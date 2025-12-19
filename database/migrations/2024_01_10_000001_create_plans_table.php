<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Starter, Business, Professional, Enterprise
            $table->string('slug')->unique(); // starter, business, professional, enterprise
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2); // Monthly price in KES
            $table->decimal('annual_price', 10, 2)->nullable(); // Annual price (17% discount)
            $table->string('billing_period')->default('monthly'); // monthly, annual

            // Limits
            $table->integer('max_shops')->default(1);
            $table->integer('max_users')->default(3);
            $table->integer('max_transactions')->default(50); // per month
            $table->integer('max_products')->default(100);
            $table->integer('max_locations')->default(1);

            // Feature flags
            $table->json('features')->nullable(); // Array of enabled features
            $table->boolean('pos_sync')->default(false);
            $table->boolean('mpesa_reconciliation')->default(false);
            $table->boolean('hr_module')->default(false);
            $table->boolean('crm_module')->default(false);
            $table->boolean('mobile_apps')->default(false);
            $table->boolean('ai_analytics')->default(false);
            $table->boolean('custom_modules')->default(false);
            $table->boolean('priority_support')->default(false);
            $table->boolean('white_label')->default(false);

            // Overage pricing
            $table->decimal('transaction_overage_price', 10, 2)->default(5.00); // KES per transaction
            $table->decimal('location_overage_price', 10, 2)->default(1500.00); // KES per location
            $table->decimal('user_overage_price', 10, 2)->default(500.00); // KES per user

            // Display
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_popular')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
