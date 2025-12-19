# Contributing to Vecna ERP

Thank you for your interest in contributing to Vecna ERP! This document provides guidelines for contributing to the project.

## 🚀 Getting Started

### Prerequisites

- PHP 8.2 or higher
- Composer 2.x
- Node.js 18+ & npm
- PostgreSQL 15+ or SQLite (for development)
- Redis 7+
- Git

### Development Setup

1. **Clone the repository**
```bash
git clone https://github.com/Julius-3367/vecna.git
cd vecna
```

2. **Install dependencies**
```bash
composer install
npm install
```

3. **Environment configuration**
```bash
cp .env.example .env
php artisan key:generate
```

4. **Configure database** (SQLite for quick start)
```bash
touch database/database.sqlite
# Update .env: DB_CONNECTION=sqlite
```

5. **Run migrations and seeders**
```bash
php artisan migrate:fresh --seed
```

6. **Start development server**
```bash
php artisan serve
```

## 📁 Project Structure

```
vecna/
├── app/
│   ├── Http/Controllers/Api/  # API controllers
│   ├── Models/                # Eloquent models
│   ├── Services/              # Business logic services
│   └── Http/Middleware/       # Custom middleware
├── database/
│   ├── migrations/            # Central database migrations
│   ├── migrations/tenant/     # Tenant-specific migrations
│   ├── seeders/               # Database seeders
│   └── factories/             # Model factories
├── routes/
│   ├── api.php               # API routes
│   └── web.php               # Web routes
└── tests/
    ├── Feature/              # Feature tests
    └── Unit/                 # Unit tests
```

## 🔧 Development Guidelines

### Code Style

- Follow PSR-12 coding standards
- Use Laravel Pint for formatting: `./vendor/bin/pint`
- Write meaningful comments for complex logic
- Use type hints for method parameters and return types

### Naming Conventions

- **Controllers**: Singular, e.g., `ProductController`
- **Models**: Singular, e.g., `Product`
- **Tables**: Plural snake_case, e.g., `products`
- **Variables**: camelCase, e.g., `$productName`
- **Methods**: camelCase, e.g., `calculateTotal()`

### Git Workflow

1. Create a feature branch from `master`
```bash
git checkout -b feature/your-feature-name
```

2. Make your changes with descriptive commits
```bash
git commit -m "feat: add WhatsApp notification for low stock"
```

3. Push to your fork
```bash
git push origin feature/your-feature-name
```

4. Create a Pull Request

### Commit Message Format

Follow conventional commits:

- `feat:` New feature
- `fix:` Bug fix
- `docs:` Documentation changes
- `style:` Code style changes (formatting)
- `refactor:` Code refactoring
- `test:` Adding or updating tests
- `chore:` Maintenance tasks

Example:
```
feat: add M-Pesa reconciliation dashboard

- Implement daily transaction matching
- Add reconciliation reports
- Update API documentation
```

## 🧪 Testing

### Running Tests

```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --filter=AuthenticationTest

# Run with coverage
php artisan test --coverage
```

### Writing Tests

- Place feature tests in `tests/Feature/`
- Place unit tests in `tests/Unit/`
- Use descriptive test method names: `test_user_can_create_product()`
- Ensure tests are isolated and can run independently

Example:
```php
public function test_user_can_create_sale(): void
{
    $user = User::factory()->create();
    $product = Product::factory()->create(['stock' => 10]);

    $response = $this->actingAs($user)
        ->postJson('/api/v1/sales', [
            'items' => [['product_id' => $product->id, 'quantity' => 2]]
        ]);

    $response->assertStatus(201);
    $this->assertEquals(8, $product->fresh()->stock);
}
```

## 🐛 Reporting Bugs

When reporting bugs, please include:

1. **Description**: Clear description of the issue
2. **Steps to Reproduce**: Numbered steps to reproduce
3. **Expected Behavior**: What should happen
4. **Actual Behavior**: What actually happens
5. **Environment**: PHP version, Laravel version, OS
6. **Screenshots**: If applicable

## 💡 Feature Requests

We welcome feature requests! Please:

1. Check if the feature already exists or is planned
2. Provide clear use cases and benefits
3. Consider Kenya-specific requirements
4. Be open to discussion and iteration

## 🌍 Kenya-Specific Features

When contributing features, keep in mind:

- **Currency**: Use KES (Kenyan Shilling)
- **Phone Numbers**: Format as 254XXXXXXXXX
- **Tax Compliance**: Follow KRA iTax requirements
- **M-Pesa**: Use Safaricom Daraja API v2
- **Business Culture**: Consider local SME workflows

## 📝 Documentation

- Update relevant `.md` files for new features
- Document API changes in `API.md`
- Add inline comments for complex business logic
- Update `README.md` if setup process changes

## ✅ Pull Request Checklist

Before submitting a PR, ensure:

- [ ] Code follows PSR-12 standards
- [ ] All tests pass (`php artisan test`)
- [ ] New features have tests
- [ ] Documentation is updated
- [ ] Commit messages follow conventions
- [ ] No debug code (dd(), var_dump(), console.log)
- [ ] Code is properly formatted (`./vendor/bin/pint`)

## 🔐 Security

If you discover a security vulnerability:

1. **DO NOT** open a public issue
2. Email: security@vecna.co.ke
3. Include detailed description and steps to reproduce
4. Allow time for a fix before public disclosure

## 📜 License

By contributing to Vecna ERP, you agree that your contributions will be licensed under the project's license.

## 🙏 Thank You!

Your contributions make Vecna ERP better for Kenyan SMEs. We appreciate your time and effort!

---

**Questions?** Reach out to the maintainers or open a discussion on GitHub.
