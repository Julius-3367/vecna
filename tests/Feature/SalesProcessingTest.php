<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesProcessingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'business_name' => 'Test Tenant',
            'subdomain' => 'test',
            'email' => 'test@tenant.com',
            'phone' => '254712345678',
            'status' => 'active',
        ]);
    }

    public function test_user_can_create_sale(): void
    {
        $this->tenant->run(function () {
            $user = User::factory()->create(['role' => 'admin']);
            $customer = Customer::factory()->create();
            $product = Product::factory()->create([
                'selling_price' => 100,
                'current_stock' => 50,
            ]);

            $response = $this->actingAs($user, 'sanctum')
                ->postJson('/api/sales', [
                    'customer_id' => $customer->id,
                    'items' => [
                        [
                            'product_id' => $product->id,
                            'quantity' => 2,
                            'unit_price' => 100,
                        ],
                    ],
                    'payment_method' => 'cash',
                ]);

            $response->assertStatus(201)
                ->assertJsonStructure([
                    'data' => ['id', 'sale_number', 'total_amount'],
                ]);

            $this->assertDatabaseHas('sales', [
                'customer_id' => $customer->id,
                'status' => 'completed',
            ]);

            // Check stock was reduced
            $product->refresh();
            $this->assertEquals(48, $product->current_stock);
        });
    }

    public function test_sale_calculates_total_correctly(): void
    {
        $this->tenant->run(function () {
            $user = User::factory()->create(['role' => 'admin']);
            $customer = Customer::factory()->create();
            $product1 = Product::factory()->create(['selling_price' => 100, 'current_stock' => 50]);
            $product2 = Product::factory()->create(['selling_price' => 200, 'current_stock' => 30]);

            $response = $this->actingAs($user, 'sanctum')
                ->postJson('/api/sales', [
                    'customer_id' => $customer->id,
                    'items' => [
                        ['product_id' => $product1->id, 'quantity' => 2, 'unit_price' => 100],
                        ['product_id' => $product2->id, 'quantity' => 1, 'unit_price' => 200],
                    ],
                    'payment_method' => 'cash',
                ]);

            $response->assertStatus(201);

            $sale = Sale::first();
            $this->assertEquals(400, $sale->total_amount); // (2 * 100) + (1 * 200)
        });
    }

    public function test_sale_cannot_exceed_available_stock(): void
    {
        $this->tenant->run(function () {
            $user = User::factory()->create(['role' => 'admin']);
            $customer = Customer::factory()->create();
            $product = Product::factory()->create([
                'selling_price' => 100,
                'current_stock' => 5,
            ]);

            $response = $this->actingAs($user, 'sanctum')
                ->postJson('/api/sales', [
                    'customer_id' => $customer->id,
                    'items' => [
                        [
                            'product_id' => $product->id,
                            'quantity' => 10,
                            'unit_price' => 100,
                        ],
                    ],
                    'payment_method' => 'cash',
                ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['items.0.quantity']);
        });
    }

    public function test_mpesa_payment_creates_pending_sale(): void
    {
        $this->tenant->run(function () {
            $user = User::factory()->create(['role' => 'admin']);
            $customer = Customer::factory()->create();
            $product = Product::factory()->create([
                'selling_price' => 100,
                'current_stock' => 50,
            ]);

            $response = $this->actingAs($user, 'sanctum')
                ->postJson('/api/sales', [
                    'customer_id' => $customer->id,
                    'items' => [
                        ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 100],
                    ],
                    'payment_method' => 'mpesa',
                    'phone' => '254712345678',
                ]);

            $response->assertStatus(201);

            $sale = Sale::first();
            $this->assertEquals('pending', $sale->payment_status);
        });
    }
}
