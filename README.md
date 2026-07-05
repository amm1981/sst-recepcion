# sst-recepcion

Sistema DocsSalud para recepcion y seguimiento de documentos medicos SST.

## Estructura

- `app/`: aplicativo Android/Kotlin con soporte offline.
- `web/backend/`: API Laravel.
- `web/frontend/`: frontend React.

## Notas de despliegue

- No versionar `.env`, `local.properties`, builds, APKs, `vendor` ni `node_modules`.
- Configurar `API_BASE_URL` en `app/local.properties` para cada ambiente.
- Configurar variables de backend desde `web/backend/.env.example`.
- Para PRD, usar dominio HTTPS estable en lugar de IP local.

## Validacion local

```powershell
cd app
.\gradlew.bat :app:assembleDebug

cd ..\web\backend
php artisan test

cd ..\frontend
npm run build
```
