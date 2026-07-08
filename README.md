# sst-recepcion

Sistema DocsSalud para recepcion y seguimiento de documentos medicos SST.

## Estructura

- `app/`: aplicativo Android/Kotlin con soporte offline.
- `web/backend/`: API Laravel.
- `web/frontend/`: frontend React.

## Notas de despliegue

- No versionar `.env`, `local.properties`, builds, APKs, `vendor` ni `node_modules`.
- Dominio PRD configurado: `https://sst.agrocalera.app`.
- Android usa `https://sst.agrocalera.app/api/` como endpoint por defecto. Para sobrescribirlo por ambiente, configurar `API_BASE_URL` en `app/local.properties`.
- Frontend usa `VITE_API_URL=https://sst.agrocalera.app/api`.
- Backend debe usar `APP_URL=https://sst.agrocalera.app`, `FRONTEND_URL=https://sst.agrocalera.app` y `CORS_ALLOWED_ORIGINS=https://sst.agrocalera.app`.
- Configurar variables de backend desde `web/backend/.env.example`.
- En PRD ejecutar migraciones y cachear configuracion despues de definir `.env`: `php artisan migrate --force`, `php artisan config:cache`, `php artisan route:cache`.

## Validacion local

```powershell
cd app
.\gradlew.bat :app:assembleDebug

cd ..\web\backend
php artisan test

cd ..\frontend
npm run build
```
