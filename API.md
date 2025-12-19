# Vecna ERP - API Documentation

## Base URL

**Production**: `https://api.vecna.co.ke`  
**Staging**: `https://api-staging.vecna.co.ke`  
**Local**: `http://vecna.test`

## Authentication

All API requests require authentication via JWT tokens or Laravel Sanctum tokens.

### Register New Tenant

Creates a new tenant account with trial subscription.

**Endpoint**: `POST /api/register`

**Request Body**:

```json
{
  "business_name": "My Shop",
  "email": "owner@myshop.com",
  "phone": "+254700000000",
  "industry": "retail",
  "password": "SecurePassword123!",
  "password_confirmation": "SecurePassword123!"
}
```

**Response** (201 Created):

```json
{
  "message": "Registration successful",
  "tenant": {
    "id": "abc123-xyz789",
    "business_name": "My Shop",
    "subdomain": "myshop",
    "email": "owner@myshop.com",
    "status": "trial",
    "trial_ends_at": "2026-01-18T10:00:00Z"
  },
  "domain": "myshop.vecna.co.ke",
  "credentials": {
    "email": "owner@myshop.com",
    "temp_password": "welcome123"
  }
}
```

### Login

Authenticates user and returns access token.

**Endpoint**: `POST /api/login`

**Request Body**:

```json
{
  "email": "owner@myshop.com",
  "password": "SecurePassword123!"
}
```

**Response** (200 OK):

```json
{
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "token_type": "Bearer",
  "expires_in": 3600,
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "owner@myshop.com",
    "phone": "+254700000000"
  },
  "tenant": {
    "id": "abc123",
    "business_name": "My Shop",
    "status": "active"
  }
}
```

### Using Token

Include token in Authorization header for all subsequent requests:

```
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
```

---

## Products API

### List Products

**Endpoint**: `GET /api/products`

**Query Parameters**:

- `page` (int): Page number (default: 1)
- `per_page` (int): Items per page (default: 20, max: 100)
- `search` (string): Search by name, SKU, or barcode
- `category_id` (int): Filter by category
- `is_active` (boolean): Filter active/inactive
- `low_stock` (boolean): Show only low stock items
- `sort` (string): Sort field (e.g., `-created_at` for descending)

**Example Request**:

```bash
GET /api/products?search=phone&category_id=5&per_page=50&sort=-created_at
```

**Response** (200 OK):

```json
{
  "data": [
    {
      "id": 1,
      "name": "Samsung Galaxy A54",
      "sku": "SAM-A54-BLK",
      "barcode": "8806094933024",
      "category": {
        "id": 5,
        "name": "Smartphones"
      },
      "brand": {
        "id": 2,
        "name": "Samsung"
      },
      "cost_price": 38000.00,
      "selling_price": 45000.00,
      "profit_margin": 18.42,
      "stock_quantity": 25,
      "reorder_level": 5,
      "is_low_stock": false,
      "is_active": true,
      "image": "https://cdn.vecna.co.ke/products/sam-a54.jpg",
      "created_at": "2025-12-15T10:30:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 150,
    "last_page": 8
  },
  "links": {
    "first": "/api/products?page=1",
    "last": "/api/products?page=8",
    "prev": null,
    "next": "/api/products?page=2"
  }
}
```

### Get Product

**Endpoint**: `GET /api/products/{id}`

**Response** (200 OK):

```json
{
  "data": {
    "id": 1,
    "name": "Samsung Galaxy A54",
    "slug": "samsung-galaxy-a54",
    "description": "6.4\" Super AMOLED, 128GB, 6GB RAM",
    "sku": "SAM-A54-BLK",
    "barcode": "8806094933024",
    "category": {...},
    "brand": {...},
    "cost_price": 38000.00,
    "selling_price": 45000.00,
    "wholesale_price": 42000.00,
    "tax_rate": 16.00,
    "stock_quantity": 25,
    "stock_locations": [
      {
        "location_id": 1,
        "location_name": "Nairobi HQ",
        "quantity": 15,
        "available_quantity": 12
      },
      {
        "location_id": 2,
        "location_name": "Mombasa Branch",
        "quantity": 10,
        "available_quantity": 10
      }
    ],
    "recent_movements": [...],
    "is_active": true
  }
}
```

### Create Product

**Endpoint**: `POST /api/products`

**Request Body**:

```json
{
  "name": "iPhone 14 Pro",
  "sku": "APPL-14PRO-128",
  "barcode": "0194253406846",
  "category_id": 5,
  "brand_id": 1,
  "unit_id": 1,
  "cost_price": 120000.00,
  "selling_price": 145000.00,
  "wholesale_price": 135000.00,
  "tax_rate": 16.00,
  "track_stock": true,
  "stock_quantity": 10,
  "reorder_level": 3,
  "description": "6.1\" Super Retina XDR, 128GB",
  "is_active": true
}
```

