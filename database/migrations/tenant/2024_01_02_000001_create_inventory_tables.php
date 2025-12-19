<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Product categories
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('image')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Product brands
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('logo')->nullable();
            $table->string('website')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Units of measurement
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Piece, Kilogram, Liter, etc
            $table->string('short_name'); // Pcs, Kg, L
            $table->string('type')->default('quantity'); // quantity, weight, volume, length
            $table->timestamps();
        });

        // Products
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('sku')->unique();
            $table->string('barcode')->nullable()->unique();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->constrained();

            // Pricing
            $table->decimal('cost_price', 12, 2)->default(0); // Purchase price
            $table->decimal('selling_price', 12, 2)->default(0); // Retail price
            $table->decimal('wholesale_price', 12, 2)->nullable(); // Bulk price
            $table->decimal('minimum_price', 12, 2)->nullable(); // Floor price
            $table->boolean('tax_inclusive')->default(true);
            $table->decimal('tax_rate', 5, 2)->default(16.00); // 16% VAT in Kenya

            // Inventory tracking
            $table->boolean('track_stock')->default(true);
            $table->integer('stock_quantity')->default(0); // Total across all locations
            $table->integer('reorder_level')->default(10);
            $table->integer('reorder_quantity')->default(50);
            $table->integer('minimum_order_quantity')->default(1);
            $table->integer('maximum_order_quantity')->nullable();

            // Physical attributes
            $table->decimal('weight', 10, 2)->nullable(); // In kg
            $table->decimal('length', 10, 2)->nullable(); // In cm
            $table->decimal('width', 10, 2)->nullable();
            $table->decimal('height', 10, 2)->nullable();

            // Product type
            $table->enum('type', ['simple', 'variable', 'service', 'bundle'])->default('simple');
            $table->boolean('is_perishable')->default(false);
            $table->integer('shelf_life_days')->nullable();

            // Media
            $table->string('image')->nullable();
            $table->json('images')->nullable(); // Additional images

            // Meta
            $table->json('metadata')->nullable(); // Custom fields
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['sku', 'barcode']);
            $table->index('category_id');
            $table->index('is_active');
        });

        // Product variants (for variable products like sizes, colors)
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // e.g., "Red - Large"
            $table->string('sku')->unique();
            $table->string('barcode')->nullable()->unique();
            $table->decimal('price_adjustment', 10, 2)->default(0); // Difference from base price
            $table->integer('stock_quantity')->default(0);
            $table->json('attributes'); // {"color": "red", "size": "L"}
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Stock per location
        Schema::create('stock_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(0);
            $table->integer('reserved_quantity')->default(0); // Pending orders
            $table->integer('available_quantity')->storedAs('quantity - reserved_quantity');
            $table->timestamps();

            $table->unique(['product_id', 'location_id']);
        });

        // Stock movements (audit trail)
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', [
                'purchase', 'sale', 'adjustment', 'transfer_in', 'transfer_out',
                'return', 'damage', 'theft', 'expired', 'opening_balance',
            ]);
            $table->integer('quantity'); // Positive for in, negative for out
            $table->integer('quantity_before');
            $table->integer('quantity_after');
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->string('reference_type')->nullable(); // Sale, Purchase, etc
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->constrained();
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        // Stock transfers between locations
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_number')->unique();
            $table->foreignId('from_location_id')->constrained('locations');
            $table->foreignId('to_location_id')->constrained('locations');
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->enum('status', ['pending', 'approved', 'in_transit', 'completed', 'cancelled'])->default('pending');
            $table->date('transfer_date');
            $table->date('expected_arrival_date')->nullable();
            $table->date('actual_arrival_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->integer('quantity');
            $table->integer('received_quantity')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Stock adjustments (for corrections)
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('adjustment_number')->unique();
            $table->foreignId('location_id')->constrained();
            $table->enum('reason', ['stock_count', 'damaged', 'expired', 'lost', 'found', 'correction', 'other']);
            $table->text('notes')->nullable();
            $table->foreignId('adjusted_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('stock_adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_adjustment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->integer('system_quantity'); // What system thinks
            $table->integer('actual_quantity'); // What was counted
            $table->integer('difference'); // Calculated
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Product bundles (combo products)
        Schema::create('product_bundles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bundle_product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('component_product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('quantity')->default(1); // How many of component in bundle
            $table->timestamps();
        });

        // Low stock alerts
        Schema::create('stock_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('location_id')->nullable()->constrained();
            $table->enum('type', ['low_stock', 'out_of_stock', 'expiring_soon', 'expired']);
            $table->string('message');
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['is_read', 'created_at']);
        });

        // Product pricing history
        Schema::create('price_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('old_price', 12, 2);
            $table->decimal('new_price', 12, 2);
            $table->string('price_type'); // selling_price, cost_price, wholesale_price
            $table->foreignId('changed_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_history');
        Schema::dropIfExists('stock_alerts');
        Schema::dropIfExists('product_bundles');
        Schema::dropIfExists('stock_adjustment_items');
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_locations');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('units');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('categories');
    }
};
