<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Suppliers
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('supplier_number')->unique();
            $table->string('company_name');
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('mobile', 20)->nullable();

            // Business details
            $table->string('kra_pin')->nullable();
            $table->string('vat_number')->nullable();
            $table->string('website')->nullable();

            // Address
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('county')->nullable();
            $table->string('country', 2)->default('KE');

            // Financial
            $table->decimal('credit_limit', 15, 2)->default(0);
            $table->decimal('outstanding_balance', 15, 2)->default(0);
            $table->integer('payment_terms_days')->default(30);
            $table->string('currency', 3)->default('KES');

            // Banking
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_branch')->nullable();

            // Rating & performance
            $table->decimal('rating', 3, 2)->default(5.00); // Out of 5
            $table->integer('on_time_delivery_rate')->default(100); // Percentage
            $table->integer('quality_score')->default(100);

            // Supplier portal access
            $table->boolean('portal_access')->default(false);
            $table->string('portal_email')->nullable();
            $table->timestamp('last_portal_login')->nullable();

            // Meta
            $table->enum('type', ['local', 'international'])->default('local');
            $table->enum('category', ['manufacturer', 'wholesaler', 'distributor', 'other'])->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_purchase_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('supplier_number');
        });

        // Supplier categories
        Schema::create('supplier_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('supplier_supplier_category', function (Blueprint $table) {
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_category_id')->constrained()->cascadeOnDelete();
            $table->primary(['supplier_id', 'supplier_category_id'], 'supplier_category_primary');
        });

        // Supplier product catalog (what they can supply)
        Schema::create('supplier_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();

            $table->string('supplier_sku')->nullable(); // Their product code
            $table->decimal('cost_price', 12, 2);
            $table->string('currency', 3)->default('KES');
            $table->integer('minimum_order_quantity')->default(1);
            $table->integer('lead_time_days')->default(1); // Delivery time
            $table->boolean('is_preferred')->default(false); // Preferred supplier for this product
            $table->boolean('is_available')->default(true);

            $table->timestamps();

            $table->unique(['supplier_id', 'product_id']);
        });

        // Purchase orders
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number')->unique();
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('location_id')->constrained(); // Delivery location
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');

            // Amounts
            $table->decimal('subtotal', 15, 2);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('shipping_cost', 15, 2)->default(0);
            $table->decimal('other_charges', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);

            // Status
            $table->enum('status', [
                'draft', 'pending_approval', 'approved', 'sent',
                'partially_received', 'received', 'cancelled', 'closed',
            ])->default('draft');

            // Dates
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->date('actual_delivery_date')->nullable();

            // Payment
            $table->enum('payment_method', ['cash', 'mpesa', 'bank_transfer', 'credit', 'cheque'])->nullable();
            $table->enum('payment_status', ['pending', 'partial', 'paid'])->default('pending');
            $table->decimal('paid_amount', 15, 2)->default(0);

            // Delivery
            $table->text('delivery_address')->nullable();
            $table->text('delivery_instructions')->nullable();

            // Meta
            $table->text('terms_conditions')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('po_number');
            $table->index('status');
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();

            $table->string('product_name'); // Snapshot
            $table->string('sku');
            $table->integer('quantity_ordered');
            $table->integer('quantity_received')->default(0);
            $table->integer('quantity_pending')->storedAs('quantity_ordered - quantity_received');

            $table->decimal('unit_cost', 12, 2);
            $table->decimal('tax_rate', 5, 2)->default(16.00);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2);

            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Goods received notes
        Schema::create('goods_received_notes', function (Blueprint $table) {
            $table->id();
            $table->string('grn_number')->unique();
            $table->foreignId('purchase_order_id')->constrained();
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('location_id')->constrained();
            $table->foreignId('received_by')->constrained('users');

            $table->date('received_date');
            $table->string('supplier_delivery_note')->nullable();
            $table->string('vehicle_number')->nullable();
            $table->string('driver_name')->nullable();

            $table->enum('status', ['pending', 'completed', 'partially_accepted', 'rejected'])->default('completed');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('goods_received_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grn_id')->constrained('goods_received_notes')->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained();
            $table->foreignId('product_id')->constrained();

            $table->integer('quantity_ordered');
            $table->integer('quantity_received');
            $table->integer('quantity_accepted');
            $table->integer('quantity_rejected')->default(0);

            $table->enum('condition', ['good', 'damaged', 'expired'])->default('good');
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Supplier bills/invoices
        Schema::create('supplier_bills', function (Blueprint $table) {
            $table->id();
            $table->string('bill_number')->unique(); // Our internal number
            $table->string('supplier_invoice_number')->nullable(); // Their invoice number
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('purchase_order_id')->nullable()->constrained();

            $table->decimal('subtotal', 15, 2);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('balance_amount', 15, 2)->storedAs('total_amount - paid_amount');

            $table->enum('status', ['draft', 'pending', 'approved', 'partial', 'paid', 'overdue'])->default('pending');
            $table->date('bill_date');
            $table->date('due_date');
            $table->timestamp('paid_at')->nullable();

            $table->string('bill_file')->nullable(); // Uploaded bill PDF
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Supplier bill payments
        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number')->unique();
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('supplier_bill_id')->nullable()->constrained();

            $table->decimal('amount', 15, 2);
            $table->enum('method', ['cash', 'mpesa', 'bank_transfer', 'cheque', 'card']);
            $table->string('reference')->nullable();
            $table->date('payment_date');

            $table->string('bank_account')->nullable();
            $table->string('cheque_number')->nullable();
            $table->string('mpesa_receipt')->nullable();

            $table->enum('status', ['pending', 'completed', 'failed', 'cancelled'])->default('completed');
            $table->foreignId('paid_by')->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Supplier ratings & reviews
        Schema::create('supplier_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained();
            $table->foreignId('rated_by')->constrained('users');

            $table->integer('quality_rating')->default(5); // 1-5
            $table->integer('delivery_rating')->default(5);
            $table->integer('price_rating')->default(5);
            $table->integer('communication_rating')->default(5);
            $table->decimal('overall_rating', 3, 2); // Calculated average

            $table->text('review')->nullable();
            $table->timestamps();
        });

        // Purchase requisitions (internal requests before PO)
        Schema::create('purchase_requisitions', function (Blueprint $table) {
            $table->id();
            $table->string('requisition_number')->unique();
            $table->foreignId('location_id')->constrained();
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');

            $table->enum('status', ['draft', 'pending_approval', 'approved', 'rejected', 'converted'])->default('draft');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');

            $table->date('required_by_date')->nullable();
            $table->foreignId('converted_po_id')->nullable()->constrained('purchase_orders');

            $table->text('justification')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_requisition_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_requisition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();

            $table->integer('quantity');
            $table->decimal('estimated_cost', 12, 2)->nullable();
            $table->foreignId('preferred_supplier_id')->nullable()->constrained('suppliers');
            $table->text('specification')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_requisition_items');
        Schema::dropIfExists('purchase_requisitions');
        Schema::dropIfExists('supplier_ratings');
        Schema::dropIfExists('supplier_payments');
        Schema::dropIfExists('supplier_bills');
        Schema::dropIfExists('goods_received_items');
        Schema::dropIfExists('goods_received_notes');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('supplier_products');
        Schema::dropIfExists('supplier_supplier_category');
        Schema::dropIfExists('supplier_categories');
        Schema::dropIfExists('suppliers');
    }
};
