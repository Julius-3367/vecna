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
        Schema::create('industry_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Retail, Hospitality, Wholesale, Manufacturing
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('icon')->nullable();

            // Template configuration
            $table->json('categories')->nullable(); // Pre-configured categories
            $table->json('products')->nullable(); // Sample products
            $table->json('chart_of_accounts')->nullable(); // Account structure
            $table->json('settings')->nullable(); // Default settings
            $table->json('reports')->nullable(); // Available reports

            // Metadata
            $table->boolean('is_active')->default(true);
            $table->integer('usage_count')->default(0);
            $table->integer('sort_order')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('industry_templates');
    }
};
