<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_user_can_register_and_get_employee_role(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Иван',
            'email' => 'ivan@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'token',
                'user' => ['id', 'name', 'email', 'role'],
            ])
            ->assertJsonPath('user.role.name', 'employee');

        $this->assertDatabaseHas('users', [
            'email' => 'ivan@example.com',
        ]);
    }

    public function test_user_can_login(): void
    {
        $employeeRole = Role::query()->where('name', 'employee')->firstOrFail();

        User::factory()->create([
            'email' => 'employee@example.com',
            'role_id' => $employeeRole->id,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'employee@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'token',
                'user' => ['id', 'name', 'email', 'role'],
            ])
            ->assertJsonPath('user.role.name', 'employee');
    }

    public function test_login_returns_unauthorized_for_wrong_password(): void
    {
        $employeeRole = Role::query()->where('name', 'employee')->firstOrFail();

        User::factory()->create([
            'email' => 'employee@example.com',
            'role_id' => $employeeRole->id,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'employee@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertUnauthorized()
            ->assertJsonPath('message', 'Неверный email или пароль.');
    }
}
