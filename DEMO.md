# Vecna ERP - Quick API Demo

## ✅ Your Backend is Running!

**Server URL:** http://127.0.0.1:8000

## 🚀 Quick Test (Copy & Paste These Commands)

### 1. Check Health
```bash
curl http://127.0.0.1:8000/up
```

### 2. Register a Tenant & User
```bash
curl -X POST http://127.0.0.1:8000/api/register-tenant \
  -H "Content-Type: application/json" \
  -d '{
    "business_name": "Demo Store",
    "email": "demo@example.com",
    "phone": "254712345678",
    "industry": "retail",
    "subdomain": "demo",
    "admin_name": "Admin User",
    "admin_email": "admin@demo.com",
    "admin_password": "password123",
    "admin_password_confirmation": "password123"
  }'
```

### 3. Login (Save the token from response!)
```bash
curl -X POST http://127.0.0.1:8000/api/v1/login \
  -H "Content-Type: application/json" \
  -H "X-Tenant: demo" \
  -d '{
    "email": "admin@demo.com",
    "password": "password123"
  }'
```

### 4. Create a Product (Replace YOUR_TOKEN)
```bash
curl -X POST http://127.0.0.1:8000/api/v1/products \
  -H "Content-Type: application/json" \
  -H "X-Tenant: demo" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "name": "iPhone 15",
    "sku": "IPH-15-001",
    "selling_price": 120000,
    "cost_price": 95000,
    "stock_quantity": 50,
    "reorder_level": 10,
    "track_stock": true,
    "description": "Latest iPhone model"
  }'
```

### 5. List All Products
```bash
curl http://127.0.0.1:8000/api/v1/products \
  -H "X-Tenant: demo" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### 6. View Dashboard
```bash
curl http://127.0.0.1:8000/api/v1/dashboard \
  -H "X-Tenant: demo" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## 🎯 What This System Can Do

- ✅ Multi-tenant (each business has separate database)
- ✅ Product management with stock tracking
- ✅ Customer management
- ✅ Sales processing with automatic stock deduction
- ✅ M-Pesa payment integration ready
- ✅ Inventory management
- ✅ Real-time dashboard
- ✅ Full API authentication with Laravel Sanctum

## 📊 Use a GUI Tool (Recommended)

Instead of curl, use one of these:

### Thunder Client (VS Code Extension)
1. Install "Thunder Client" extension in VS Code
2. Create a new request
3. Set URL: `http://127.0.0.1:8000/api/v1/login`
4. Add header: `X-Tenant: demo`
5. Send request and see formatted JSON response!

### Postman
1. Download from https://www.postman.com/downloads/
2. Import collection from `API.md`
3. Visual interface for testing

## 🔧 Common Issues

**"Connection refused"**
- Server not running → Run: `php artisan serve`

**"Tenant not found"**
- Missing X-Tenant header → Add: `-H "X-Tenant: demo"`

**"Unauthenticated"**
- Missing or expired token → Login again to get new token

## 📁 Project Structure

```
This is a REST API backend that:
- Accepts JSON requests
- Returns JSON responses
- Uses JWT tokens for authentication
- Supports multiple tenants (businesses)
```

## 🎨 Want to See it in a Browser?

The backend is ready! You can:

1. **Build a frontend** (React, Vue, or Angular)
2. **Use Postman** (Visual API testing)
3. **Use Thunder Client** (VS Code extension - easiest!)
4. **Add Laravel Blade views** (traditional Laravel approach)

## 🧪 Run Tests

```bash
php artisan test
```

All 12 tests should pass ✅

## 📖 Full Documentation

- `API.md` - Complete API reference
- `QUICK_START.md` - Detailed setup guide
- `DEPLOYMENT.md` - Production deployment guide
