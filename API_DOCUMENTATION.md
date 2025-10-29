# API Admin Management - Documentation

## Base URL
```
http://localhost:8000/api
```

## Authentication
This API uses Laravel Sanctum for authentication. After login, you'll receive a bearer token that should be included in the `Authorization` header for protected routes.

```
Authorization: Bearer {your-token}
```

## Test Users
- **Super Admin**: `admin@example.com` / `password`
- **Admin**: `admin.user@example.com` / `password`
- **User**: `user@example.com` / `password`

---

## Authentication Endpoints

### 1. Login
**POST** `/auth/login`

**Request Body:**
```json
{
  "email": "admin@example.com",
  "password": "password"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": 1,
      "name": "Super Admin",
      "email": "admin@example.com",
      "is_admin": true,
      "is_active": true,
      "roles": [...]
    },
    "token": "1|abcd1234...",
    "token_type": "Bearer"
  }
}
```

### 2. Logout
**POST** `/auth/logout`

**Headers:** `Authorization: Bearer {token}`

**Response:**
```json
{
  "success": true,
  "message": "Logout successful"
}
```

### 3. Get Current User
**GET** `/auth/me`

**Headers:** `Authorization: Bearer {token}`

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Super Admin",
    "email": "admin@example.com",
    "roles": [...],
    "permissions": [...]
  }
}
```

### 4. Refresh Token
**POST** `/auth/refresh`

**Headers:** `Authorization: Bearer {token}`

---

## Dashboard Endpoints

### 1. Get Statistics
**GET** `/dashboard`

**Response:**
```json
{
  "success": true,
  "data": {
    "total_users": 10,
    "total_admins": 3,
    "active_users": 9,
    "inactive_users": 1,
    "total_roles": 4,
    "total_permissions": 17,
    "total_audit_logs": 45
  }
}
```

### 2. Recent Activities
**GET** `/dashboard/recent-activities`

### 3. User Growth
**GET** `/dashboard/user-growth`

### 4. Action Statistics
**GET** `/dashboard/action-stats`

### 5. Top Active Users
**GET** `/dashboard/top-active-users`

---

## User Management Endpoints

### 1. List Users
**GET** `/users?per_page=15&search=john`

**Query Parameters:**
- `per_page` (optional): Number of items per page (default: 15)
- `search` (optional): Search by name or email

**Response:**
```json
{
  "success": true,
  "data": [...],
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 15,
    "total": 45
  }
}
```

### 2. Get Single User
**GET** `/users/{id}`

### 3. Create User
**POST** `/users`

**Request Body:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "is_admin": false,
  "is_active": true,
  "phone": "+1234567890",
  "address": "123 Street",
  "roles": ["viewer"]
}
```

### 4. Update User
**PUT** `/users/{id}`

**Request Body:** (all fields optional)
```json
{
  "name": "John Updated",
  "email": "john.updated@example.com",
  "is_active": false,
  "roles": ["admin"]
}
```

### 5. Delete User
**DELETE** `/users/{id}`

---

## Admin Management Endpoints

Same structure as User Management, but uses `/admins` prefix and only manages users with `is_admin=true`.

- **GET** `/admins` - List admins
- **GET** `/admins/{id}` - Get single admin
- **POST** `/admins` - Create admin
- **PUT** `/admins/{id}` - Update admin
- **DELETE** `/admins/{id}` - Delete admin

---

## Role Management Endpoints

### 1. List Roles
**GET** `/roles?per_page=15&search=admin`

### 2. Get Single Role
**GET** `/roles/{id}`

### 3. Create Role
**POST** `/roles`

**Request Body:**
```json
{
  "name": "content-manager",
  "guard_name": "web",
  "permissions": ["view users", "edit users"]
}
```

### 4. Update Role
**PUT** `/roles/{id}`

**Request Body:**
```json
{
  "name": "content-manager-updated",
  "permissions": ["view users", "edit users", "create users"]
}
```

### 5. Delete Role
**DELETE** `/roles/{id}`

---

## Permission Management Endpoints

### 1. List Permissions
**GET** `/permissions?per_page=15&search=users`

### 2. Create Permission
**POST** `/permissions`

**Request Body:**
```json
{
  "name": "manage posts",
  "guard_name": "web"
}
```

### 3. Delete Permission
**DELETE** `/permissions/{id}`

### 4. Assign Permissions to Role
**POST** `/permissions/roles/{roleId}/assign`

**Request Body:**
```json
{
  "permissions": ["view users", "edit users", "delete users"]
}
```

### 5. Get Role Permissions
**GET** `/permissions/roles/{roleId}`

### 6. Assign Permissions to User
**POST** `/permissions/users/{userId}/assign`

**Request Body:**
```json
{
  "permissions": ["view dashboard"]
}
```

### 7. Get User Permissions
**GET** `/permissions/users/{userId}`

---

## Audit Logs Endpoints

### 1. List Audit Logs
**GET** `/audit-logs?per_page=15&action=create&user_id=1&model_type=User&start_date=2025-01-01&end_date=2025-12-31`

**Query Parameters:**
- `per_page` (optional): Number of items per page
- `action` (optional): Filter by action (create, update, delete, login, etc.)
- `user_id` (optional): Filter by user ID
- `model_type` (optional): Filter by model type (User, Role, Permission, etc.)
- `start_date` (optional): Filter by start date
- `end_date` (optional): Filter by end date

### 2. Get Single Audit Log
**GET** `/audit-logs/{id}`

### 3. Get User Audit Logs
**GET** `/audit-logs/user/{userId}`

### 4. Get Model Audit Logs
**GET** `/audit-logs/model/{modelType}/{modelId}`

Example: `GET /audit-logs/model/User/5`

---

## Response Format

All API responses follow this format:

**Success Response:**
```json
{
  "success": true,
  "message": "Operation successful",
  "data": { ... }
}
```

**Error Response:**
```json
{
  "success": false,
  "message": "Error message",
  "errors": { ... }
}
```

---

## Error Codes

- `200` - Success
- `201` - Created
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Validation Error
- `500` - Server Error

---

## Available Permissions

- `view users`, `create users`, `edit users`, `delete users`
- `view admins`, `create admins`, `edit admins`, `delete admins`
- `view roles`, `create roles`, `edit roles`, `delete roles`
- `view permissions`, `create permissions`, `edit permissions`, `delete permissions`, `assign permissions`
- `view audit logs`
- `view dashboard`

---

## Available Roles

- **super-admin**: Full access to all permissions
- **admin**: Can manage users and view dashboard
- **user-manager**: Can only manage users
- **viewer**: Can only view data

---

## Migration to PostgreSQL

Pour migrer vers PostgreSQL plus tard:

1. Modifier le `.env`:
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=api_admin_management
DB_USERNAME=postgres
DB_PASSWORD=your_password
DB_CHARSET=utf8
DB_COLLATION=utf8_unicode_ci
```

2. Exécuter les migrations:
```bash
php artisan migrate:fresh --seed
```

---

## Installation & Setup

1. Clone the repository
2. Copy `.env.example` to `.env`
3. Configure database credentials in `.env`
4. Run migrations and seeders:
```bash
php artisan migrate:fresh --seed
```
5. Start the development server:
```bash
php artisan serve
```

The API will be available at `http://localhost:8000/api`
