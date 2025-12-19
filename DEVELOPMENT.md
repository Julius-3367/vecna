# Vecna ERP - Developer Setup Guide

## Quick Start (Local Development)

### Prerequisites

- PHP 8.2 or higher
- PostgreSQL 15+
- Redis 7+
- Composer 2.x
- Node.js 18+
- Git

### Installation Steps

#### 1. Clone Repository

```bash
git clone https://github.com/Julius-3367/vecna.git
cd vecna
```

#### 2. Install Dependencies

```bash
# PHP dependencies
composer install

# JavaScript dependencies
npm install
```

#### 3. Environment Setup

```bash
cp .env.example .env
```

Edit `.env` file:

```env
APP_NAME="Vecna ERP"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://vecna.test

CENTRAL_DOMAIN=vecna.test
TENANT_DOMAIN_SUFFIX=.vecna.test

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=vecna_central
DB_USERNAME=postgres
DB_PASSWORD=

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

QUEUE_CONNECTION=redis
```

#### 4. Generate Application Key

```bash
php artisan key:generate
```

#### 5. Setup Database

**Option A: Using PostgreSQL locally**

```bash
# Create database
createdb vecna_central

# Run migrations
php artisan migrate

# Seed demo data
php artisan db:seed
```

**Option B: Using Laravel Sail (Docker)**

```bash
# Install Sail
composer require laravel/sail --dev

# Publish Sail configuration
php artisan sail:install

# Start Docker containers
./vendor/bin/sail up -d

# Run migrations
./vendor/bin/sail artisan migrate

# Seed data
./vendor/bin/sail artisan db:seed
```

#### 6. Build Frontend Assets

```bash
npm run dev
```

#### 7. Start Development Server

```bash
# Option A: Built-in PHP server
php artisan serve

# Option B: Laravel Sail
./vendor/bin/sail artisan serve

# Start queue worker (separate terminal)
php artisan queue:work

# Start Horizon (if using)
php artisan horizon
```

#### 8. Configure Local Domains

**For macOS/Linux:**

```bash
sudo nano /etc/hosts
```

Add:

```
127.0.0.1 vecna.test
127.0.0.1 shop1.vecna.test
127.0.0.1 shop2.vecna.test
```

**For Windows:**

Edit `C:\Windows\System32\drivers\etc\hosts`:

```
127.0.0.1 vecna.test
127.0.0.1 shop1.vecna.test
```

#### 9. Create Admin User

```bash
php artisan make:filament-user
```

#### 10. Create Test Tenant

```bash
php artisan tenants:create shop1 \
    --domain=shop1.vecna.test \
    --business_name="Test Shop" \
    --email=admin@shop1.test \
    --phone=+254700000000
```

### Access Application

- **Central Dashboard**: http://vecna.test
- **Admin Panel**: http://vecna.test/admin
- **Tenant Dashboard**: http://shop1.vecna.test
- **API Documentation**: http://vecna.test/api/documentation

## Project Structure

```
vecna/
├── app/
│   ├── Console/              # Artisan commands
│   ├── Exceptions/           # Custom exceptions
│   ├── Filament/             # Admin panel resources
│   ├── Http/
│   │   ├── Controllers/      # API & web controllers
│   │   ├── Middleware/       # Custom middleware
│   │   ├── Requests/         # Form requests
│   │   └── Resources/        # API resources
│   ├── Jobs/                 # Queue jobs
│   ├── Livewire/             # Livewire components
│   ├── Models/               # Eloquent models
│   ├── Observers/            # Model observers
│   ├── Policies/             # Authorization policies
│   ├── Providers/            # Service providers
│   └── Services/             # Business logic services
├── config/                   # Configuration files
├── database/
│   ├── factories/            # Model factories
│   ├── migrations/
│   │   └── tenant/           # Tenant-specific migrations
│   └── seeders/              # Database seeders
├── public/                   # Public assets
├── resources/
│   ├── css/                  # Stylesheets
│   ├── js/                   # JavaScript
│   └── views/                # Blade templates
├── routes/
│   ├── web.php               # Web routes
│   ├── api.php               # API routes
│   └── tenant.php            # Tenant routes
├── storage/                  # Application storage
├── tests/
│   ├── Feature/              # Feature tests
│   └── Unit/                 # Unit tests
└── vendor/                   # Composer dependencies
```