**Response** (201 Created):

```json
{
  "message": "Product created successfully",
  "data": {
    "id": 152,
    "name": "iPhone 14 Pro",
    "sku": "APPL-14PRO-128",
    ...
  }
}
```

### Update Product

**Endpoint**: `PUT /api/products/{id}`

**Request Body**: Same as create

**Response** (200 OK)

### Delete Product

**Endpoint**: `DELETE /api/products/{id}`

**Response** (204 No Content)

### Update Stock

**Endpoint**: `PATCH /api/products/{id}/stock`

**Request Body**:

```json
{
  "location_id": 1,
  "quantity": 5,
  "type": "adjustment",
  "notes": "Stock count correction"
}
```

**Types**: `purchase`, `sale`, `adjustment`, `transfer_in`, `transfer_out`, `damage`, `expired`

**Response** (200 OK):

```json
{
  "message": "Stock updated successfully",
  "product": {
    "id": 1,
    "name": "Samsung Galaxy A54",
    "stock_quantity": 30,
    "stock_at_location": 20
  }
}
```

---

## Sales API

### Create Sale

**Endpoint**: `POST /api/sales`

**Request Body**:

```json
{
  "customer_id": 123,
  "location_id": 1,
  "sale_date": "2025-12-19T14:30:00Z",
  "channel": "pos",
  "items": [
    {
      "product_id": 1,
      "quantity": 2,
      "unit_price": 45000.00
    },
    {
      "product_id": 5,
      "quantity": 1,
      "unit_price": 15000.00
    }
  ],
  "discount_type": "percentage",
  "discount_value": 5,
  "payment_method": "mpesa",
  "mpesa_receipt": "QGX7H2M9K1",
  "notes": "Customer requested gift wrapping"
}
```

**Response** (201 Created):

```json
{
  "message": "Sale created successfully",
  "data": {
    "id": 5678,
    "sale_number": "SAL-20251219-0042",
    "customer": {
      "id": 123,
      "name": "Jane Doe",
      "phone": "+254722222222"
    },
    "subtotal": 105000.00,
    "tax_amount": 16800.00,
    "discount_amount": 5250.00,
    "total_amount": 116550.00,
    "paid_amount": 116550.00,
    "balance_amount": 0.00,
    "payment_status": "paid",
    "status": "confirmed",
    "items": [
      {
        "product_id": 1,
        "product_name": "Samsung Galaxy A54",
        "quantity": 2,
        "unit_price": 45000.00,
        "line_total": 90000.00
      },
      {
        "product_id": 5,
        "product_name": "Phone Case",
        "quantity": 1,
        "unit_price": 15000.00,
        "line_total": 15000.00
      }
    ],
    "created_at": "2025-12-19T14:30:00Z"
  }
}
```

### List Sales

**Endpoint**: `GET /api/sales`

**Query Parameters**:

- `start_date` (date): Filter from date
- `end_date` (date): Filter to date
- `customer_id` (int): Filter by customer
- `location_id` (int): Filter by location
- `status` (string): Filter by status
- `payment_status` (string): Filter by payment status

**Example**:

```bash
GET /api/sales?start_date=2025-12-01&end_date=2025-12-19&status=confirmed
```

### Get Sale

**Endpoint**: `GET /api/sales/{id}`

### Record Payment

**Endpoint**: `POST /api/sales/{id}/payments`

**Request Body**:

```json
{
  "amount": 50000.00,
  "method": "mpesa",
  "mpesa_receipt": "QGX7H2M9K1",
  "payment_date": "2025-12-19T15:00:00Z",
  "notes": "Partial payment"
}
```

---

## M-Pesa API

### Initiate STK Push

**Endpoint**: `POST /api/mpesa/stk-push`

**Request Body**:

```json
{
  "phone": "254712345678",
  "amount": 5000,
  "account_reference": "INV-001",
  "description": "Payment for invoice #001"
}
```

**Response** (200 OK):

```json
{
  "message": "STK push initiated",
  "merchant_request_id": "29115-34620561-1",
  "checkout_request_id": "ws_CO_191220191020363925",
  "response_code": "0",
  "response_description": "Success. Request accepted for processing",
  "customer_message": "Success. Request accepted for processing"
}
```

### Check Transaction Status

**Endpoint**: `GET /api/mpesa/query/{checkout_request_id}`

### Reconcile Transactions

**Endpoint**: `GET /api/mpesa/reconcile`

**Query Parameters**:

- `start_date` (date)
- `end_date` (date)
- `is_reconciled` (boolean)

**Response**:

