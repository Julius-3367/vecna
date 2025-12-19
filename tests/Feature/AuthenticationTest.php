<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '254712345678',
            'business_name' => 'Test Business Ltd',
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
    }

    public function test_user_can_login(): void
    {
        // Create tenant first
        $tenant = Tenant::create([
            'id' => 'test-tenant',
            'business_name' => 'Test Tenant',
            'subdomain' => 'test',
            'email' => 'test@tenant.com',
            'phone' => '254712345678',
            'status' => 'active',
        ]);

        $tenant->run(function () {
            $user = User::factory()->create([
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
        $tenant = Tenant::create([
            'id' => 'test-tenant-2',
            'business_name' => 'Test Tenant',
            'subdomain' => 'test2',
            'email' => 'test2@tenant.com',
            'phone' => '254712345678',
            'status' => 'active',
        ]);

        $tenant->run(function () {
            User::factory()->create([
                'email' => 'test@example.com',
                'password' => bcrypt('password123'),
            ]);

            $response = $this->postJson('/api/v1/login', [
                'email' => 'test@example.com',
                'password' => 'wrong-password',
            ]);

            $response->assertStatus(401);
        });
    }

    public function test_authenticated_user_can_logout(): void
    {
        $tenant = Tenant::create([
            'id' => 'test-tenant-3',
            'business_name' => 'Test Tenant',
            'subdomain' => 'test3',
            'email' => 'test3@tenant.com',
            'phone' => '254712345678',
            'status' => 'active',
        ]);

        $tenant->run(function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user, 'sanctum')
                ->postJson('/api/v1/logout');

            $response->assertStatus(200);
        });
    }
}
