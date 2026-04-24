# hfccf-backend

A Laravel 13 backend API for managing products with standard CRUD endpoints, request validation, and consistent JSON responses.

## Stack

- PHP 8.3+
- Laravel 13
- SQLite by default (`.env.example`)
- Vite for frontend asset build tooling

## Features

- List products with pagination
- Create a product
- View a single product
- Update a product
- Delete a product
- Validation errors returned as JSON
- Consistent API response envelope

## Getting Started

### 1. Install dependencies

```bash
composer install
npm install
```

### 2. Configure environment

```bash
copy .env.example .env
php artisan key:generate
```

The default environment uses SQLite:

```bash
New-Item -ItemType File -Force database/database.sqlite
```

Then update `.env` if needed:

```env
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
```

### 3. Run migrations

```bash
php artisan migrate
```

### 4. Start the app

Backend only:

```bash
php artisan serve
```

Backend with queue/log/vite dev processes:

```bash
composer dev
```

### 5. Run tests

```bash
composer test
```

## API

Base URL:

```text
http://127.0.0.1:8000/api
```

### Endpoints

| Method | Endpoint | Description |
| --- | --- | --- |
| `GET` | `/products` | List products |
| `POST` | `/products` | Create a product |
| `GET` | `/products/{id}` | Get a product |
| `PUT` / `PATCH` | `/products/{id}` | Update a product |
| `DELETE` | `/products/{id}` | Delete a product |

### Query Parameters

`GET /products` supports:

- `per_page`: number of records per page, minimum `1`, maximum `100`, default `10`

### Request Payload

Create product:

```json
{
  "name": "Wireless Mouse",
  "description": "Ergonomic Bluetooth mouse",
  "price": 24.99,
  "stock": 35
}
```

Update product:

```json
{
  "price": 19.99,
  "stock": 50
}
```

### Validation Rules

- `name`: required on create, string, max 255 characters
- `description`: nullable string
- `price`: required on create, numeric, minimum 0
- `stock`: required on create, integer, minimum 0

## Response Format

Successful responses use this structure:

```json
{
  "success": true,
  "message": "Product retrieved successfully.",
  "data": {
    "id": 1,
    "name": "Wireless Mouse",
    "description": "Ergonomic Bluetooth mouse",
    "price": 24.99,
    "stock": 35,
    "created_at": "2026-04-24T07:00:00.000000Z",
    "updated_at": "2026-04-24T07:00:00.000000Z"
  }
}
```

List responses include pagination metadata:

```json
{
  "success": true,
  "message": "Products retrieved successfully.",
  "data": {
    "products": [],
    "pagination": {
      "current_page": 1,
      "last_page": 1,
      "per_page": 10,
      "total": 0
    }
  }
}
```

Validation failures return HTTP `422`:

```json
{
  "success": false,
  "message": "Validation failed.",
  "data": {
    "errors": {
      "name": [
        "The name field is required."
      ]
    }
  }
}
```

Missing products return HTTP `404`:

```json
{
  "success": false,
  "message": "Product not found.",
  "data": null
}
```

## Project Structure

- `routes/api.php`: API route definitions
- `app/Http/Controllers/Api/ProductController.php`: product CRUD logic
- `app/Http/Requests`: request validation
- `app/Http/Resources/ProductResource.php`: API output formatting
- `database/migrations`: schema definitions

## Notes

- The repository still includes Laravel starter frontend tooling, but the main implemented feature is the product API.
- If you switch away from SQLite, update the database settings in `.env` before running migrations.
