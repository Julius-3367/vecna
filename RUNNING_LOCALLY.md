# Running Vecna ERP Locally

## Quick Start

The backend is now running! 🚀

**Server URL:** http://localhost:8000

## What You Can Do

### Option 1: Use the Test Script (Recommended)
```bash
./test-api.sh
```
This automated script will:
- Create a demo tenant
- Register and login a user
- Create products
- Create customers
- Process a sale
- Show dashboard stats

### Option 2: Use Postman/Insomnia
1. Import the API endpoints from `API.md`
2. Base URL: `http://localhost:8000/api/v1`
3. Add header: `X-Tenant: your-tenant-subdomain`
4. Add header: `Authorization: Bearer YOUR_TOKEN`

### Option 3: Use curl (Manual Testing)

#### 1. Register a Tenant
```bash
curl -X POST http://localhost:8000/api/register-tenant \
  -H "Content-Type: application/json" \
  -d '{
    "business_name": "My Store",
    "email": "store@example.com",
    "phone": "254712345678",
    "industry": "retail",
    "subdomain": "mystore",
    "admin_name": "Admin User",
    "admin_email": "admin@mystore.com",
    "admin_password": "password123",
    "admin_password_confirmation": "password123"
  }'
```

#### 2. Login
```bash
curl -X POST http://localhost:8000/api/v1/login \
  -H "Content-Type: application/json" \
  -H "X-Tenant: mystore" \
  -d '{
    "email": "admin@mystore.com",
    "password": "password123"
  }'
```

Save the token from the response!

#### 3. Create a Product
```bash
curl -X POST http://localhost:8000/api/v1/products \
  -H "Content-Type: application/json" \
  -H "X-Tenant: mystore" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -d '{
    "name": "Laptop",
    "sku": "LAP-001",
    "selling_price": 50000,
    "cost_price": 35000,
    "stock_quantity": 10,
    "reorder_level": 5,
    "track_stock": true
  }'
```

#### 4. List Products
```bash
curl http://localhost:8000/api/v1/products \
  -H "X-Tenant: mystore" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

#### 5. Create a Customer
```bash
curl -X POST http://localhost:8000/api/v1/customers \
  -H "Content-Type: application/json" \
  -H "X-Tenant: mystore" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "254722123456",
    "customer_type": "individual"
  }'
```

#### 6. Create a Sale
```bash
curl -X POST http://localhost:8000/api/v1/sales \
  -H "Content-Type: application/json" \
  -H "X-Tenant: mystore" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -d '{
    "customer_id": 1,
    "items": [
      {
        "product_id": 1,
        "quantity": 2,
        "unit_price": 50000
      }
    ],
    "payment_method": "cash"
  }'
```

#### 7. View Dashboard
```bash
curl http://localhost:8000/api/v1/dashboard \
  -H "X-Tenant: mystore" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

## Available Endpoints

### Authentication
- POST `/api/register-tenant` - Register new business
- POST `/api/v1/login` - Login user
- POST `/api/v1/logout` - Logout user
- GET `/api/v1/me` - Get current user

### Products
- GET `/api/v1/products` - List products
- POST `/api/v1/products` - Create product
- GET `/api/v1/products/{id}` - Get product details
- PUT `/api/v1/products/{id}` - Update product
- DELETE `/api/v1/products/{id}` - Delete product
- POST `/api/v1/products/{id}/update-stock` - Update stock
- GET `/api/v1/products/low-stock` - Get low stock products

### Sales
- GET `/api/v1/sales` - List sales
- POST `/api/v1/sales` - Create sale
- GET `/api/v1/sales/{id}` - Get sale details
- POST `/api/v1/sales/{id}/complete` - Complete sale
- POST `/api/v1/sales/{id}/cancel` - Cancel sale

### Customers
- GET `/api/v1/customers` - List customers
- POST `/api/v1/customers` - Create customer
- GET `/api/v1/customers/{id}` - Get customer details
- PUT `/api/v1/customers/{id}` - Update customer
- DELETE `/api/v1/customers/{id}` - Delete customer

### Dashboard & Reports
- GET `/api/v1/dashboard` - Dashboard statistics
- GET `/api/v1/inventory/stock-value` - Inventory value
- GET `/api/v1/inventory/movements` - Stock movements

## Tools You Can Use

### 1. **Thunder Client** (VS Code Extension)
- Install: Search "Thunder Client" in VS Code Extensions
- Create collections for your API requests
- Visual and integrated in VS Code

### 2. **Postman** (Desktop App)
- Download: https://www.postman.com/downloads/
- Import API collection
- Test and organize requests

### 3. **Insomnia** (Desktop App)
- Download: https://insomnia.rest/download
- Lightweight alternative to Postman

### 4. **HTTPie** (Command Line)
```bash
# Install
pip install httpie

# Use (prettier than curl)
http POST http://localhost:8000/api/v1/login \
  X-Tenant:mystore \
  email=admin@mystore.com \
  password=password123
```

## Important Headers

Every tenant API request needs:
```
X-Tenant: your-subdomain
Authorization: Bearer your-token-here
Content-Type: application/json
```

## Database

- **Central DB**: SQLite (stores tenants, plans, subscriptions)
- **Tenant DBs**: PostgreSQL (one database per tenant with prefix `tenant_`)

View tenants:
```bash
php artisan tinker
>>> \App\Models\Tenant::all()
```

## Stopping the Server

Press `Ctrl+C` in the terminal running the server

## Troubleshooting

### "Tenant not found" error
- Make sure you're using the correct subdomain in the `X-Tenant` header
- Check that the tenant exists: `php artisan tenants:list`

### "Unauthenticated" error
- Your token may have expired
- Login again to get a new token
- Make sure the `Authorization: Bearer` header is set correctly

### Database errors
- Run migrations: `php artisan migrate`
- For tenant migrations: `php artisan tenants:migrate`

## Next Steps

Want to build a frontend? You can:
1. **React/Vue SPA**: Use the API endpoints
2. **Mobile App**: Flutter/React Native with API integration
3. **Livewire/Inertia**: Add Laravel frontend

See `API.md` for complete API documentation.
