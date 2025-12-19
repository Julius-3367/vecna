# Vecna ERP - Multi-Tenant Business Management Platform

> Custom-built SaaS ERP for Kenyan SMEs with unlimited companies, inventory tracking, KRA VAT compliance, HR payroll, CRM, POS sync, and M-Pesa integration.

## 🎯 Overview

Vecna is a comprehensive multi-tenant ERP platform built specifically for Kenyan entrepreneurs and SMEs. Unlike generic ERPs, Vecna emphasizes Kenya-specific integrations including:

- **M-Pesa/STK Push** - Native Daraja API integration
- **KRA iTax Compliance** - Automated VAT reports and filing
- **Multi-Branch Operations** - Real-time sync across locations
- **POS Hardware Integration** - Thermal printers, barcode scanners
- **Mobile-First Design** - React Native apps for iOS/Android
- **Supplier Network** - B2B marketplace with network effects

## 🏗️ Architecture

### Tech Stack

- **Backend**: Laravel 11 (PHP 8.2+)
- **Database**: PostgreSQL 15+ with tenant isolation
- **Multi-Tenancy**: stancl/tenancy (database per tenant)
- **Frontend**: Livewire 3 + Alpine.js
- **Mobile**: React Native 0.72+
- **Admin Panel**: Filament 3
- **Queue**: Laravel Horizon + Redis
- **Real-time**: Laravel Reverb / Pusher
- **Storage**: AWS S3
- **Cache**: Redis 7+

### Multi-Tenancy Strategy

Each tenant (company) gets:
- Isolated PostgreSQL schema
- Unique subdomain (e.g., `shop.vecna.co.ke`)
- Separate file storage namespace
- Independent settings and configurations

```
Central Database (vecna_central)
├── tenants table
├── plans & subscriptions
└── global admin data

Tenant Databases (auto-created)
├── tenant_abc123
│   ├── users
│   ├── products
│   ├── sales
│   └── ...
└── tenant_xyz789
    └── (same schema, different data)
```

## 🚀 Quick Start

### Prerequisites

```bash
- PHP 8.2+
- PostgreSQL 15+
- Redis 7+
- Composer 2.x
- Node.js 18+
- npm/yarn
```

### Installation

```bash
# Clone repository
git clone https://github.com/Julius-3367/vecna.git
cd vecna

# Install PHP dependencies
composer install

# Install JavaScript dependencies
npm install

# Setup environment
cp .env.example .env
php artisan key:generate

# Configure database in .env
DB_CONNECTION=pgsql
DB_DATABASE=vecna_central
DB_USERNAME=postgres
DB_PASSWORD=your_password

# Run migrations
php artisan migrate

# Install tenancy
php artisan tenancy:install

# Seed demo data (optional)
php artisan db:seed --class=DemoSeeder

# Build assets
npm run build

# Start development server
php artisan serve

# In separate terminal, start queue worker
php artisan horizon

# In another terminal, start WebSocket server
php artisan reverb:start
```

### Create Your First Tenant

```bash
# Via Artisan command
php artisan tenants:create shop1 --domain=shop1.vecna.test

# Or via registration API
POST /api/register
{
  "business_name": "My Shop",
  "email": "owner@myshop.com",
  "phone": "+254712345678",
  "industry": "retail"
}
```

## 📦 Core Modules

### 1. Inventory Management
- Multi-location stock tracking
- Barcode scanning (mobile app)
- Low-stock alerts & notifications
- AI-powered reorder predictions
- Photo-based catalog setup (OCR)

### 2. Point of Sale (POS)
- Real-time WebSocket sync
- Hardware integration (printers, scanners)
- Offline mode with auto-sync
- Receipt generation
- Cash drawer management

### 3. Accounting & Compliance
- Automated invoicing
- M-Pesa reconciliation
- KRA iTax/VAT reports
- P&L and balance sheets
- Multi-currency support
- Cash flow forecasting

### 4. HR & Payroll
- Employee self-service portals
- Leave management workflows
- Automated payslips (NHIF/NSSF)
- Performance tracking
- Shift scheduling

### 5. CRM & Projects
- Lead pipeline (Kanban boards)
- Customer portals
- WhatsApp integration
- Job cards for services
- Gantt charts

### 6. Supplier Portal (Network Effects)
- Free supplier accounts
- Real-time order management
- B2B marketplace
- Procurement analytics
- Bulk ordering discounts

## 🔌 API Documentation

### Authentication

```bash
# Register new tenant
POST /api/register
Content-Type: application/json

{
  "business_name": "Shop ABC",
  "email": "owner@shopabc.com",
  "phone": "+254700000000",
  "industry": "retail",
  "password": "securepassword"
}

# Login
POST /api/login
{
  "email": "owner@shopabc.com",
  "password": "securepassword"
}

# Response
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "user": {...},
  "tenant": {...}
}

# Use token in subsequent requests
Authorization: Bearer {token}
```

### Inventory APIs

```bash
# List products
GET /api/products?filter[search]=phone&sort=-created_at

# Create product
POST /api/products
{
  "name": "Samsung Galaxy A54",
  "sku": "SAM-A54-BLK",
  "barcode": "8806094933024",
  "category_id": 1,
  "price": 45000,
  "cost": 38000,
  "stock": 25,
  "reorder_level": 5,
  "locations": [
    {"location_id": 1, "quantity": 15},
    {"location_id": 2, "quantity": 10}
  ]
}

# Update stock
PATCH /api/products/{id}/stock
{
  "location_id": 1,
  "quantity": 5,
  "type": "sale|purchase|adjustment",
  "notes": "Sold to customer"
}
```

### Sales & POS

