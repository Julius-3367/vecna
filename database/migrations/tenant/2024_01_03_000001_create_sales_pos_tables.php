<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Customers
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_number')->unique();
            $table->enum('type', ['individual', 'business'])->default('individual');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('mobile', 20)->nullable();

            // Business details
            $table->string('company_name')->nullable();
            $table->string('kra_pin')->nullable();
            $table->string('vat_number')->nullable();

            // Address
            $table->text('billing_address')->nullable();
            $table->text('shipping_address')->nullable();
            $table->string('city')->nullable();
            $table->string('county')->nullable();
            $table->string('country', 2)->default('KE');

            // Financial
            $table->decimal('credit_limit', 12, 2)->default(0);
            $table->decimal('outstanding_balance', 12, 2)->default(0);
            $table->integer('credit_days')->default(0); // Payment terms
            $table->enum('payment_terms', ['cash', 'credit', 'advance'])->default('cash');

            // Loyalty
            $table->integer('loyalty_points')->default(0);
            $table->enum('tier', ['regular', 'silver', 'gold', 'platinum'])->default('regular');

            // Contact person (for business)
            $table->string('contact_person')->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->string('contact_email')->nullable();

            // Meta
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_purchase_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('customer_number');
            $table->index(['phone', 'mobile']);
        });

        // Customer addresses (multiple shipping addresses)
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('label')->default('Home'); // Home, Office, etc
            $table->string('address');
            $table->string('city')->nullable();
            $table->string('county')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('phone', 20)->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        // Sales
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('sale_number')->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('location_id')->constrained();
            $table->foreignId('user_id')->constrained(); // Salesperson

            // Amounts
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('shipping_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('balance_amount', 12, 2)->storedAs('total_amount - paid_amount');

            // Discount
            $table->enum('discount_type', ['fixed', 'percentage'])->nullable();
            $table->decimal('discount_value', 10, 2)->nullable();

            // Payment
            $table->enum('payment_status', ['pending', 'partial', 'paid', 'overdue'])->default('pending');
            $table->enum('payment_method', ['cash', 'mpesa', 'bank_transfer', 'card', 'credit', 'mixed'])->nullable();

            // Order status
            $table->enum('status', ['draft', 'confirmed', 'processing', 'completed', 'cancelled', 'refunded'])->default('confirmed');
            $table->enum('fulfillment_status', ['pending', 'processing', 'shipped', 'delivered', 'cancelled'])->default('pending');

            // Dates
            $table->timestamp('sale_date');
            $table->timestamp('due_date')->nullable();
            $table->timestamp('fulfilled_at')->nullable();

            // Delivery
            $table->foreignId('shipping_address_id')->nullable()->constrained('customer_addresses')->nullOnDelete();
            $table->string('delivery_method')->nullable();
            $table->text('delivery_notes')->nullable();

            // Source
            $table->enum('channel', ['pos', 'web', 'mobile', 'phone', 'whatsapp'])->default('pos');
            $table->string('pos_terminal_id')->nullable();

            // Meta
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('sale_number');
            $table->index(['customer_id', 'sale_date']);
            $table->index('status');
            $table->index('payment_status');
        });

        // Sale items
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();

            $table->string('product_name'); // Snapshot at time of sale
            $table->string('sku');
            $table->integer('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('unit_cost', 12, 2); // For profit calculation
            $table->decimal('tax_rate', 5, 2)->default(16.00);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2); // quantity * unit_price - discount + tax

            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Payments
        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->string('payment_number')->unique();
            $table->decimal('amount', 12, 2);
            $table->enum('method', ['cash', 'mpesa', 'bank_transfer', 'card', 'cheque', 'credit_note']);

            // M-Pesa specific
            $table->string('mpesa_receipt')->nullable();
            $table->string('mpesa_transaction_id')->nullable();
            $table->string('mpesa_phone')->nullable();

            // Bank/Card specific
            $table->string('bank_name')->nullable();
            $table->string('cheque_number')->nullable();
            $table->string('card_last_four')->nullable();
            $table->string('transaction_reference')->nullable();

            $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])->default('completed');
            $table->timestamp('payment_date');
            $table->text('notes')->nullable();
            $table->foreignId('received_by')->constrained('users');
            $table->timestamps();

            $table->index('mpesa_receipt');
        });

        // Quotations (before becoming sales)
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('quotation_number')->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('location_id')->constrained();
            $table->foreignId('user_id')->constrained();

            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2);

            $table->enum('status', ['draft', 'sent', 'accepted', 'rejected', 'expired', 'converted'])->default('draft');
            $table->date('quotation_date');
            $table->date('valid_until');
            $table->foreignId('converted_sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->timestamp('converted_at')->nullable();

            $table->text('terms_conditions')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->string('product_name');
            $table->integer('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('tax_rate', 5, 2)->default(16.00);
            $table->decimal('line_total', 12, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Invoices (for credit sales)
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('sale_id')->constrained();
            $table->foreignId('customer_id')->constrained();

            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax_amount', 12, 2);
            $table->decimal('total_amount', 12, 2);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('balance_amount', 12, 2)->storedAs('total_amount - paid_amount');

            $table->enum('status', ['draft', 'sent', 'partial', 'paid', 'overdue', 'cancelled'])->default('draft');
            $table->date('invoice_date');
            $table->date('due_date');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            // KRA eTIMS/iTax
            $table->string('kra_cu_invoice_no')->nullable(); // Control unit invoice number
            $table->string('kra_cu_serial_no')->nullable();
            $table->string('kra_qr_code')->nullable();
            $table->timestamp('kra_submitted_at')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Returns/Refunds
        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number')->unique();
            $table->foreignId('sale_id')->constrained();
            $table->foreignId('location_id')->constrained();
            $table->foreignId('processed_by')->constrained('users');

            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2);
            $table->decimal('refunded_amount', 12, 2)->default(0);

            $table->enum('status', ['pending', 'approved', 'completed', 'rejected'])->default('pending');
            $table->enum('refund_method', ['cash', 'mpesa', 'credit_note', 'exchange'])->nullable();
            $table->string('refund_reference')->nullable();

            $table->timestamp('return_date');
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained();
            $table->foreignId('product_id')->constrained();

            $table->integer('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->enum('condition', ['good', 'damaged', 'expired'])->default('good');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // POS terminals/devices
        Schema::create('pos_terminals', function (Blueprint $table) {
            $table->id();
            $table->string('terminal_id')->unique();
            $table->string('name');
            $table->foreignId('location_id')->constrained();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('device_type')->nullable(); // tablet, desktop, mobile
            $table->string('ip_address', 45)->nullable();
            $table->string('mac_address')->nullable();

            $table->decimal('opening_cash', 12, 2)->default(0);
            $table->decimal('closing_cash', 12, 2)->nullable();
            $table->boolean('is_online')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();

            $table->json('settings')->nullable(); // Printer config, etc
            $table->timestamps();
        });

        // Cash register sessions
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_number')->unique();
            $table->foreignId('pos_terminal_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('location_id')->constrained();

            $table->decimal('opening_balance', 12, 2);
            $table->decimal('closing_balance', 12, 2)->nullable();
            $table->decimal('expected_balance', 12, 2)->nullable();
            $table->decimal('difference', 12, 2)->nullable();

            $table->decimal('total_sales', 12, 2)->default(0);
            $table->decimal('total_cash', 12, 2)->default(0);
            $table->decimal('total_mpesa', 12, 2)->default(0);
            $table->decimal('total_card', 12, 2)->default(0);
            $table->decimal('total_refunds', 12, 2)->default(0);

            $table->integer('transactions_count')->default(0);

            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Receipts (for printing)
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number')->unique();
            $table->foreignId('sale_id')->constrained();
            $table->foreignId('pos_terminal_id')->nullable()->constrained();

            $table->text('header_text')->nullable();
            $table->text('footer_text')->nullable();
            $table->string('qr_code')->nullable(); // For KRA compliance
            $table->boolean('is_printed')->default(false);
            $table->boolean('is_emailed')->default(false);
            $table->timestamp('printed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('cash_sessions');
        Schema::dropIfExists('pos_terminals');
        Schema::dropIfExists('sale_return_items');
        Schema::dropIfExists('sale_returns');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
        Schema::dropIfExists('sale_payments');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('customers');
    }
};
