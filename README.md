# HFCCF Backend

Laravel 13 API backend for the HFCCF admin system. The current backend supports authentication, RBAC, user management, Preschool, Scholarship, English, and Sport foundation APIs, along with forgot-password OTP flow, personal access tokens, and API rate limiting for the Vue frontend.

## Stack

- PHP 8.3+
- Laravel 13
- MySQL or MariaDB
- Token-based API authentication

## Current Features

- Login with active HFCCF system users
- Authenticated user profile endpoint
- Logout with token revocation
- Forgot password OTP request, OTP verification, and password reset
- Role, permission, department, and user seed data aligned with the frontend contract
- User CRUD with permission syncing, avatar upload, and archived deletion flow
- Preschool module APIs for classes, students, teachers, attendance, and payments
- Scholarship module APIs for students, applications, reviews, and status workflow
- English module APIs for classes, students, teachers, tasks, and submissions
- Sport foundation APIs for teams, players, coaches, matches, events, tournaments, and standings
- API rate limiting for general API traffic, login, OTP, and password reset endpoints
- Queue-based asynchronous image optimization after upload
- Automatic user archiving to `deleted_users` table upon deletion
- Standardized sequential user ID indexing (`usr_001`, `usr_002`, etc.)
- Consistent JSON error responses for missing API routes and rate-limit failures

## Module Coverage

The backend currently exposes these stabilized domain areas:

- Core auth and RBAC
- User management and profile updates
- Preschool module
- Scholarship module
- English module
- Sport foundation, including event-driven match scores and tournament standings

## Setup

### 1. Install Dependencies

```bash
composer install
npm install
```

### 2. Configure Environment

```bash
copy .env.example .env
php artisan key:generate
```

Use MySQL/MariaDB for local development:

```env
APP_NAME=hfccf-backend
APP_URL=http://hfccf-backend.test
FRONTEND_URLS=http://localhost:5173,http://127.0.0.1:5173

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hfccf_backend
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=database
```

### 3. Run Migrations and Seeders

```bash
php artisan migrate:fresh --seed
```

### 4. Start the Queue Worker

Image uploads dispatch an asynchronous optimization job after the original file is stored. Keep a worker running in any environment that uses the database queue:

```bash
php artisan queue:work --tries=3 --timeout=60
```

The existing `composer run dev` script already starts a queue listener for local development.

### 5. Start the Backend

```bash
php artisan serve
```

## API Response Format

Successful responses:

```json
{
  "success": true,
  "message": "Login successful.",
  "data": {}
}
```

Error responses:

```json
{
  "success": false,
  "message": "Invalid credentials.",
  "data": null
}
```

## Seed Login Accounts

| User Type | Email | Password |
| --- | --- | --- |
| Super Admin | `superadmin01@hfccf.org` | `superadmin@123` |
| Super Admin | `dngdarong@gmail.com` | `Darong@123` |
| Sport Admin | `sport.admin01@hfccf.org` | `sportAdmin@123` |

## Rate Limiting

Configured in `app/Providers/AppServiceProvider.php` and applied in `bootstrap/app.php` and `routes/api.php`.

| Limiter | Limit | Scope |
| --- | --- | --- |
| Global | `500 requests/minute` | Entire application (per IP) |
| API (Guest) | `60 requests/minute` | Unauthenticated API traffic (per IP) |
| API (Auth) | `300 requests/minute` | Authenticated API traffic (per User ID) |
| Login | `5 requests/minute` | Per email (also 20/min per IP) |
| Forgot password / OTP verify | `3 requests/minute` | Per email (also 10/min per IP) |
| Password reset | `3 requests/minute` | Per email (also 10/min per IP) |

## Project Structure

- `routes/api.php`: API routes and route-level rate limiter middleware
- `app/Http/Controllers/Api/AuthController.php`: auth and password reset flow
- `app/Http/Controllers/Api/UserController.php`: user CRUD
- `app/Http/Controllers/Api/Preschool/`: preschool module controllers
- `app/Http/Controllers/Api/Scholarship/`: scholarship module controllers
- `app/Http/Controllers/Api/English/`: english module controllers
- `app/Http/Controllers/Api/Sport/`: sport module controllers
- `app/Http/Middleware/AuthenticateApiToken.php`: bearer token authentication
- `app/Models/User.php`: Main user model with automatic archiving logic
- `app/Models/DeletedUser.php`: Archive model for deleted users
- `database/migrations/2026_05_09_043402_create_deleted_users_table.php`: User archive schema
- `database/seeders/HfccfAuthSeeder.php`: frontend-aligned seed users, roles, and permissions

## Notes

- User IDs are sequential string IDs such as `usr_001`, `usr_002`, matching the frontend contract.
- The role code `adminscholarship` is the canonical scholarship admin role used by the frontend and backend.
- Players and preschool students are data records, not system users.
- Sport matches derive score snapshots from match events; standings are derived from completed tournament matches.
- Uploaded images are stored on the configured filesystem disk and optimized asynchronously by queued post-upload jobs.
- Frontend module integration uses standardized JSON response envelopes with `success`, `message`, and `data`.
