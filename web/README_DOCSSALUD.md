# DocsSalud Web

## Backend

```powershell
cd C:\Users\JMartinez\Documents\Proyectos\DocsSalud\web\backend
composer install
```

Configure MySQL in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=docssalud
DB_USERNAME=root
DB_PASSWORD=5CY7ek$%bLn9
```

Create the database and seed initial data:

```powershell
& 'C:\wamp64\bin\mysql\mysql8.4.7\bin\mysql.exe' -u root -p -e "CREATE DATABASE IF NOT EXISTS docssalud CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan migrate:fresh --seed
php artisan serve --host=127.0.0.1 --port=8000
```

Seeded users:

- `admin@docssalud.test` / `Password123`
- `rrhh@docssalud.test` / `Password123`
- `sst@docssalud.test` / `Password123`

## Frontend

```powershell
cd C:\Users\JMartinez\Documents\Proyectos\DocsSalud\web\frontend
npm install
npm run dev
```

Frontend URL: `http://localhost:5173`
Backend API: `http://localhost:8000/api`
