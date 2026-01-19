# How to Use Laravel ReverseKit - Complete Guide

> Generate complete Laravel backend scaffolding from JSON, API, OpenAPI, Postman, or Database in seconds!

## 📦 Installation

```bash
composer require shaqi-labs/laravel-reversekit
```

No additional configuration needed - Laravel auto-discovers the package.

---

## 🚀 Quick Start (5 Minutes)

### Method 1: From JSON File

**Step 1:** Create a JSON file with your sample API data:

```json
// users.json
{
  "users": [
    {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "is_active": true,
      "created_at": "2024-01-15T10:30:00Z",
      "posts": [
        {
          "id": 1,
          "title": "My First Post",
          "body": "Content here...",
          "published": true
        }
      ]
    }
  ]
}
```

**Step 2:** Run the generator:

```bash
php artisan reverse:generate users.json
```

**Step 3:** That's it! ReverseKit generates:
- ✅ `User` and `Post` Models with relationships
- ✅ Migrations with correct column types
- ✅ API Controllers with CRUD methods
- ✅ Form Requests with validation rules
- ✅ API Resources for JSON responses
- ✅ Factories & Seeders
- ✅ Feature Tests
- ✅ Routes

---

## 📋 All Input Methods

### 1️⃣ From JSON File/String

```bash
# From file
php artisan reverse:generate path/to/data.json

# From inline JSON string
php artisan reverse:generate '{"products": [{"name": "iPhone", "price": 999.99}]}'
```

### 2️⃣ From Live API URL

```bash
# Public API
php artisan reverse:generate --from-url=https://jsonplaceholder.typicode.com/users

# With Bearer Token
php artisan reverse:generate --from-url=https://api.example.com/users --auth-token=your-api-token
```

### 3️⃣ From OpenAPI/Swagger Spec

```bash
php artisan reverse:generate --from-openapi=openapi.yaml
php artisan reverse:generate --from-openapi=swagger.json
```

### 4️⃣ From Postman Collection

```bash
php artisan reverse:generate --from-postman=collection.json
```

### 5️⃣ From Existing Database

```bash
# All tables
php artisan reverse:generate --from-database=*

# Specific tables
php artisan reverse:generate --from-database=users,posts,comments
```

### 6️⃣ Interactive Mode (No files needed!)

```bash
php artisan reverse:interactive
```

Follow the prompts to:
1. Define models (User, Post, Comment...)
2. Add fields with types (string, integer, boolean...)
3. Set up relationships (hasMany, belongsTo...)
4. Choose what to generate

---

## ⚙️ Useful Options

### Preview Before Generating

```bash
php artisan reverse:generate data.json --preview
```

Shows what will be generated without creating files.

### Generate Only Specific Components

```bash
# Only models and migrations
php artisan reverse:generate data.json --only=model,migration

# Only controllers and routes
php artisan reverse:generate data.json --only=controller,routes
```

**Available components:** `model`, `migration`, `controller`, `resource`, `request`, `policy`, `factory`, `seeder`, `test`, `routes`

### Overwrite Existing Files

```bash
php artisan reverse:generate data.json --force
```

### Custom Namespace

```bash
php artisan reverse:generate data.json --namespace=Domain\\Shop
```

### Module/Domain Prefix

```bash
php artisan reverse:generate data.json --module=Blog
```

---

## 📁 Generated File Structure

After running ReverseKit on a `users` + `posts` JSON:

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── UserController.php
│   │   └── PostController.php
│   ├── Requests/
│   │   ├── StoreUserRequest.php
│   │   ├── UpdateUserRequest.php
│   │   ├── StorePostRequest.php
│   │   └── UpdatePostRequest.php
│   └── Resources/
│       ├── UserResource.php
│       └── PostResource.php
├── Models/
│   ├── User.php
│   └── Post.php
└── Policies/
    ├── UserPolicy.php
    └── PostPolicy.php

database/
├── factories/
│   ├── UserFactory.php
│   └── PostFactory.php
├── migrations/
│   ├── 2024_01_15_000001_create_users_table.php
│   └── 2024_01_15_000002_create_posts_table.php
└── seeders/
    ├── UserSeeder.php
    └── PostSeeder.php

tests/Feature/
├── UserControllerTest.php
└── PostControllerTest.php

routes/
└── api.php (routes appended)
```

---

## 💡 Real-World Examples

### Example 1: E-commerce Product Catalog

```json
{
  "categories": [{
    "id": 1,
    "name": "Electronics",
    "slug": "electronics",
    "products": [{
      "id": 1,
      "name": "iPhone 15",
      "price": 999.99,
      "stock": 50,
      "is_featured": true,
      "images": ["url1", "url2"]
    }]
  }]
}
```

```bash
php artisan reverse:generate products.json --only=model,migration,controller
php artisan migrate
```

### Example 2: Blog System from Public API

```bash
# Fetch from JSONPlaceholder API
php artisan reverse:generate --from-url=https://jsonplaceholder.typicode.com/posts --preview

# Generate if looks good
php artisan reverse:generate --from-url=https://jsonplaceholder.typicode.com/posts
```

### Example 3: Reverse Engineer Existing Database

```bash
# Preview what's in your database
php artisan reverse:generate --from-database=* --preview

# Generate models for specific tables
php artisan reverse:generate --from-database=products,orders,customers --only=model,resource
```

### Example 4: From OpenAPI Spec

```yaml
# openapi.yaml
openapi: 3.0.0
info:
  title: My API
paths:
  /users:
    get:
      responses:
        '200':
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/User'
components:
  schemas:
    User:
      type: object
      properties:
        id:
          type: integer
        name:
          type: string
        email:
          type: string
          format: email
```

```bash
php artisan reverse:generate --from-openapi=openapi.yaml
```

---

## ✏️ Customizing Stubs

Want to customize generated code? Publish the stubs:

```bash
php artisan vendor:publish --tag=reversekit-stubs
```

Edit files in `resources/stubs/reversekit/`:
- `model.stub`
- `controller.stub`
- `migration.stub`
- etc.

---

## ❓ FAQ

**Q: Does it overwrite my existing files?**
A: No! By default, existing files are skipped. Use `--force` to overwrite.

**Q: Can I generate only specific components?**
A: Yes! Use `--only=model,migration` to select what you need.

**Q: Does it support nested relationships?**
A: Yes! Nested objects and arrays are automatically converted to relationships.

**Q: What Laravel versions are supported?**
A: Laravel 10, 11, and 12.

**Q: Can I use it with modules/domains?**
A: Yes! Use `--module=Blog` or `--namespace=Domain\\Blog`.

---

## 🔗 Links

- **GitHub:** [github.com/shaqi-labs/laravel-reversekit](https://github.com/shaqi-labs/laravel-reversekit)
- **Packagist:** [packagist.org/packages/shaqi-labs/laravel-reversekit](https://packagist.org/packages/shaqi-labs/laravel-reversekit)

---

**Made with ❤️ by Shaqi Labs**