## Development Workflow

### 1. Creating New Features

#### Create Migration

```bash
# Central database migration
php artisan make:migration create_example_table

# Tenant database migration
php artisan make:migration create_example_table --path=database/migrations/tenant
```

#### Create Model

```bash
php artisan make:model Example -mfsc
# -m: migration
# -f: factory
# -s: seeder
# -c: controller
```

#### Create Controller

```bash
# API controller
php artisan make:controller Api/ExampleController --api

# Resource controller
php artisan make:controller ExampleController --resource
```

#### Create Livewire Component

```bash
php artisan make:livewire ExampleComponent
```

#### Create Filament Resource (Admin Panel)

```bash
php artisan make:filament-resource Example --generate
```

### 2. Working with Tenants

#### Create Tenant Programmatically

```php
use App\Models\Tenant;

$tenant = Tenant::create([
    'business_name' => 'My Business',
    'subdomain' => 'mybiz',
    'email' => 'owner@mybiz.com',
    'phone' => '+254700000000',
    'industry' => 'retail',
]);

$tenant->createDomain([
    'domain' => 'mybiz.vecna.test',
]);

// Run migrations for tenant
$tenant->run(function () {
    Artisan::call('migrate', ['--database' => 'tenant']);
});
```

#### Execute Code in Tenant Context

```php
use Stancl\Tenancy\Facades\Tenancy;

$tenant = Tenant::find('tenant_id');

Tenancy::initialize($tenant);

// Now all database queries run in tenant context
$products = \App\Models\Product::all();

Tenancy::end();
```

### 3. Testing

#### Run All Tests

```bash
php artisan test
```

#### Run Specific Test

```bash
php artisan test --filter ExampleTest
```

#### Create Test

```bash
# Feature test
php artisan make:test ExampleTest

# Unit test
php artisan make:test ExampleTest --unit
```

#### Example Test

```php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Tenant;
use App\Models\Product;

class ProductTest extends TestCase
{
    public function test_can_create_product()
    {
        $tenant = Tenant::factory()->create();
        
        tenancy()->initialize($tenant);
        
        $product = Product::factory()->create([
            'name' => 'Test Product',
            'sku' => 'TEST-001',
            'selling_price' => 1000,
        ]);
        
        $this->assertDatabaseHas('products', [
            'sku' => 'TEST-001',
        ]);
    }
}
```

### 4. Database Seeding

#### Create Seeder

```bash
php artisan make:seeder ExampleSeeder
```

#### Run Seeders

```bash
# All seeders
php artisan db:seed

# Specific seeder
php artisan db:seed --class=ExampleSeeder

# For tenant
tenancy()->runForMultiple([Tenant::find('xyz')], function () {
    Artisan::call('db:seed', ['--class' => 'TenantSeeder']);
});
```

### 5. Queue Jobs

#### Create Job

```bash
php artisan make:job ProcessOrder
```

#### Dispatch Job

```php
use App\Jobs\ProcessOrder;

ProcessOrder::dispatch($order);

// Delayed dispatch
ProcessOrder::dispatch($order)->delay(now()->addMinutes(10));

// On specific queue
ProcessOrder::dispatch($order)->onQueue('high-priority');
```

#### Monitor Horizon

```bash
php artisan horizon

# Access dashboard at: http://vecna.test/horizon
```

### 6. API Development

#### Create API Resource

```bash
php artisan make:resource ProductResource
```

#### Example API Controller

```php
namespace App\Http\Controllers\Api;

use App\Models\Product;
use App\Http\Resources\ProductResource;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::query()
            ->when(request('search'), function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->paginate(20);
            
        return ProductResource::collection($products);
    }
    
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|unique:products',
            'selling_price' => 'required|numeric|min:0',
        ]);
        
        $product = Product::create($validated);
        
        return new ProductResource($product);
    }
}
```

### 7. Events & Listeners

#### Create Event

```bash
php artisan make:event OrderPlaced
```

#### Create Listener

```bash
php artisan make:listener SendOrderConfirmation --event=OrderPlaced
```

