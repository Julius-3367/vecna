<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'Perfect for getting started with basic ERP features',
                'price' => 0,
                'annual_price' => 0,
                'max_shops' => 1,
                'max_users' => 3,
                'max_transactions' => 50,
                'max_products' => 100,
                'max_locations' => 1,
                'pos_sync' => false,
                'mpesa_reconciliation' => false,
                'hr_module' => false,
                'crm_module' => false,
                'mobile_apps' => false,
                'ai_analytics' => false,
                'custom_modules' => false,
                'priority_support' => false,
                'white_label' => false,
                'transaction_overage_price' => 5.00,
                'location_overage_price' => 1500.00,
                'user_overage_price' => 500.00,
                'sort_order' => 1,
                'is_active' => true,
                'is_featured' => false,
                'is_popular' => false,
            ],
            [
                'name' => 'Business',
                'slug' => 'business',
                'description' => 'For growing businesses with multiple locations',
                'price' => 6000,
                'annual_price' => 60000, // 17% discount (2 months free)
                'max_shops' => 3,
                'max_users' => 15,
                'max_transactions' => 500,
                'max_products' => 1000,
                'max_locations' => 3,
                'pos_sync' => true,
                'mpesa_reconciliation' => true,
                'hr_module' => false,
                'crm_module' => true,
                'mobile_apps' => false,
                'ai_analytics' => false,
                'custom_modules' => false,
                'priority_support' => false,
                'white_label' => false,
                'transaction_overage_price' => 5.00,
                'location_overage_price' => 1500.00,
                'user_overage_price' => 500.00,
                'sort_order' => 2,
                'is_active' => true,
                'is_featured' => false,
                'is_popular' => true,
            ],
            [
                'name' => 'Professional',
                'slug' => 'professional',
                'description' => 'Complete solution for established businesses',
                'price' => 12000,
                'annual_price' => 120000, // 17% discount
                'max_shops' => 10,
                'max_users' => 50,
                'max_transactions' => 2000,
                'max_products' => 10000,
                'max_locations' => 10,
                'pos_sync' => true,
                'mpesa_reconciliation' => true,
                'hr_module' => true,
                'crm_module' => true,
                'mobile_apps' => true,
                'ai_analytics' => true,
                'custom_modules' => false,
                'priority_support' => true,
                'white_label' => false,
                'transaction_overage_price' => 5.00,
                'location_overage_price' => 1500.00,
                'user_overage_price' => 500.00,
                'sort_order' => 3,
                'is_active' => true,
                'is_featured' => true,
                'is_popular' => false,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'Unlimited power for large organizations',
                'price' => 25000,
                'annual_price' => 250000, // 17% discount
                'max_shops' => 999999,
                'max_users' => 999999,
                'max_transactions' => 999999,
                'max_products' => 999999,
                'max_locations' => 999999,
                'pos_sync' => true,
                'mpesa_reconciliation' => true,
                'hr_module' => true,
                'crm_module' => true,
                'mobile_apps' => true,
                'ai_analytics' => true,
                'custom_modules' => true,
                'priority_support' => true,
                'white_label' => true,
                'transaction_overage_price' => 0,
                'location_overage_price' => 0,
                'user_overage_price' => 0,
                'sort_order' => 4,
                'is_active' => true,
                'is_featured' => false,
                'is_popular' => false,
            ],
        ];

        foreach ($plans as $planData) {
            Plan::create($planData);
        }

        $this->command->info('✅ Subscription plans seeded successfully!');
    }
}
