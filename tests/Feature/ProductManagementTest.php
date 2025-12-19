<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        // Create tenant and run migrations
        $this->tenant = Tenant::create([
            'id' => 'product-test-tenant',
            'business_name' => 'Product Test Tenant',
            'subdomain' => 'producttest',
            'email' => 'product@tenant.com',
            'phone' => '254712345678',
            'status' => 'active',
        ]);
        
        // Run tenant migrations
        $this->artisan('tenants:migrate', ['--tenants' => [$this->tenant->id]]);
    }

    public function test_user_can_create_product(): void
    {
        $this->tenant->run(function () {
            $user = User::factory()->create();
            $category = Category::factory()->create();
            $brand = Brand::factory()->create();
            $unit = Unit::factory()->create();

            $response = $this->actingAs($user, 'sanctum')
                ->postJson('/api/v1/products', [
                    'name' => 'Test Product',
                    'sku' => 'TEST-001',
                    'category_id' => $category->id,
                    'brand_id' => $brand->id,
                    'unit_id' => $unit->id,
                    'cost_price' => 100,
                    'selling_price' => 150,
                    'stock_quantity' => 50,
                    'reorder_level' => 10,
                    'track_stock' => true,
                ]);

            $response->assertStatus(201)
                ->assertJsonStructure([
                    'data' => ['id', 'name', 'sku', 'selling_price'],
                ]);

            $this->assertDatabaseHas('products', [
                'name' => 'Test Product',
                'sku' => 'TEST-001',
            ]);
        });
    }

    public function test_user_can_list_products(): void
    {
        $this->tenant->run(function () {
            $user = User::factory()->create();
            Product::factory()->count(5)->create();

            $response = $this->actingAs($user, 'sanctum')
                ->getJson('/api/v1/products');

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        '*' => ['id', 'name', 'sku', 'selling_price'],
                    ],
                ]);
        });
    }

    public function test_user_can_update_product_stock(): void
    {
        $this->tenant->run(function () {
            $user = User::factory()->create();
            $product = Product::factory()->create([
                'stock_quantity' => 100,
            ]);

            $response = $this->actingAs($user, 'sanctum')
                ->postJson("/api/v1/products/{$product->id}/stock", [
                    'quantity' => 50,
                    'type' => 'in',
                    'reference' => 'Stock purchase',
                ]);

            $response->assertStatus(200);

            $product->refresh();
            $this->assertEquals(150, $product->stock_quantity);
        });
    }

    public function test_low_stock_products_are_identified(): void
    {
        $this->tenant->run(function () {
            $user = User::factory()->create();

            Product::factory()->create([
                'stock_quantity' => 5,
                'reorder_level' => 10,
            ]);

            Product::factory()->create([
                'stock_quantity' => 50,
                'reorder_level' => 10,
            ]);

            $response = $this->actingAs($user, 'sanctum')
                ->getJson('/api/v1/products/low-stock');

            $response->assertStatus(200)
                ->assertJsonCount(1, 'data');
        });
    }
}
