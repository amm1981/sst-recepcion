# Configuracion del proceso en segundo plano de sincronizacion de trabajadores

Este proyecto ya registra la tarea de Laravel en `web/backend/routes/console.php`:

```php
Schedule::command('workers:sync-employee-flow')->hourly();
```

Si la ultima sincronizacion fue a las 02:00 y a las 12:00 no se ejecuto, el problema habitual es que el scheduler de Laravel no esta corriendo en el servidor. La definicion `hourly()` solo se ejecuta si existe un proceso externo llamando al scheduler.

## 1. Validar variables de Employee Flow

En `web/backend/.env` deben existir estos valores:

```env
EMPLOYEE_FLOW_BASE_URL=
EMPLOYEE_FLOW_USERNAME=
EMPLOYEE_FLOW_PASSWORD=
```

Luego limpiar cache de configuracion:

```bash
cd /ruta/a/DocsSalud/web/backend
php artisan config:clear
php artisan config:cache
```

## 2. Probar la sincronizacion manual

Ejecutar:

```bash
cd /ruta/a/DocsSalud/web/backend
php artisan workers:sync-employee-flow
```

Verificar que se cree un registro nuevo en la tabla `worker_sync_logs` y que el comando termine como `COMPLETED`.

## 3. Configurar scheduler en Linux con cron

Editar el crontab del usuario que ejecuta la aplicacion:

```bash
crontab -e
```

Agregar una sola linea:

```cron
* * * * * cd /ruta/a/DocsSalud/web/backend && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Laravel recomienda ejecutar `schedule:run` cada minuto. Internamente Laravel decide que `workers:sync-employee-flow` corra solo una vez por hora.

## 4. Configurar scheduler con systemd, alternativa recomendada en servidores

Crear `/etc/systemd/system/docssalud-scheduler.service`:

```ini
[Unit]
Description=DocsSalud Laravel Scheduler

[Service]
Type=oneshot
WorkingDirectory=/ruta/a/DocsSalud/web/backend
ExecStart=/usr/bin/php artisan schedule:run
User=www-data
Group=www-data
```

Crear `/etc/systemd/system/docssalud-scheduler.timer`:

```ini
[Unit]
Description=Run DocsSalud Laravel Scheduler every minute

[Timer]
OnBootSec=60
OnUnitActiveSec=60
Unit=docssalud-scheduler.service

[Install]
WantedBy=timers.target
```

Activar:

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now docssalud-scheduler.timer
sudo systemctl list-timers | grep docssalud
```

## 5. Configurar en Windows Server con Task Scheduler

Crear una tarea programada con estas opciones:

- Trigger: cada 1 minuto, indefinidamente.
- Program/script: ruta completa de `php.exe`.
- Arguments: `artisan schedule:run`.
- Start in: ruta completa de `DocsSalud\web\backend`.
- Usuario: una cuenta con permisos de lectura/escritura sobre `storage`, `bootstrap/cache` y acceso a la base de datos.

Ejemplo de accion:

```powershell
Program/script: C:\php\php.exe
Add arguments: artisan schedule:run
Start in: C:\inetpub\DocsSalud\web\backend
```

## 6. Verificacion posterior

Despues de configurarlo, ejecutar:

```bash
php artisan schedule:list
```

Debe aparecer:

```text
workers:sync-employee-flow    Every hour
```

Luego revisar cada hora:

```sql
select id, started_at, finished_at, status, total_received, created_count, updated_count, error_count
from worker_sync_logs
order by id desc
limit 5;
```

Si no aparecen registros nuevos, revisar:

- El cron o timer esta activo.
- La ruta del proyecto es correcta.
- El binario de PHP es el correcto.
- `php artisan schedule:run` se puede ejecutar desde el mismo usuario del servicio.
- El servidor tiene salida de red hacia Employee Flow.
- `storage/logs/laravel.log` no registra errores de credenciales, SSL, permisos o base de datos.

