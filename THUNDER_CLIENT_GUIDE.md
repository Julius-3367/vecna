# 🚀 Quick Start - Testing with Thunder Client

## ✅ Setup Complete!

Thunder Client is now installed in VS Code with a pre-configured API collection.

## 📋 Step-by-Step Guide

### 1. Start the Server
```bash
php artisan serve
```
Leave this terminal running.

### 2. Open Thunder Client
1. Look for the **Thunder Client** icon in VS Code's left sidebar (⚡ lightning bolt)
2. Click it to open the Thunder Client panel

### 3. You'll See Pre-Configured Requests Organized in Folders:

```
Vecna ERP API
├── Health Check
├── 1. Authentication
│   ├── Register Tenant
│   ├── Login
│   ├── Get Current User
│   └── Logout
├── 2. Products
│   ├── Create Product
│   ├── List Products
│   ├── Get Product Details
│   ├── Update Product Stock
│   └── Get Low Stock Products
├── 3. Customers
│   ├── Create Customer
│   └── List Customers
├── 4. Sales
│   ├── Create Sale
│   └── List Sales
└── 5. Dashboard & Reports
    ├── Dashboard Stats
    └── Inventory Value
```

## 🎯 Your First Test (Follow These Steps)

### Step 1: Health Check
1. Click **"Health Check"** request
2. Click the **"Send"** button
3. You should see: `{"status":"ok"}` ✅

### Step 2: Register Tenant
1. Click **"1. Authentication" → "Register Tenant"**
2. Review the JSON body (already filled in):
   ```json
   {
     "business_name": "Demo Store",
     "subdomain": "demo",
     ...
   }
   ```
3. Click **"Send"**
4. You'll see a response with tenant details and a token! 🎉

**Note:** If you get an error saying tenant exists, that's fine - skip to Step 3.

### Step 3: Login
1. Click **"Login"** request
2. Click **"Send"**
3. Look for the `token` in the response
4. **COPY THE TOKEN** (it's a long string after `"token":`)

### Step 4: Set Your Token
1. In Thunder Client, look for **"Env"** tab at the top
2. Click it
3. Replace `paste-your-token-here-after-login` with your actual token
4. Click **Save**

**OR** you can manually paste the token in each request's Authorization header.

### Step 5: Create a Product
1. Click **"2. Products" → "Create Product"**
2. Notice the headers include:
   - `X-Tenant: demo`
   - `Authorization: Bearer {{token}}`
3. Click **"Send"**
4. You'll see the created product with ID! 📦

### Step 6: List Products
1. Click **"List Products"**
2. Click **"Send"**
3. See your products in a nice JSON format! 📋

### Step 7: Create a Customer
1. Click **"3. Customers" → "Create Customer"**
2. Click **"Send"**
3. Customer created! 👤

### Step 8: Create a Sale
1. Click **"4. Sales" → "Create Sale"**
2. Review the JSON - it references product_id: 1 and customer_id: 1
3. Click **"Send"**
4. Sale created! Stock automatically reduced! 💰

### Step 9: View Dashboard
1. Click **"5. Dashboard & Reports" → "Dashboard Stats"**
2. Click **"Send"**
3. See real-time business statistics! 📊

## 🎨 Thunder Client Features

### Response View
- **Pretty** - Formatted JSON (easiest to read)
- **Raw** - Original response
- **Headers** - Response headers
- **Cookies** - Session cookies

### Request Body
- Already configured as **JSON** for POST requests
- You can edit any values before sending

### Query Parameters
- Click **"Query"** tab to add filters like `?per_page=20`

## 🔧 Common Adjustments

### Change Product Details
In "Create Product" request, edit:
```json
{
  "name": "Your Product Name",
  "selling_price": 50000,
  "stock_quantity": 100
}
```

### Change Tenant Subdomain
If you want to test with a different tenant:
1. Update `subdomain` in "Register Tenant"
2. Update `X-Tenant` header in all other requests

### Add More Items to Sale
In "Create Sale", edit the items array:
```json
{
  "items": [
    {"product_id": 1, "quantity": 2, "unit_price": 150000},
    {"product_id": 2, "quantity": 1, "unit_price": 50000}
  ]
}
```

## 🐛 Troubleshooting

### "Failed to connect"
- Server not running → Run `php artisan serve`
- Wrong URL → Should be `http://localhost:8000`

### "Tenant not found"
- Check `X-Tenant` header matches your subdomain
- Make sure you registered the tenant first

### "Unauthenticated"
- Token not set or expired
- Login again and update the token in Env or headers

### Can't see Thunder Client icon
- Press `Ctrl+Shift+P` (or `Cmd+Shift+P` on Mac)
- Type "Thunder Client"
- Click "Thunder Client: Open"

## 🎓 Next Steps

### Test the Full Workflow
1. Register tenant ✅
2. Login and get token ✅
3. Create 5 products 📦
4. Create 3 customers 👥
5. Process 10 sales 💰
6. Check dashboard stats 📊
7. View inventory value 💵

### Explore More Endpoints
Check `API.md` for 110+ available endpoints:
- Inventory management
- Stock transfers
- M-Pesa payments
- Reports and analytics
- And much more!

### Build a Frontend
Now that you can see the API working:
- Build a React/Vue dashboard
- Create a mobile app with Flutter
- Add Livewire components to Laravel

## 📖 Resources

- **Thunder Client Docs**: https://www.thunderclient.com/
- **API Reference**: See `API.md` in project root
- **Full Guide**: See `RUNNING_LOCALLY.md`

---

**You're all set! Start clicking "Send" and watch the magic happen! ✨**
