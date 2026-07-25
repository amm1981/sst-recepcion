# Despliegue seguro de cambios

Esta guia resume los pasos para subir cambios a GitHub y desplegarlos sin perder informacion registrada.

## 1. Antes de subir a GitHub

Validar que el sistema compile y que las pruebas pasen:

```powershell
cd C:\Users\JMartinez\Documents\Proyectos\DocsSalud\web\backend
php artisan test

cd C:\Users\JMartinez\Documents\Proyectos\DocsSalud\web\frontend
npm run build

cd C:\Users\JMartinez\Documents\Proyectos\DocsSalud\app
.\gradlew.bat :app:assembleDebug
```

Revisar cambios pendientes:

```powershell
cd C:\Users\JMartinez\Documents\Proyectos\DocsSalud
git status -sb
git diff --stat
```

Crear commit y subir:

```powershell
git add -A
git commit -m "Descripcion clara del cambio"
git push origin main
```

Confirmar que no quedo nada pendiente:

```powershell
git status -sb
```

Debe mostrar:

```text
## main...origin/main
```

## 2. Antes de desplegar en produccion

Tomar respaldo de base de datos:

```bash
mysqldump -u USUARIO -p BASE_DE_DATOS > backup_docssalud_$(date +%Y%m%d_%H%M%S).sql
```

Tomar respaldo de archivos subidos por usuarios:

```bash
tar -czf backup_storage_docssalud_$(date +%Y%m%d_%H%M%S).tar.gz /var/www/sst-recepcion/web/backend/storage/app
```

Guardar el commit actual de produccion para poder regresar:

```bash
cd /var/www/sst-recepcion
git rev-parse HEAD
```

Copiar ese hash en un lugar seguro antes de continuar.

## 3. Actualizar codigo en produccion

Entrar al proyecto y traer los ultimos cambios:

```bash
cd /var/www/sst-recepcion
git fetch origin
git status -sb
git pull origin main
```

Si `git status -sb` muestra cambios locales no esperados en produccion, detener el despliegue y revisar antes de continuar.

## 4. Actualizar backend

```bash
cd /var/www/sst-recepcion/web/backend
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Importante:

- Usar `php artisan migrate --force`.
- No usar `php artisan migrate:fresh`.
- No usar `php artisan migrate:refresh`.
- No usar `php artisan db:wipe`.
- No ejecutar `php artisan db:seed --force` en produccion con datos reales, salvo que se haya revisado especificamente que el seeder no modifica usuarios o registros existentes.

## 5. Actualizar frontend web

```bash
cd /var/www/sst-recepcion/web/frontend
npm ci
npm run build
```

Recargar Nginx:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

## 6. Reiniciar procesos

Reiniciar la cola de Laravel:

```bash
sudo supervisorctl restart docssalud-worker:*
```

Si el servidor usa PHP-FPM, recargarlo:

```bash
sudo systemctl reload php8.2-fpm
```

Ajustar `php8.2-fpm` si el servidor usa otra version de PHP.

## 7. Validar despues del despliegue

Validar la web:

```bash
curl -I https://sst.agrocalera.app
```

Validar la API:

```bash
curl -I https://sst.agrocalera.app/api/auth/me
```

El endpoint `/api/auth/me` puede responder `401 Unauthorized`; eso es normal si no se envia token. Lo importante es que responda la API.

Validar manualmente:

- Iniciar sesion en la web.
- Revisar que los documentos existentes sigan apareciendo.
- Revisar que los documentos anulados aparezcan en su apartado.
- Registrar un documento de prueba.
- Confirmar que la fecha del documento se valide correctamente.
- Confirmar que cargo, gerencia y sector se conserven en el documento registrado.
- Confirmar que los reportes y estadisticas no incluyan documentos anulados.
- Probar sincronizacion desde Android si aplica.

## 8. Plan de regreso si algo falla

Si falla el despliegue antes de ejecutar migraciones:

```bash
cd /var/www/sst-recepcion
git checkout COMMIT_ANTERIOR
```

Si falla despues de ejecutar migraciones, evaluar primero el error. Si se necesita regresar completamente, restaurar base de datos y archivos desde backup:

```bash
mysql -u USUARIO -p BASE_DE_DATOS < backup_docssalud_FECHA.sql
tar -xzf backup_storage_docssalud_FECHA.tar.gz -C /
```

Luego volver al commit anterior:

```bash
cd /var/www/sst-recepcion
git checkout COMMIT_ANTERIOR

cd web/backend
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache

cd ../frontend
npm ci
npm run build

sudo supervisorctl restart docssalud-worker:*
sudo nginx -t
sudo systemctl reload nginx
```

## 9. Regla principal para no perder informacion

Nunca ejecutar comandos que reinicien o borren la base de datos en produccion.

Comandos prohibidos en produccion con datos reales:

```bash
php artisan migrate:fresh
php artisan migrate:refresh
php artisan db:wipe
php artisan db:seed --force
```

El comando correcto para aplicar cambios de estructura sin borrar datos es:

```bash
php artisan migrate --force
```
