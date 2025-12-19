#!/bin/bash

# Vecna ERP - Quick Setup Script
# This script automates the initial setup for local development

set -e

echo "🚀 Vecna ERP - Quick Setup"
echo "=========================="
echo ""

# Check if composer is installed
if ! command -v composer &> /dev/null; then
    echo "❌ Composer is not installed. Please install Composer first."
    echo "   Visit: https://getcomposer.org/download/"
    exit 1
fi

# Check if npm is installed
if ! command -v npm &> /dev/null; then
    echo "❌ npm is not installed. Please install Node.js first."
    echo "   Visit: https://nodejs.org/"
    exit 1
fi

# Check if PostgreSQL is installed
if ! command -v psql &> /dev/null; then
    echo "⚠️  PostgreSQL is not installed. Please install PostgreSQL 15+ first."
    echo "   Ubuntu: sudo apt install postgresql"
    echo "   macOS: brew install postgresql@15"
    exit 1
fi

# Check if Redis is installed
if ! command -v redis-cli &> /dev/null; then
    echo "⚠️  Redis is not installed. Please install Redis first."
    echo "   Ubuntu: sudo apt install redis-server"
    echo "   macOS: brew install redis"
    exit 1
fi

echo "✅ All prerequisites found!"
echo ""

# Install PHP dependencies
echo "📦 Installing PHP dependencies..."
composer install

# Install JavaScript dependencies
echo "📦 Installing JavaScript dependencies..."
npm install

# Copy environment file if it doesn't exist
if [ ! -f .env ]; then
    echo "📝 Creating .env file..."
    cp .env.example .env
else
    echo "ℹ️  .env file already exists, skipping..."
fi

# Generate application key
echo "🔑 Generating application key..."
php artisan key:generate

# Prompt for database configuration
echo ""
echo "📊 Database Configuration"
echo "========================"
read -p "Enter PostgreSQL database name [vecna_central]: " DB_NAME
DB_NAME=${DB_NAME:-vecna_central}

read -p "Enter PostgreSQL username [postgres]: " DB_USER
DB_USER=${DB_USER:-postgres}

read -sp "Enter PostgreSQL password: " DB_PASS
echo ""

# Update .env file with database credentials
if [[ "$OSTYPE" == "darwin"* ]]; then
    # macOS
    sed -i '' "s/DB_DATABASE=.*/DB_DATABASE=$DB_NAME/" .env
    sed -i '' "s/DB_USERNAME=.*/DB_USERNAME=$DB_USER/" .env
    sed -i '' "s/DB_PASSWORD=.*/DB_PASSWORD=$DB_PASS/" .env
else
    # Linux
    sed -i "s/DB_DATABASE=.*/DB_DATABASE=$DB_NAME/" .env
    sed -i "s/DB_USERNAME=.*/DB_USERNAME=$DB_USER/" .env
    sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=$DB_PASS/" .env
fi

# Create database if it doesn't exist
echo "🗄️  Creating database..."
PGPASSWORD=$DB_PASS psql -U $DB_USER -h localhost -tc "SELECT 1 FROM pg_database WHERE datname = '$DB_NAME'" | grep -q 1 || \
PGPASSWORD=$DB_PASS psql -U $DB_USER -h localhost -c "CREATE DATABASE $DB_NAME"

# Run migrations
echo "🔄 Running migrations..."
php artisan migrate

echo ""
echo "✅ Setup complete!"
echo ""
echo "🎯 Next Steps:"
echo "=============="
echo ""
echo "1. Configure local domains:"
echo "   Add to /etc/hosts (or C:\\Windows\\System32\\drivers\\etc\\hosts on Windows):"
echo "   127.0.0.1 vecna.test"
echo "   127.0.0.1 shop1.vecna.test"
echo ""
echo "2. Start the development server:"
echo "   php artisan serve"
echo ""
echo "3. In a separate terminal, start the queue worker:"
echo "   php artisan queue:work"
echo ""
echo "4. Build frontend assets:"
echo "   npm run dev"
echo ""
echo "5. Create admin user:"
echo "   php artisan make:filament-user"
echo ""
echo "6. Create a test tenant:"
echo "   php artisan tenants:create shop1 --domain=shop1.vecna.test"
echo ""
echo "7. Access the application:"
echo "   - Central: http://vecna.test"
echo "   - Admin Panel: http://vecna.test/admin"
echo "   - Tenant: http://shop1.vecna.test"
echo ""
echo "📚 For more information, see:"
echo "   - README.md - Project overview"
echo "   - DEVELOPMENT.md - Developer guide"
echo "   - DEPLOYMENT.md - Production deployment"
echo "   - API.md - API documentation"
echo ""
echo "🎉 Happy coding!"
