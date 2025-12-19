<?php

namespace Database\Seeders;

use App\Models\IndustryTemplate;
use Illuminate\Database\Seeder;

class IndustryTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Retail Template
        IndustryTemplate::create([
            'name' => 'Retail Store',
            'slug' => 'retail',
            'description' => 'Perfect for retail shops, boutiques, and general merchandise stores',
            'icon' => 'shopping-bag',
            'categories' => [
                ['name' => 'Electronics', 'description' => 'Electronic devices and accessories', 'is_active' => true],
                ['name' => 'Clothing & Fashion', 'description' => 'Apparel, shoes, and accessories', 'is_active' => true],
                ['name' => 'Home & Kitchen', 'description' => 'Home appliances and kitchenware', 'is_active' => true],
                ['name' => 'Beauty & Personal Care', 'description' => 'Cosmetics and personal care products', 'is_active' => true],
                ['name' => 'Toys & Games', 'description' => 'Children toys and games', 'is_active' => true],
            ],
            'products' => [
                [
                    'name' => 'Samsung Galaxy A54',
                    'sku' => 'ELEC-SAM-A54',
                    'category_id' => 1,
                    'cost_price' => 35000,
                    'selling_price' => 45000,
                    'current_stock' => 10,
                    'minimum_stock' => 3,
                    'track_stock' => true,
                    'is_active' => true,
                ],
                [
                    'name' => 'Men\'s T-Shirt',
                    'sku' => 'CLOTH-TSHIRT-M',
                    'category_id' => 2,
                    'cost_price' => 500,
                    'selling_price' => 1200,
                    'current_stock' => 50,
                    'minimum_stock' => 20,
                    'track_stock' => true,
                    'is_active' => true,
                ],
                [
                    'name' => 'Blender 1.5L',
                    'sku' => 'HOME-BLEND-15',
                    'category_id' => 3,
                    'cost_price' => 2500,
                    'selling_price' => 4000,
                    'current_stock' => 15,
                    'minimum_stock' => 5,
                    'track_stock' => true,
                    'is_active' => true,
                ],
            ],
            'chart_of_accounts' => [
                // Assets
                ['code' => '1000', 'name' => 'Cash', 'type' => 'asset', 'parent_id' => null],
                ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => 'asset', 'parent_id' => null],
                ['code' => '1200', 'name' => 'Inventory', 'type' => 'asset', 'parent_id' => null],
                ['code' => '1500', 'name' => 'Equipment', 'type' => 'asset', 'parent_id' => null],
                // Liabilities
                ['code' => '2000', 'name' => 'Accounts Payable', 'type' => 'liability', 'parent_id' => null],
                ['code' => '2100', 'name' => 'VAT Payable', 'type' => 'liability', 'parent_id' => null],
                // Revenue
                ['code' => '4000', 'name' => 'Sales Revenue', 'type' => 'revenue', 'parent_id' => null],
                // Expenses
                ['code' => '5000', 'name' => 'Cost of Goods Sold', 'type' => 'expense', 'parent_id' => null],
                ['code' => '5100', 'name' => 'Rent Expense', 'type' => 'expense', 'parent_id' => null],
                ['code' => '5200', 'name' => 'Salaries', 'type' => 'expense', 'parent_id' => null],
            ],
            'settings' => [
                'default_tax_rate' => 16,
                'currency' => 'KES',
                'date_format' => 'd/m/Y',
                'fiscal_year_start' => '01-01',
                'low_stock_threshold' => 10,
            ],
            'reports' => [
                'sales_summary',
                'inventory_valuation',
                'customer_statement',
                'profit_loss',
                'vat_report',
            ],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // Hospitality Template
        IndustryTemplate::create([
            'name' => 'Restaurant & Hospitality',
            'slug' => 'hospitality',
            'description' => 'Ideal for restaurants, cafes, hotels, and food service businesses',
            'icon' => 'utensils',
            'categories' => [
                ['name' => 'Beverages', 'description' => 'Drinks and beverages', 'is_active' => true],
                ['name' => 'Main Courses', 'description' => 'Main dishes and meals', 'is_active' => true],
                ['name' => 'Appetizers', 'description' => 'Starters and appetizers', 'is_active' => true],
                ['name' => 'Desserts', 'description' => 'Sweet treats and desserts', 'is_active' => true],
                ['name' => 'Raw Materials', 'description' => 'Kitchen ingredients', 'is_active' => true],
            ],
            'products' => [
                [
                    'name' => 'Cappuccino',
                    'sku' => 'BEV-CAPP',
                    'category_id' => 1,
                    'cost_price' => 80,
                    'selling_price' => 250,
                    'current_stock' => 0,
                    'minimum_stock' => 0,
                    'track_stock' => false,
                    'is_active' => true,
                ],
                [
                    'name' => 'Grilled Chicken',
                    'sku' => 'MAIN-CHICK',
                    'category_id' => 2,
                    'cost_price' => 350,
                    'selling_price' => 800,
                    'current_stock' => 0,
                    'minimum_stock' => 0,
                    'track_stock' => false,
                    'is_active' => true,
                ],
                [
                    'name' => 'French Fries',
                    'sku' => 'APP-FRIES',
                    'category_id' => 3,
                    'cost_price' => 50,
                    'selling_price' => 200,
                    'current_stock' => 0,
                    'minimum_stock' => 0,
                    'track_stock' => false,
                    'is_active' => true,
                ],
            ],
            'chart_of_accounts' => [
                // Assets
                ['code' => '1000', 'name' => 'Cash', 'type' => 'asset', 'parent_id' => null],
                ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => 'asset', 'parent_id' => null],
                ['code' => '1200', 'name' => 'Kitchen Equipment', 'type' => 'asset', 'parent_id' => null],
                ['code' => '1300', 'name' => 'Furniture & Fixtures', 'type' => 'asset', 'parent_id' => null],
                // Liabilities
                ['code' => '2000', 'name' => 'Accounts Payable', 'type' => 'liability', 'parent_id' => null],
                ['code' => '2100', 'name' => 'VAT Payable', 'type' => 'liability', 'parent_id' => null],
                // Revenue
                ['code' => '4000', 'name' => 'Food Sales', 'type' => 'revenue', 'parent_id' => null],
                ['code' => '4100', 'name' => 'Beverage Sales', 'type' => 'revenue', 'parent_id' => null],
                // Expenses
                ['code' => '5000', 'name' => 'Food Cost', 'type' => 'expense', 'parent_id' => null],
                ['code' => '5100', 'name' => 'Labor Cost', 'type' => 'expense', 'parent_id' => null],
                ['code' => '5200', 'name' => 'Rent', 'type' => 'expense', 'parent_id' => null],
                ['code' => '5300', 'name' => 'Utilities', 'type' => 'expense', 'parent_id' => null],
            ],
            'settings' => [
                'default_tax_rate' => 16,
                'currency' => 'KES',
                'date_format' => 'd/m/Y',
                'fiscal_year_start' => '01-01',
                'table_management' => true,
                'split_bills' => true,
            ],
            'reports' => [
                'daily_sales',
                'menu_performance',
                'table_turnover',
                'food_cost_analysis',
                'vat_report',
            ],
            'is_active' => true,
            'sort_order' => 2,
        ]);

        // Wholesale Template
        IndustryTemplate::create([
            'name' => 'Wholesale & Distribution',
            'slug' => 'wholesale',
            'description' => 'Built for wholesalers, distributors, and bulk traders',
            'icon' => 'warehouse',
            'categories' => [
                ['name' => 'Electronics Wholesale', 'description' => 'Bulk electronics', 'is_active' => true],
                ['name' => 'Food & Beverages', 'description' => 'Food products in bulk', 'is_active' => true],
                ['name' => 'Building Materials', 'description' => 'Construction materials', 'is_active' => true],
                ['name' => 'Textiles', 'description' => 'Fabrics and textiles', 'is_active' => true],
                ['name' => 'Packaging Materials', 'description' => 'Boxes, bags, wrapping', 'is_active' => true],
            ],
            'products' => [
                [
                    'name' => 'LED Bulbs (Box of 100)',
                    'sku' => 'ELEC-LED-100',
                    'category_id' => 1,
                    'cost_price' => 8000,
                    'selling_price' => 12000,
                    'current_stock' => 50,
                    'minimum_stock' => 10,
                    'track_stock' => true,
                    'is_active' => true,
                ],
                [
                    'name' => 'Rice 50kg Bag',
                    'sku' => 'FOOD-RICE-50',
                    'category_id' => 2,
                    'cost_price' => 4500,
                    'selling_price' => 6000,
                    'current_stock' => 200,
                    'minimum_stock' => 50,
                    'track_stock' => true,
                    'is_active' => true,
                ],
                [
                    'name' => 'Cement (50kg)',
                    'sku' => 'BUILD-CEM-50',
                    'category_id' => 3,
                    'cost_price' => 650,
                    'selling_price' => 850,
                    'current_stock' => 500,
                    'minimum_stock' => 100,
                    'track_stock' => true,
                    'is_active' => true,
                ],
            ],
            'chart_of_accounts' => [
                // Assets
                ['code' => '1000', 'name' => 'Cash', 'type' => 'asset', 'parent_id' => null],
                ['code' => '1100', 'name' => 'Trade Receivables', 'type' => 'asset', 'parent_id' => null],
                ['code' => '1200', 'name' => 'Inventory - Finished Goods', 'type' => 'asset', 'parent_id' => null],
                ['code' => '1500', 'name' => 'Delivery Vehicles', 'type' => 'asset', 'parent_id' => null],
                // Liabilities
                ['code' => '2000', 'name' => 'Trade Payables', 'type' => 'liability', 'parent_id' => null],
                ['code' => '2100', 'name' => 'VAT Payable', 'type' => 'liability', 'parent_id' => null],
                // Revenue
                ['code' => '4000', 'name' => 'Wholesale Sales', 'type' => 'revenue', 'parent_id' => null],
                ['code' => '4100', 'name' => 'Delivery Charges', 'type' => 'revenue', 'parent_id' => null],
                // Expenses
                ['code' => '5000', 'name' => 'Cost of Goods Sold', 'type' => 'expense', 'parent_id' => null],
                ['code' => '5100', 'name' => 'Warehouse Rent', 'type' => 'expense', 'parent_id' => null],
                ['code' => '5200', 'name' => 'Transportation', 'type' => 'expense', 'parent_id' => null],
                ['code' => '5300', 'name' => 'Staff Salaries', 'type' => 'expense', 'parent_id' => null],
            ],
            'settings' => [
                'default_tax_rate' => 16,
                'currency' => 'KES',
                'date_format' => 'd/m/Y',
                'fiscal_year_start' => '01-01',
                'bulk_pricing' => true,
                'credit_terms' => 30,
                'minimum_order_value' => 10000,
            ],
            'reports' => [
                'sales_by_customer',
                'inventory_aging',
                'debtor_aging',
                'supplier_performance',
                'profit_margin_analysis',
                'vat_report',
            ],
            'is_active' => true,
            'sort_order' => 3,
        ]);

        // Manufacturing Template
        IndustryTemplate::create([
            'name' => 'Light Manufacturing',
            'slug' => 'manufacturing',
            'description' => 'For manufacturers, assemblers, and production businesses',
            'icon' => 'industry',
            'categories' => [
                ['name' => 'Raw Materials', 'description' => 'Production inputs', 'is_active' => true],
                ['name' => 'Work in Progress', 'description' => 'Partially completed goods', 'is_active' => true],
                ['name' => 'Finished Goods', 'description' => 'Ready for sale products', 'is_active' => true],
                ['name' => 'Packaging', 'description' => 'Packaging materials', 'is_active' => true],
                ['name' => 'Consumables', 'description' => 'Factory supplies', 'is_active' => true],
            ],
            'products' => [
                [
                    'name' => 'Plastic Bottle (Raw)',
                    'sku' => 'RAW-BOTTLE',
                    'category_id' => 1,
                    'cost_price' => 10,
                    'selling_price' => 0,
                    'current_stock' => 10000,
                    'minimum_stock' => 2000,
                    'track_stock' => true,
                    'is_active' => true,
                ],
                [
                    'name' => 'Packaged Water 500ml',
                    'sku' => 'FIN-WATER-500',
                    'category_id' => 3,
                    'cost_price' => 15,
                    'selling_price' => 30,
                    'current_stock' => 5000,
                    'minimum_stock' => 1000,
                    'track_stock' => true,
                    'is_active' => true,
                ],
            ],
            'chart_of_accounts' => [
                // Assets
                ['code' => '1000', 'name' => 'Cash', 'type' => 'asset', 'parent_id' => null],
                ['code' => '1200', 'name' => 'Raw Materials Inventory', 'type' => 'asset', 'parent_id' => null],
                ['code' => '1210', 'name' => 'Work in Progress', 'type' => 'asset', 'parent_id' => null],
                ['code' => '1220', 'name' => 'Finished Goods', 'type' => 'asset', 'parent_id' => null],
                ['code' => '1500', 'name' => 'Machinery', 'type' => 'asset', 'parent_id' => null],
                // Liabilities
                ['code' => '2000', 'name' => 'Accounts Payable', 'type' => 'liability', 'parent_id' => null],
                ['code' => '2100', 'name' => 'VAT Payable', 'type' => 'liability', 'parent_id' => null],
                // Revenue
                ['code' => '4000', 'name' => 'Product Sales', 'type' => 'revenue', 'parent_id' => null],
                // Expenses
                ['code' => '5000', 'name' => 'Direct Materials', 'type' => 'expense', 'parent_id' => null],
                ['code' => '5100', 'name' => 'Direct Labor', 'type' => 'expense', 'parent_id' => null],
                ['code' => '5200', 'name' => 'Factory Overhead', 'type' => 'expense', 'parent_id' => null],
                ['code' => '5300', 'name' => 'Utilities', 'type' => 'expense', 'parent_id' => null],
            ],
            'settings' => [
                'default_tax_rate' => 16,
                'currency' => 'KES',
                'date_format' => 'd/m/Y',
                'fiscal_year_start' => '01-01',
                'batch_tracking' => true,
                'production_planning' => true,
            ],
            'reports' => [
                'production_summary',
                'material_consumption',
                'batch_cost_analysis',
                'inventory_valuation',
                'waste_report',
            ],
            'is_active' => true,
            'sort_order' => 4,
        ]);

        $this->command->info('✅ Industry templates seeded successfully!');
        $this->command->info('   - Retail Store');
        $this->command->info('   - Restaurant & Hospitality');
        $this->command->info('   - Wholesale & Distribution');
        $this->command->info('   - Light Manufacturing');
    }
}
