<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a test tenant
        $this->tenant = Tenant::create([
            'id' => 'test-tenant',
            'business_name' => 'Test Business Ltd',
            'subdomain' => 'test',
            'email' => 'admin@test.com',
            'phone' => '254712345678',
            'status' => 'active',
        ]);
        
        // Run tenant migrations
        $this->artisan('tenants:migrate', ['--tenants' => [$this->tenant->id]]);
    }

    public function test_user_can_register_within_tenant(): void
    {
        $this->tenant->run(function () {
            $response = $this->postJson('/api/v1/register', [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'phone' => '254712345678',
            ]);

            $response->assertStatus(201)
                ->assertJsonStructure([
                    'data' => [
                        'user' => ['id', 'name', 'email'],
                        'token',
                    ],
                ]);

            $this->assertDatabaseHas('users', [
                'email' => 'test@example.com',
            ]);
        });
    }

    public function test_user_can_login(): void
    {
        $this->tenant->run(function () {
            User::factory()->create([
                'email' => 'test@example.com',
                'password' => bcrypt('password123'),
            ]);

            $response = $this->postJson('/api/v1/login', [
                'email' => 'test@example.com',
                'password' => 'password123',
            ]);

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'user' => ['id', 'name', 'email'],
                        'token',
                    ],
                ]);
        });
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        $this->tenant->run(function () {
            User::factory()->create([
                'email' => 'test@example.com',
                'password' => bcrypt('password123'),
            ]);

            $response = $this->postJson('/api/v1/login', [
                'email' => 'test@example.com',
                'password' => 'wrong-password',
            ]);

            $response->assertStatus(422);
        });
    }

    public function test_authenticated_user_can_logout(): void
    {
        $this->tenant->run(function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user, 'sanctum')
                ->postJson('/api/v1/logout');

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Logged out successfully',
                ]);
        });
    }
}
