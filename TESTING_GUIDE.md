# Vecna ERP - Testing Guide

## Overview
This guide provides comprehensive testing procedures for the Vecna ERP system before production deployment.

## Prerequisites

### System Requirements
- PHP 8.2 or higher
- PostgreSQL 15 or higher
- Redis 7 or higher
- Composer 2.x
- Node.js 18+ (for frontend assets)

### Environment Setup
1. Copy `.env.example` to `.env`
2. Configure database connection
3. Set up Redis connection
4. Add API credentials (M-Pesa, KRA, SMS)

## Initial Setup

### 1. Install Dependencies
```bash
composer install
npm install
```

### 2. Generate Application Key
```bash
php artisan key:generate
```

### 3. Run Migrations
```bash
# Run central database migrations
php artisan migrate

# Run tenant migrations
php artisan tenants:migrate
```

### 4. Seed Demo Data
```bash
php artisan db:seed --class=DemoDataSeeder
```

This creates:
- Demo tenant: `demo.vecna.test`
- Admin user: `admin@demo.test` / `password`
- Manager user: `manager@demo.test` / `password`
- 5 sample products
- 4 customers (2 B2B, 2 B2C)
- 2 suppliers
- 6 expense categories

## Running Tests

### Run All Tests
```bash
php artisan test
```

### Run Specific Test Suites
```bash
# Authentication tests
php artisan test --filter AuthenticationTest

# Product management tests
php artisan test --filter ProductManagementTest

# Sales processing tests
php artisan test --filter SalesProcessingTest
```

### Test Coverage
```bash
php artisan test --coverage
```

## Manual Testing Procedures

### 1. Authentication Flow

#### Register New Tenant
```bash
POST /api/register
{
    "name": "Test User",
    "email": "test@example.com",
    "password": "password123",
    "password_confirmation": "password123",
    "phone": "254712345678",
    "business_name": "Test Business Ltd"
}
```

Expected: 201 response with user data and token

#### Login
```bash
POST /api/login
{
    "email": "admin@demo.test",
    "password": "password"
}
```

Expected: 200 response with user and token

#### Get Profile
```bash
GET /api/user
Authorization: Bearer {token}
```

Expected: 200 response with user details

### 2. Product Management

#### Create Product
```bash
POST /api/products
Authorization: Bearer {token}
{
    "name": "Test Product",
    "sku": "TEST-001",
    "category_id": 1,
    "brand_id": 1,
    "unit_id": 1,
    "cost_price": 1000,
    "selling_price": 1500,
    "current_stock": 100,
    "minimum_stock": 10,
    "track_stock": true
}
```

Expected: 201 response with product data

#### List Products
```bash
GET /api/products
Authorization: Bearer {token}
```

Expected: 200 response with paginated products

#### Update Stock
```bash
POST /api/products/{id}/stock
Authorization: Bearer {token}
{
    "quantity": 50,
    "type": "in",
    "reference": "Stock purchase"
}
```

Expected: 200 response, stock increased

#### Check Low Stock
```bash
GET /api/products/low-stock
Authorization: Bearer {token}
```

Expected: 200 response with products below minimum

### 3. Sales Processing

#### Create Sale (Cash)
```bash
POST /api/sales
Authorization: Bearer {token}
{
    "customer_id": 1,
    "items": [
        {
            "product_id": 1,
            "quantity": 2,
            "unit_price": 1500
        }
    ],
    "payment_method": "cash",
    "notes": "Walk-in customer"
}
```

Expected: 201 response, sale created, stock reduced

#### Create Sale (M-Pesa)
```bash
POST /api/sales
Authorization: Bearer {token}
{
    "customer_id": 1,
    "items": [
        {
            "product_id": 1,
            "quantity": 1,
            "unit_price": 1500
        }
    ],
    "payment_method": "mpesa",
    "phone": "254712345678"
}
```

Expected: 201 response, STK Push initiated

#### List Sales
```bash
GET /api/sales
Authorization: Bearer {token}
```

Expected: 200 response with paginated sales

#### Get Sale Details
```bash
GET /api/sales/{id}
Authorization: Bearer {token}
```

Expected: 200 response with sale and items

### 4. Customer Management

#### Create Customer
```bash
POST /api/customers
Authorization: Bearer {token}
{
    "name": "Test Customer",
    "email": "customer@test.com",
    "phone": "254712345678",
    "customer_type": "b2c"
}
```

Expected: 201 response

#### Get Customer Statement
```bash
GET /api/customers/{id}/statement
Authorization: Bearer {token}
```

Expected: 200 response with transaction history

### 5. Inventory Management

#### Create Stock Transfer
```bash
POST /api/inventory/transfers
Authorization: Bearer {token}
{
    "from_location_id": 1,
    "to_location_id": 2,
    "items": [
        {
            "product_id": 1,
            "quantity": 10
        }
    ],
    "notes": "Replenishment"
}
```

Expected: 201 response, stock moved

