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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('plan_id')->constrained('plans')->onDelete('restrict');

            // Subscription details
            $table->string('status'); // active, cancelled, expired, past_due, trialing
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            // Billing
            $table->string('billing_period')->default('monthly'); // monthly, annual
            $table->decimal('amount', 10, 2); // Subscription amount
            $table->string('payment_method')->nullable(); // mpesa, stripe, bank_transfer
            $table->string('payment_reference')->nullable(); // M-Pesa transaction ID, Stripe subscription ID

            // Auto-renewal
            $table->boolean('auto_renew')->default(true);
            $table->timestamp('next_billing_date')->nullable();

            // Usage tracking for current period
            $table->integer('current_period_transactions')->default(0);
            $table->integer('current_period_shops')->default(0);
            $table->integer('current_period_users')->default(0);

            // Overage charges
            $table->decimal('overage_amount', 10, 2)->default(0);
            $table->json('overage_details')->nullable(); // Breakdown of overages

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index('next_billing_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
