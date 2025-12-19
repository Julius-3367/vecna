<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Central database for tenant management
     */
    public function up(): void
    {
        // Tenants table - stores all registered businesses
        Schema::create('tenants', function (Blueprint $table) {
            $table->string('id')->primary(); // UUID
            $table->string('business_name');
            $table->string('subdomain')->unique();
            $table->string('custom_domain')->nullable()->unique();
            $table->string('email')->unique();
            $table->string('phone', 20);
            $table->enum('industry', [
                'retail', 'wholesale', 'hospitality', 'manufacturing',
                'services', 'healthcare', 'logistics', 'other',
            ])->default('retail');
            $table->string('country', 2)->default('KE');
            $table->string('currency', 3)->default('KES');
            $table->string('timezone')->default('Africa/Nairobi');
            $table->string('language', 5)->default('en');

            // Subscription & billing
            $table->foreignId('plan_id')->nullable()->constrained('subscription_plans');
            $table->enum('status', ['active', 'suspended', 'cancelled', 'trial'])->default('trial');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->text('suspension_reason')->nullable();

            // Onboarding
            $table->string('industry_template')->nullable();

            // Tenancy package data column
            $table->json('data')->nullable();

            // Metadata
            $table->json('settings')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('plan_id');
        });

        // Subscription plans
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Starter, Business, Professional, Enterprise
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2); // Monthly price in KES
            $table->decimal('annual_price', 10, 2)->nullable(); // Annual price with discount

            // Limits
            $table->integer('max_shops')->default(1);
            $table->integer('max_users')->default(3);
            $table->integer('max_transactions')->default(50); // Per month
            $table->integer('max_products')->nullable();
            $table->integer('max_storage_gb')->default(1);

            // Features
            $table->json('features'); // Array of enabled features
            $table->boolean('pos_sync')->default(false);
            $table->boolean('mobile_apps')->default(false);
            $table->boolean('api_access')->default(false);
            $table->boolean('whatsapp_integration')->default(false);
            $table->boolean('priority_support')->default(false);
            $table->boolean('custom_modules')->default(false);

            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Usage tracking for billing
        Schema::create('usage_records', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('metric'); // transactions, storage, sms, api_calls
            $table->integer('quantity');
            $table->date('period'); // YYYY-MM-DD
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index(['tenant_id', 'period', 'metric']);
        });

        // Payments
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('KES');
            $table->enum('method', ['mpesa', 'stripe', 'bank_transfer', 'cash']);
            $table->string('transaction_id')->nullable();
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])->default('pending');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });

        // Platform admin users (super admin)
        Schema::create('admin_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->enum('role', ['super_admin', 'support', 'finance'])->default('support');
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        // Activity logs (audit trail)
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable();
            $table->string('user_type')->nullable(); // Admin, User
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('event'); // created, updated, deleted, login, etc
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->text('old_values')->nullable();
            $table->text('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['auditable_type', 'auditable_id']);
        });

        // System notifications
        Schema::create('system_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable(); // Null for all tenants
            $table->string('title');
            $table->text('message');
            $table->enum('type', ['info', 'warning', 'error', 'success'])->default('info');
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_dismissible')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_notifications');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('admin_users');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('usage_records');
        Schema::dropIfExists('tenants');
        Schema::dropIfExists('subscription_plans');
    }
};