```bash
# Create sale
POST /api/sales
{
  "customer_id": 123,
  "items": [
    {"product_id": 1, "quantity": 2, "price": 45000},
    {"product_id": 5, "quantity": 1, "price": 15000}
  ],
  "payment_method": "mpesa",
  "mpesa_receipt": "QGX7H2M9K1",
  "location_id": 1
}

# Get daily sales report
GET /api/reports/sales/daily?date=2025-12-19&location_id=1
```

### M-Pesa Integration

```bash
# Initiate STK Push
POST /api/mpesa/stk-push
{
  "phone": "254712345678",
  "amount": 5000,
  "account_reference": "INV-001",
  "description": "Payment for invoice #001"
}

# Reconcile transactions
GET /api/mpesa/reconcile?start_date=2025-12-01&end_date=2025-12-19

# M-Pesa callback (webhook)
POST /api/mpesa/callback
# Automatically handled by system
```

## 🎨 Admin Panel

Access super admin panel at `/admin` (after seeding admin user)

Features:
- Tenant management & monitoring
- Subscription & billing
- Platform analytics
- Support tools
- Module toggles
- System health monitoring

```bash
# Create admin user
php artisan make:filament-user

# Access at
http://localhost/admin
```

## 🔐 Security Features

- **Multi-tenant data isolation** - PostgreSQL schemas prevent cross-tenant access
- **JWT authentication** - Secure token-based auth
- **2FA via SMS** - Optional two-factor authentication
- **Encryption** - AES-256 at rest, TLS 1.3 in transit
- **Audit logs** - Comprehensive activity tracking
- **Rate limiting** - API throttling
- **CSRF protection** - Laravel security features
- **XSS prevention** - Input sanitization

## 📊 Pricing Tiers

| Tier | Price (KES/month) | Shops | Users | Transactions |
|------|------------------|-------|-------|--------------|
| **Starter** | Free | 1 | 3 | 50/month |
| **Business** | 6,000 | 3 | 15 | 500/month |
| **Professional** | 12,000 | 10 | 50 | 2,000/month |
| **Enterprise** | 25,000+ | Unlimited | Unlimited | Unlimited |

### Add-ons
- Transaction overage: KES 5 per transaction
- Extra locations: KES 1,500/month
- Extra users: KES 500/month
- POS Hardware Bundle: KES 3,000/month

## 🧪 Testing

```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --filter=InventoryTest

# Run with coverage
php artisan test --coverage

# Run Pint (code style)
./vendor/bin/pint
```

## 📱 Mobile Apps

React Native apps in `/mobile` directory (separate repository)

```bash
# Clone mobile repo
git clone https://github.com/Julius-3367/vecna-mobile.git

# Configure API endpoint
# Edit mobile/.env
API_URL=https://api.vecna.co.ke

# Run iOS
cd mobile
npm install
npx pod-install
npm run ios

# Run Android
npm run android
```

## 🚢 Deployment

### Production Setup

```bash
# Setup production server (Ubuntu 22.04)
# Install dependencies
sudo apt update
sudo apt install -y php8.2 php8.2-fpm php8.2-pgsql php8.2-redis nginx postgresql redis

# Clone and setup
git clone https://github.com/Julius-3367/vecna.git /var/www/vecna
cd /var/www/vecna
composer install --optimize-autoloader --no-dev
npm install && npm run build

# Environment
cp .env.example .env
# Edit .env for production settings
php artisan key:generate
php artisan migrate --force
php artisan storage:link

# Permissions
sudo chown -R www-data:www-data /var/www/vecna
sudo chmod -R 755 /var/www/vecna/storage

# Setup Horizon as systemd service
sudo cp deployment/horizon.service /etc/systemd/system/
sudo systemctl enable horizon
sudo systemctl start horizon

# Configure Nginx
sudo cp deployment/nginx.conf /etc/nginx/sites-available/vecna
sudo ln -s /etc/nginx/sites-available/vecna /etc/nginx/sites-enabled/
sudo systemctl restart nginx

# SSL with Let's Encrypt
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d vecna.co.ke -d *.vecna.co.ke
```

### Docker Deployment

```bash
# Build and run with Docker Compose
docker-compose up -d

# Run migrations
docker-compose exec app php artisan migrate

# Scale workers
docker-compose up -d --scale worker=3
```

## 📈 Monitoring & Maintenance

```bash
# Check Horizon status
php artisan horizon:status

# Clear caches
php artisan optimize:clear

# Run backups
php artisan backup:run

# Monitor logs
tail -f storage/logs/laravel.log

# Database maintenance
php artisan tenants:run migrate
```

## 🤝 Contributing

This is a proprietary project. For contribution guidelines, contact the development team.

## 📄 License

Proprietary - All rights reserved by Vecna Technologies Limited

## 📞 Support

- **Email**: support@vecna.co.ke
- **Phone**: +254 700 000 000
- **Docs**: https://docs.vecna.co.ke
- **Status**: https://status.vecna.co.ke

## 🎯 Roadmap

### Phase 1 (Months 1-4) - MVP
- [x] Multi-tenant architecture
- [x] Basic inventory management
- [x] POS integration
- [x] M-Pesa reconciliation
- [ ] Beta testing with 20 users

### Phase 2 (Months 5-8) - Revenue Engine
- [ ] Mobile apps launch
- [ ] HR module
- [ ] WhatsApp integration
- [ ] Freemium tier
- [ ] 100 paying customers

### Phase 3 (Months 9-12) - Scale
- [ ] Supplier portal
- [ ] AI features
- [ ] Loyalty program
- [ ] 200 tenants

### Phase 4 (Year 2) - Market Leadership
- [ ] Marketplace for modules
- [ ] Manufacturing module
- [ ] Regional expansion
- [ ] 500+ tenants

---

**Built with ❤️ for Kenyan entrepreneurs**

*Empowering SMEs to compete in the digital economy*
