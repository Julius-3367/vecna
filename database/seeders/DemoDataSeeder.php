<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Department;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\StockLocation;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create demo tenant
        $tenant = Tenant::create([
            'id' => 'demo-tenant',
            'business_name' => 'Demo Store Ltd',
            'subdomain' => 'demo',
            'email' => 'admin@demo.vecna.test',
            'phone' => '254712345678',
            'industry' => 'retail',
            'status' => 'active',
            'trial_ends_at' => now()->addDays(30),
        ]);

        $tenant->run(function () {
            $this->seedTenantData();
        });
    }

    protected function seedTenantData()
    {
        // Create admin user
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@demo.test',
            'password' => Hash::make('password'),
            'phone' => '254712345678',
            'role' => 'admin',
            'is_active' => true,
        ]);

        // Create manager
        $manager = User::create([
            'name' => 'Manager User',
            'email' => 'manager@demo.test',
            'password' => Hash::make('password'),
            'phone' => '254723456789',
            'role' => 'manager',
            'is_active' => true,
        ]);

        // Create stock locations
        $mainStore = StockLocation::create([
            'name' => 'Main Store',
            'code' => 'MAIN',
            'address' => '123 Business Street, Nairobi',
            'city' => 'Nairobi',
            'phone' => '254712345678',
            'is_default' => true,
            'is_active' => true,
        ]);

        $warehouse = StockLocation::create([
            'name' => 'Warehouse',
            'code' => 'WH01',
            'address' => '456 Industrial Area, Nairobi',
            'city' => 'Nairobi',
            'phone' => '254723456789',
            'is_default' => false,
            'is_active' => true,
        ]);

        // Create departments
        $sales = Department::create([
            'name' => 'Sales',
            'description' => 'Sales department',
            'manager_id' => $manager->id,
            'is_active' => true,
        ]);

        Department::create([
            'name' => 'Warehouse',
            'description' => 'Warehouse operations',
            'is_active' => true,
        ]);

        // Create categories
        $electronics = Category::create([
            'name' => 'Electronics',
            'description' => 'Electronic devices and accessories',
            'is_active' => true,
        ]);

        $furniture = Category::create([
            'name' => 'Furniture',
            'description' => 'Office and home furniture',
            'is_active' => true,
        ]);

        $stationery = Category::create([
            'name' => 'Stationery',
            'description' => 'Office supplies and stationery',
            'is_active' => true,
        ]);

        // Create brands
        $samsung = Brand::create([
            'name' => 'Samsung',
            'description' => 'Samsung electronics',
            'is_active' => true,
        ]);

        $hp = Brand::create([
            'name' => 'HP',
            'description' => 'HP computers and printers',
            'is_active' => true,
        ]);

        $generic = Brand::create([
            'name' => 'Generic',
            'description' => 'Generic products',
            'is_active' => true,
        ]);

        // Create units
        $pieces = Unit::create([
            'name' => 'Pieces',
            'short_name' => 'pcs',
            'is_active' => true,
        ]);

        $boxes = Unit::create([
            'name' => 'Boxes',
            'short_name' => 'box',
            'is_active' => true,
        ]);

        $kg = Unit::create([
            'name' => 'Kilograms',
            'short_name' => 'kg',
            'is_active' => true,
        ]);

        // Create products
        $products = [
            [
                'name' => 'Samsung Galaxy A54',
                'sku' => 'SAMS-A54-BLK',
                'barcode' => '8801643990626',
                'category_id' => $electronics->id,
                'brand_id' => $samsung->id,
                'unit_id' => $pieces->id,
                'description' => '6.4" Display, 8GB RAM, 256GB Storage',
                'cost_price' => 35000,
                'selling_price' => 45000,
                'current_stock' => 15,
                'minimum_stock' => 5,
                'maximum_stock' => 50,
                'reorder_level' => 10,
                'tax_rate' => 16,
                'is_taxable' => true,
                'track_stock' => true,
                'is_active' => true,
            ],
            [
                'name' => 'HP Laptop 15-dw3000',
                'sku' => 'HP-LAP-15DW',
                'barcode' => '195161109731',
                'category_id' => $electronics->id,
                'brand_id' => $hp->id,
                'unit_id' => $pieces->id,
                'description' => 'Intel Core i5, 8GB RAM, 512GB SSD',
                'cost_price' => 55000,
                'selling_price' => 75000,
                'current_stock' => 8,
                'minimum_stock' => 3,
                'maximum_stock' => 20,
                'reorder_level' => 5,
                'tax_rate' => 16,
                'is_taxable' => true,
                'track_stock' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Office Desk Chair',
                'sku' => 'FURN-CHR-001',
                'category_id' => $furniture->id,
                'brand_id' => $generic->id,
                'unit_id' => $pieces->id,
                'description' => 'Ergonomic office chair with adjustable height',
                'cost_price' => 8000,
                'selling_price' => 12000,
                'current_stock' => 25,
                'minimum_stock' => 10,
                'maximum_stock' => 100,
                'reorder_level' => 15,
                'tax_rate' => 16,
                'is_taxable' => true,
                'track_stock' => true,
                'is_active' => true,
            ],
            [
                'name' => 'A4 Printing Paper (Ream)',
                'sku' => 'STAT-PPR-A4',
                'category_id' => $stationery->id,
                'brand_id' => $generic->id,
                'unit_id' => $boxes->id,
                'description' => '500 sheets per ream, 80gsm',
                'cost_price' => 450,
                'selling_price' => 650,
                'current_stock' => 120,
                'minimum_stock' => 50,
                'maximum_stock' => 500,
                'reorder_level' => 75,
                'tax_rate' => 16,
                'is_taxable' => true,
                'track_stock' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Ball Point Pens (Box of 50)',
                'sku' => 'STAT-PEN-BP50',
                'category_id' => $stationery->id,
                'brand_id' => $generic->id,
                'unit_id' => $boxes->id,
                'description' => 'Blue ink ball point pens',
                'cost_price' => 350,
                'selling_price' => 500,
                'current_stock' => 85,
                'minimum_stock' => 20,
                'maximum_stock' => 200,
                'reorder_level' => 30,
                'tax_rate' => 16,
                'is_taxable' => true,
                'track_stock' => true,
                'is_active' => true,
            ],
        ];

        foreach ($products as $productData) {
            Product::create($productData);
        }

        // Create customers
        $customers = [
            [
                'name' => 'Acme Corporation Ltd',
                'email' => 'info@acmecorp.co.ke',
                'phone' => '254722111222',
                'kra_pin' => 'P051234567A',
                'customer_type' => 'b2b',
                'credit_limit' => 500000,
                'payment_terms' => 30,
                'currency' => 'KES',
                'tax_exempt' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Tech Solutions Kenya',
                'email' => 'sales@techsolutions.co.ke',
                'phone' => '254733222333',
                'kra_pin' => 'P051234568B',
                'customer_type' => 'b2b',
                'credit_limit' => 300000,
                'payment_terms' => 30,
                'currency' => 'KES',
                'tax_exempt' => false,
                'is_active' => true,
            ],
            [
                'name' => 'John Kamau',
                'email' => 'john.kamau@email.com',
                'phone' => '254744333444',
                'customer_type' => 'b2c',
                'currency' => 'KES',
                'tax_exempt' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Mary Wanjiku',
                'email' => 'mary.w@email.com',
                'phone' => '254755444555',
                'customer_type' => 'b2c',
                'currency' => 'KES',
                'tax_exempt' => false,
                'is_active' => true,
            ],
        ];

        foreach ($customers as $customerData) {
            Customer::create($customerData);
        }

        // Create suppliers
        $suppliers = [
            [
                'name' => 'Electronics Wholesalers Ltd',
                'email' => 'sales@electronicswholesale.co.ke',
                'phone' => '254766555666',
                'kra_pin' => 'P051234569C',
                'payment_terms' => 30,
                'credit_limit' => 1000000,
                'currency' => 'KES',
                'is_active' => true,
            ],
            [
                'name' => 'Office Supplies Kenya',
                'email' => 'info@officesupplies.co.ke',
                'phone' => '254777666777',
                'kra_pin' => 'P051234570D',
                'payment_terms' => 14,
                'credit_limit' => 500000,
                'currency' => 'KES',
                'is_active' => true,
            ],
        ];

        foreach ($suppliers as $supplierData) {
            Supplier::create($supplierData);
        }

        // Create expense categories
        $expenseCategories = [
            ['name' => 'Rent', 'description' => 'Office and warehouse rent', 'is_active' => true],
            ['name' => 'Utilities', 'description' => 'Electricity, water, internet', 'is_active' => true],
            ['name' => 'Salaries', 'description' => 'Employee salaries and wages', 'is_active' => true],
            ['name' => 'Marketing', 'description' => 'Advertising and promotion', 'is_active' => true],
            ['name' => 'Transport', 'description' => 'Delivery and logistics', 'is_active' => true],
            ['name' => 'Office Supplies', 'description' => 'Stationery and consumables', 'is_active' => true],
        ];

        foreach ($expenseCategories as $catData) {
            ExpenseCategory::create($catData);
        }

        echo "✅ Demo data seeded successfully!\n";
        echo "   - Admin: admin@demo.test / password\n";
        echo "   - Manager: manager@demo.test / password\n";
        echo "   - Products: 5 items created\n";
        echo "   - Customers: 4 created (2 B2B, 2 B2C)\n";
        echo "   - Suppliers: 2 created\n";
    }
}