```json
{
  "data": [
    {
      "id": 1,
      "trans_id": "QGX7H2M9K1",
      "trans_amount": 5000.00,
      "msisdn": "254712345678",
      "first_name": "JOHN",
      "last_name": "DOE",
      "trans_time": "20251219143025",
      "is_reconciled": false,
      "created_at": "2025-12-19T14:30:25Z"
    }
  ],
  "summary": {
    "total_transactions": 150,
    "total_amount": 450000.00,
    "reconciled_count": 120,
    "unreconciled_count": 30
  }
}
```

### Manual Reconciliation

**Endpoint**: `POST /api/mpesa/reconcile/{transaction_id}`

**Request Body**:

```json
{
  "reconciled_type": "Sale",
  "reconciled_id": 5678
}
```

---

## Reports API

### Sales Report

**Endpoint**: `GET /api/reports/sales`

**Query Parameters**:

- `start_date` (required)
- `end_date` (required)
- `location_id` (optional)
- `user_id` (optional)
- `group_by` (optional): `day`, `week`, `month`, `product`, `category`

**Response**:

```json
{
  "period": {
    "start_date": "2025-12-01",
    "end_date": "2025-12-19"
  },
  "summary": {
    "total_sales": 2500000.00,
    "total_transactions": 450,
    "average_transaction": 5555.56,
    "total_profit": 450000.00,
    "profit_margin": 18.00
  },
  "top_products": [
    {
      "product_id": 1,
      "product_name": "Samsung Galaxy A54",
      "quantity_sold": 45,
      "revenue": 2025000.00
    }
  ],
  "daily_breakdown": [...]
}
```

### Inventory Report

**Endpoint**: `GET /api/reports/inventory`

### Profit & Loss

**Endpoint**: `GET /api/reports/profit-loss`

### Tax Report (KRA VAT)

**Endpoint**: `GET /api/reports/tax`

**Query Parameters**:

- `period` (required): `2025-12` (YYYY-MM format)

**Response**:

```json
{
  "period": "2025-12",
  "period_start": "2025-12-01",
  "period_end": "2025-12-31",
  "sales": {
    "excluding_vat": 2155172.41,
    "vat": 344827.59,
    "including_vat": 2500000.00
  },
  "purchases": {
    "excluding_vat": 1293103.45,
    "vat": 206896.55,
    "including_vat": 1500000.00
  },
  "net_vat": {
    "amount": 137931.04,
    "status": "payable"
  },
  "kra_status": "draft",
  "download_url": "/api/reports/tax/2025-12/download"
}
```

---

## Customers API

### List Customers

**Endpoint**: `GET /api/customers`

### Create Customer

**Endpoint**: `POST /api/customers`

**Request Body**:

```json
{
  "type": "business",
  "name": "Acme Corp",
  "company_name": "Acme Corporation Ltd",
  "email": "info@acme.com",
  "phone": "+254722000000",
  "kra_pin": "A001234567Z",
  "billing_address": "123 Business St, Nairobi",
  "city": "Nairobi",
  "county": "Nairobi",
  "credit_limit": 100000.00,
  "payment_terms": "credit",
  "credit_days": 30
}
```

### Get Customer

**Endpoint**: `GET /api/customers/{id}`

**Response** includes purchase history, outstanding balance, loyalty points.

---

## Error Responses

### Validation Error (422)

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email has already been taken."],
    "phone": ["The phone format is invalid."]
  }
}
```

### Unauthorized (401)

```json
{
  "message": "Unauthenticated."
}
```

### Forbidden (403)

```json
{
  "message": "This action is unauthorized."
}
```

### Not Found (404)

```json
{
  "message": "Resource not found."
}
```

### Server Error (500)

```json
{
  "message": "Server Error",
  "error": "An unexpected error occurred. Please try again later."
}
```

---

## Rate Limiting

- **Standard**: 60 requests per minute per user
- **Login**: 5 requests per minute per IP
- **Registration**: 3 requests per minute per IP

Rate limit headers included in responses:

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
X-RateLimit-Reset: 1671456000
```

---

## Webhooks

### M-Pesa Callback

Vecna automatically handles M-Pesa callbacks at:

```
POST /api/mpesa/callback
```

### Custom Webhooks (Coming Soon)

Configure webhooks in admin panel for events:

- `sale.created`
- `payment.received`
- `product.low_stock`
- `invoice.overdue`

---

## SDK & Libraries

### PHP SDK (Coming Soon)

```php
use Vecna\SDK\VecnaClient;

$client = new VecnaClient('your-api-token');

$products = $client->products()->all();
$sale = $client->sales()->create([...]);
```

### JavaScript SDK (Coming Soon)

```javascript
import Vecna from '@vecna/sdk';

const vecna = new Vecna({ apiToken: 'your-token' });

const products = await vecna.products.list();
const sale = await vecna.sales.create({...});
```

---

## Support

- **Email**: api@vecna.co.ke
- **Docs**: https://docs.vecna.co.ke
- **Status**: https://status.vecna.co.ke

**API Version**: v1  
**Last Updated**: December 19, 2025
