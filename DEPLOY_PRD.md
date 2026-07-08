# Despliegue PRD - DocsSalud SST

Dominio productivo: `https://sst.agrocalera.app`

Esta guia asume un servidor Linux con Nginx, PHP-FPM, MySQL, Composer y Node.js. Ajusta rutas, usuario del sistema y versiones segun tu hosting.

## 1. Requisitos del servidor

- PHP `8.2+` con extensiones comunes de Laravel: `bcmath`, `ctype`, `curl`, `fileinfo`, `json`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `zip`.
- Composer 2.
- MySQL 8 o MariaDB compatible.
- Node.js LTS y npm.
- Nginx con HTTPS activo para `sst.agrocalera.app`.
- Supervisor o servicio equivalente para la cola Laravel.
- Certificado SSL valido. Recomendado: Let's Encrypt.

## 2. Obtener codigo

```bash
sudo mkdir -p /var/www/sst-recepcion
sudo chown -R $USER:www-data /var/www/sst-recepcion
cd /var/www/sst-recepcion

git clone https://github.com/amm1981/sst-recepcion.git .
git checkout main
git pull origin main
```

## 3. Backend Laravel

```bash
cd /var/www/sst-recepcion/web/backend
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

Edita `.env` con valores reales de PRD:

```env
APP_NAME=DocsSalud
APP_ENV=production
APP_DEBUG=false
APP_URL=https://sst.agrocalera.app
FRONTEND_URL=https://sst.agrocalera.app
CORS_ALLOWED_ORIGINS=https://sst.agrocalera.app

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=docssalud
DB_USERNAME=docssalud
DB_PASSWORD=CAMBIAR_PASSWORD_REAL

QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database
FILESYSTEM_DISK=local
```

Crea la base de datos y usuario con credenciales reales:

```sql
CREATE DATABASE docssalud CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'docssalud'@'localhost' IDENTIFIED BY 'CAMBIAR_PASSWORD_REAL';
GRANT ALL PRIVILEGES ON docssalud.* TO 'docssalud'@'localhost';
FLUSH PRIVILEGES;
```

Ejecuta migraciones:

```bash
php artisan migrate --force
```

Primera instalacion solamente: cargar roles, permisos y catalogos base.

```bash
php artisan db:seed --force
```

Importante: el seeder crea usuarios iniciales con password `Password123`. Cambia esas contrasenas inmediatamente desde la base o desde la web, y no vuelvas a ejecutar `db:seed` en PRD despues de operar con usuarios reales porque puede restablecer esos usuarios semilla.

Optimiza Laravel:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Permisos:

```bash
sudo chown -R www-data:www-data /var/www/sst-recepcion/web/backend/storage /var/www/sst-recepcion/web/backend/bootstrap/cache
sudo chmod -R ug+rwX /var/www/sst-recepcion/web/backend/storage /var/www/sst-recepcion/web/backend/bootstrap/cache
```

## 4. Frontend React

```bash
cd /var/www/sst-recepcion/web/frontend
cp .env.example .env
```

Confirma `.env`:

```env
VITE_API_URL=https://sst.agrocalera.app/api
```

Compila:

```bash
npm ci
npm run build
```

El build queda en:

```text
/var/www/sst-recepcion/web/frontend/dist
```

## 5. Nginx

Ejemplo para servir el frontend en `/` y la API Laravel en `/api` bajo el mismo dominio:

```nginx
server {
    listen 80;
    server_name sst.agrocalera.app;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name sst.agrocalera.app;

    root /var/www/sst-recepcion/web/frontend/dist;
    index index.html;

    client_max_body_size 50M;

    location / {
        try_files $uri $uri/ /index.html;
    }

    location ^~ /api/ {
        root /var/www/sst-recepcion/web/backend/public;
        try_files $uri /index.php?$query_string;

        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME /var/www/sst-recepcion/web/backend/public/index.php;
        fastcgi_param DOCUMENT_ROOT /var/www/sst-recepcion/web/backend/public;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Valida y recarga:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

Si usas Certbot:

```bash
sudo certbot --nginx -d sst.agrocalera.app
```

## 6. Cola Laravel

La cola procesa trabajos con `QUEUE_CONNECTION=database`. Configura Supervisor:

```ini
[program:docssalud-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/sst-recepcion/web/backend/artisan queue:work database --sleep=3 --tries=3 --timeout=120
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/sst-recepcion/web/backend/storage/logs/worker.log
stopwaitsecs=3600
```

Activar:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart docssalud-worker:*
```

## 7. Android

El proyecto ya usa por defecto:

```text
https://sst.agrocalera.app/api/
```

En la maquina de build, confirma `app/local.properties`:

```properties
API_BASE_URL="https://sst.agrocalera.app/api/"
```

Build debug para validacion interna:

```powershell
cd C:\Users\JMartinez\Documents\Proyectos\DocsSalud\app
.\gradlew.bat :app:assembleDebug
```

Para APK/AAB de produccion, configura un keystore fuera del repo y agrega signing config de release antes de generar:

```powershell
.\gradlew.bat :app:assembleRelease
```

No subas `.jks`, `.keystore`, passwords ni `local.properties` al repositorio.

## 8. Validacion antes de publicar

En local o servidor de build:

```bash
cd web/backend
php artisan test

cd ../frontend
npm run build
```

Android:

```powershell
cd app
.\gradlew.bat :app:assembleDebug
```

Validacion HTTP en PRD:

```bash
curl -I https://sst.agrocalera.app
curl -I https://sst.agrocalera.app/api/auth/me
```

El segundo comando puede devolver `401 Unauthorized`; eso es correcto si la API responde y no hay token.

## 9. Checklist PRD

- DNS `sst.agrocalera.app` apunta al servidor correcto.
- SSL activo y sin errores.
- `.env` de backend tiene `APP_ENV=production` y `APP_DEBUG=false`.
- `APP_KEY` generado y conservado.
- Base de datos creada, migrada y respaldada.
- Usuarios semilla cambiados o desactivados.
- Frontend compilado con `VITE_API_URL=https://sst.agrocalera.app/api`.
- Android apunta a `https://sst.agrocalera.app/api/`.
- Nginx sirve frontend y enruta `/api` a Laravel.
- Cola Laravel activa en Supervisor.
- Permisos de `storage` y `bootstrap/cache` correctos.
- Backups de base de datos y archivos configurados.

## 10. Actualizaciones posteriores

```bash
cd /var/www/sst-recepcion
git pull origin main

cd web/backend
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo supervisorctl restart docssalud-worker:*

cd ../frontend
npm ci
npm run build

sudo systemctl reload nginx
```

No ejecutes `php artisan migrate:fresh` ni `php artisan db:seed` en PRD con datos reales.

## 11. Rollback basico

Antes de actualizar, guarda el commit activo:

```bash
git rev-parse HEAD
```

Si hay que volver:

```bash
cd /var/www/sst-recepcion
git checkout COMMIT_ANTERIOR

cd web/backend
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo supervisorctl restart docssalud-worker:*

cd ../frontend
npm ci
npm run build
sudo systemctl reload nginx
```

Si el cambio incluyo migraciones destructivas, restaura backup de base de datos antes del rollback.