#### Dispatch Event

```php
use App\Events\OrderPlaced;

event(new OrderPlaced($order));
```

## Useful Artisan Commands

### Development

```bash
# Clear all caches
php artisan optimize:clear

# Recreate cache
php artisan optimize

# List routes
php artisan route:list

# Tinker (REPL)
php artisan tinker

# Generate IDE helper
php artisan ide-helper:generate
php artisan ide-helper:models
```

### Tenancy

```bash
# List all tenants
php artisan tenants:list

# Run command for all tenants
php artisan tenants:run migrate

# Run command for specific tenant
php artisan tenants:run migrate --tenant=abc123
```

### Maintenance

```bash
# Put app in maintenance mode
php artisan down

# Bring app back up
php artisan up

# Check application status
php artisan about
```

## Code Style & Standards

### PHP CodeSniffer (Pint)

```bash
# Check code style
./vendor/bin/pint --test

# Fix code style
./vendor/bin/pint
```

### PHPStan (Static Analysis)

```bash
composer require --dev phpstan/phpstan

./vendor/bin/phpstan analyse
```

## Debugging Tools

### Laravel Telescope

```bash
composer require laravel/telescope --dev

php artisan telescope:install
php artisan migrate

# Access: http://vecna.test/telescope
```

### Debug Bar

```bash
composer require barryvdh/laravel-debugbar --dev

# Automatically enabled in local environment
```

### Log Viewer

```bash
# View logs
tail -f storage/logs/laravel.log

# Or use Telescope for better log viewing
```

## Git Workflow

### Branching Strategy

```
master (production)
  └── develop (staging)
       ├── feature/inventory-module
       ├── feature/pos-integration
       └── bugfix/stock-calculation
```

### Commit Messages

```
feat: Add M-Pesa STK push integration
fix: Correct stock calculation on transfers
docs: Update API documentation
refactor: Extract payment logic to service class
test: Add unit tests for Product model
chore: Update dependencies
```

### Pull Request Process

1. Create feature branch from `develop`
2. Make changes and commit
3. Write tests
4. Push and create PR
5. Code review
6. Merge to `develop`
7. Deploy to staging
8. After testing, merge to `master`

## Environment Variables Reference

```env
# App
APP_NAME="Vecna ERP"
APP_ENV=local|production
APP_DEBUG=true|false
APP_URL=http://vecna.test

# Tenancy
CENTRAL_DOMAIN=vecna.test
TENANT_DOMAIN_SUFFIX=.vecna.test

# Database
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=vecna_central
DB_USERNAME=postgres
DB_PASSWORD=

# Cache & Queue
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
QUEUE_CONNECTION=redis

# Mail
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=
MAIL_PASSWORD=

# AWS S3
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=af-south-1
AWS_BUCKET=

# M-Pesa
MPESA_CONSUMER_KEY=
MPESA_CONSUMER_SECRET=
MPESA_PASSKEY=
MPESA_SHORTCODE=
MPESA_ENVIRONMENT=sandbox|production

# Africa's Talking (SMS)
AT_USERNAME=
AT_API_KEY=
AT_SENDER_ID=VECNA

# Monitoring
SENTRY_LARAVEL_DSN=
```

## Common Issues & Solutions

### Issue: "No application encryption key"

```bash
php artisan key:generate
```

### Issue: "Permission denied" on storage

```bash
chmod -R 775 storage
chmod -R 775 bootstrap/cache
```

### Issue: Tenant migrations not running

```bash
# Run migrations for specific tenant
php artisan tenants:migrate --tenants=shop1
```

### Issue: Queue not processing

```bash
# Check queue worker is running
ps aux | grep queue:work

# Restart queue worker
php artisan queue:restart
```

## Resources

- **Laravel Docs**: https://laravel.com/docs
- **Stancl Tenancy**: https://tenancyforlaravel.com/docs
- **Filament**: https://filamentphp.com/docs
- **Livewire**: https://livewire.laravel.com/docs

## Getting Help

- **GitHub Issues**: https://github.com/Julius-3367/vecna/issues
- **Email**: dev@vecna.co.ke
- **Slack**: vecna-dev.slack.com

---

**Happy Coding! 🚀**
