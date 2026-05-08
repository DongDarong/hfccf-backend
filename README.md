# HFCCF Backend

Laravel 13 API backend for the HFCCF admin system. The current backend slice supports authentication, RBAC, forgot-password OTP flow, personal access tokens, and API rate limiting for the Vue frontend.

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
- API rate limiting for general API traffic, login, OTP, and password reset endpoints
- Consistent JSON error responses for missing API routes and rate-limit failures

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
QUEUE_CONNECTION=sync
```

### 3. Run Migrations and Seeders

```bash
php artisan migrate:fresh --seed
```

Current migration set intentionally keeps only the HFCCF auth/RBAC schema. The removed Laravel starter/demo tables are not required by this API:

- `products`
- `password_reset_tokens`
- `sessions`
- `cache`
- `cache_locks`
- `jobs`
- `job_batches`
- `failed_jobs`

### 4. Start the Backend

```bash
php artisan serve
```

If using Laragon, the expected local API host is:

```text
http://hfccf-backend.test/api
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

Rate limit responses return HTTP `429`:

```json
{
  "success": false,
  "message": "Too many requests. Please wait before trying again.",
  "data": null
}
```

## Auth API

Base URL:

```text
/api
```

| Method | Endpoint | Auth | Description |
| --- | --- | --- | --- |
| `POST` | `/auth/login` | No | Login and issue a bearer token |
| `GET` | `/auth/me` | Bearer token | Return authenticated user profile |
| `POST` | `/auth/logout` | Bearer token | Revoke current bearer token |
| `POST` | `/auth/forgot-password` | No | Issue OTP for active Super Admin password reset |
| `POST` | `/auth/verify-otp` | No | Verify password reset OTP |
| `POST` | `/auth/reset-password` | No | Reset password after OTP verification |

### Login Payload

```json
{
  "email": "superadmin01@hfccf.org",
  "password": "superadmin@123"
}
```

### Login Response

```json
{
  "success": true,
  "message": "Login successful.",
  "data": {
    "token": "1|plain-text-token",
    "user": {
      "id": "usr_001",
      "firstName": "Vanna",
      "lastName": "Nop",
      "username": "Vanna Nop",
      "email": "superadmin01@hfccf.org",
      "role": "superadmin",
      "scope": "super_admin",
      "domain": "global",
      "departmentCode": "operations",
      "department": "Operations",
      "status": "active",
      "role_permission": ["all:*"]
    }
  }
}
```

### Authenticated Requests

```http
Authorization: Bearer <token>
Accept: application/json
```

## Seed Login Accounts

| User Type | Email | Password |
| --- | --- | --- |
| Super Admin | `superadmin01@hfccf.org` | `superadmin@123` |
| Sport Admin | `sport.admin01@hfccf.org` | `sportAdmin@123` |
| Coach | `coach01@hfccf.org` | `Coach@123` |

## Rate Limiting

Configured in `app/Providers/AppServiceProvider.php` and applied in `routes/api.php`.

| Limiter | Limit |
| --- | --- |
| General API | `120 requests/minute` per authenticated user or IP |
| Login | `5 requests/minute` per email and `20 requests/minute` per IP |
| Forgot password / OTP verify | `3 requests/minute` per email and `10 requests/minute` per IP |
| Password reset | `3 requests/minute` per email and `10 requests/minute` per IP |

## Frontend Integration

The Vue frontend should point to:

```env
VITE_API_BASE_URL=/api
```

For local development, the frontend Vite proxy forwards `/api` to:

```text
http://hfccf-backend.test
```

If not using the Vite proxy, set the frontend API base URL directly:

```env
VITE_API_BASE_URL=http://hfccf-backend.test/api
```

Make sure `FRONTEND_URLS` in this backend `.env` includes the browser origin used by Vite.

## Verification Commands

```bash
php artisan route:list
php artisan migrate:status
.\vendor\bin\phpunit.bat
```

Quick login check:

```powershell
Invoke-WebRequest `
  -Uri http://hfccf-backend.test/api/auth/login `
  -Method POST `
  -ContentType 'application/json' `
  -Body '{"email":"superadmin01@hfccf.org","password":"superadmin@123"}' `
  -UseBasicParsing
```

Quick rate-limit check:

```powershell
$statuses = @()
for ($i = 1; $i -le 6; $i++) {
  try {
    Invoke-WebRequest `
      -Uri 'http://hfccf-backend.test/api/auth/login' `
      -Method POST `
      -ContentType 'application/json' `
      -Body '{"email":"rate-limit-check@hfccf.test","password":"wrong-password"}' `
      -UseBasicParsing | Out-Null
    $statuses += 200
  } catch {
    $statuses += [int]$_.Exception.Response.StatusCode
  }
}
$statuses -join ', '
```

Expected result:

```text
401, 401, 401, 401, 401, 429
```

## Project Structure

- `routes/api.php`: API routes and route-level rate limiter middleware
- `app/Http/Controllers/Api/AuthController.php`: auth and password reset flow
- `app/Http/Middleware/AuthenticateApiToken.php`: bearer token authentication
- `app/Http/Resources/AuthUserResource.php`: frontend-compatible user response mapper
- `app/Models`: auth/RBAC models
- `database/migrations/2026_04_24_150000_create_hfccf_auth_tables.php`: HFCCF auth/RBAC schema
- `database/seeders/HfccfAuthSeeder.php`: frontend-aligned seed users, roles, and permissions

## Notes

- User IDs are string IDs such as `usr_001`, matching the frontend contract.
- The role code `adminscholaship` is intentionally unchanged because the frontend depends on that exact value.
- Players and preschool students are data records, not system users.
- Product CRUD scaffold was removed because it is not part of the HFCCF system contract.