#### Create Stock Adjustment
```bash
POST /api/inventory/adjustments
Authorization: Bearer {token}
{
    "location_id": 1,
    "items": [
        {
            "product_id": 1,
            "quantity_before": 100,
            "quantity_counted": 95,
            "reason": "Damaged goods"
        }
    ],
    "adjustment_type": "loss"
}
```

Expected: 201 response, stock adjusted

### 6. Reporting

#### Sales Summary
```bash
GET /api/reports/sales-summary?start_date=2024-01-01&end_date=2024-12-31
Authorization: Bearer {token}
```

Expected: 200 response with sales metrics

#### Inventory Report
```bash
GET /api/reports/inventory
Authorization: Bearer {token}
```

Expected: 200 response with stock levels

#### Profit Analysis
```bash
GET /api/reports/profit-analysis?period=month
Authorization: Bearer {token}
```

Expected: 200 response with profit metrics

### 7. M-Pesa Integration

#### Initiate STK Push
```bash
POST /api/mpesa/stk-push
Authorization: Bearer {token}
{
    "phone": "254712345678",
    "amount": 1500,
    "reference": "SALE-001"
}
```

Expected: 200 response with checkout request

#### Check Transaction Status
```bash
GET /api/mpesa/transactions/{id}
Authorization: Bearer {token}
```

Expected: 200 response with status

## Integration Testing

### M-Pesa Sandbox
1. Set sandbox credentials in `.env`
2. Use test phone number: `254708374149`
3. Test PIN: `1234`
4. Verify callback handling

### KRA iTax Testing
1. Configure sandbox credentials
2. Test PIN validation
3. Test invoice submission
4. Verify tax record storage

### SMS Testing
1. Configure Africa's Talking sandbox
2. Test receipt sending
3. Test low stock alerts
4. Verify delivery reports

## Performance Testing

### Load Testing
```bash
# Install Apache Bench
sudo apt install apache2-utils

# Test API endpoint
ab -n 1000 -c 10 -H "Authorization: Bearer {token}" http://localhost/api/products
```

### Database Performance
```bash
# Monitor slow queries
php artisan telescope:install
php artisan migrate
```

## Security Testing

### Authentication
- [ ] Verify token expiration
- [ ] Test invalid credentials
- [ ] Test password reset flow
- [ ] Verify role-based access

### Authorization
- [ ] Test tenant isolation
- [ ] Verify role permissions
- [ ] Test cross-tenant access prevention

### Data Validation
- [ ] Test SQL injection prevention
- [ ] Test XSS prevention
- [ ] Verify input sanitization

## Production Readiness Checklist

### Configuration
- [ ] Environment variables set
- [ ] Database configured
- [ ] Redis configured
- [ ] Queue workers running
- [ ] Scheduler configured

### Security
- [ ] SSL certificates installed
- [ ] API rate limiting enabled
- [ ] CORS configured
- [ ] Security headers set

### Monitoring
- [ ] Error tracking (Sentry)
- [ ] Performance monitoring
- [ ] Log aggregation
- [ ] Backup automation

### Documentation
- [ ] API documentation updated
- [ ] Deployment guide reviewed
- [ ] User manual created
- [ ] Admin guide created

## Common Issues and Solutions

### Issue: Migration Fails
**Solution**: Check database connection, ensure PostgreSQL is running
```bash
php artisan config:clear
php artisan migrate:fresh
```

### Issue: Tests Fail
**Solution**: Ensure test database is configured
```bash
cp .env.example .env.testing
php artisan config:clear
php artisan test
```

### Issue: M-Pesa Callback Not Received
**Solution**: Verify callback URL is publicly accessible, check ngrok or tunnel setup

### Issue: Stock Not Updating
**Solution**: Check queue workers are running
```bash
php artisan queue:work
```

## Test Data Cleanup

### Reset Demo Data
```bash
php artisan migrate:fresh --seed --class=DemoDataSeeder
```

### Clear Cache
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

## Support and Troubleshooting

### Enable Debug Mode
```env
APP_DEBUG=true
LOG_LEVEL=debug
```

### Check Logs
```bash
tail -f storage/logs/laravel.log
```

### Database Queries
```bash
# Enable query logging
DB_LOG_QUERIES=true
```

## Next Steps After Testing

1. **Fix Issues**: Address any bugs found during testing
2. **Optimize**: Implement caching, query optimization
3. **Security Audit**: Conduct security review
4. **UAT**: User acceptance testing with real users
5. **Staging Deployment**: Deploy to staging environment
6. **Production Deployment**: Follow DEPLOYMENT.md guide

## Testing Metrics

Track these metrics during testing:
- Response times (target: < 200ms)
- Error rates (target: < 0.1%)
- Test coverage (target: > 80%)
- API success rate (target: > 99.9%)

## Conclusion

Comprehensive testing ensures system reliability before production deployment. Document all issues found and their resolutions. Schedule regular testing cycles for continuous quality assurance.
