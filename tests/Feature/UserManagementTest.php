<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_employee_cannot_open_user_list(): void
    {
        $employee = $this->createUser('employee');

        Sanctum::actingAs($employee);

        $this->getJson('/api/users')
            ->assertForbidden()
            ->assertJsonPath('message', 'У вас нет доступа к этому действию.');
    }

    public function test_admin_can_change_user_role(): void
    {
        $admin = $this->createUser('admin');
        $employee = $this->createUser('employee');
        $managerRole = Role::query()->where('name', 'office_manager')->firstOrFail();

        Sanctum::actingAs($admin);

        $this->getJson('/api/roles')
            ->assertOk()
            ->assertJsonFragment(['name' => 'office_manager']);

        $response = $this->patchJson('/api/users/'.$employee->id.'/role', [
            'role_id' => $managerRole->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('role.name', 'office_manager');

        $this->assertDatabaseHas('users', [
            'id' => $employee->id,
            'role_id' => $managerRole->id,
        ]);
    }

    public function test_admin_can_view_user_list_and_single_user(): void
    {
        $admin = $this->createUser('admin');
        $employee = $this->createUser('employee');

        Sanctum::actingAs($admin);

        $this->getJson('/api/users')
            ->assertOk()
            ->assertJsonFragment(['email' => $employee->email]);

        $this->getJson('/api/users/'.$employee->id)
            ->assertOk()
            ->assertJsonPath('id', $employee->id);
    }

    public function test_admin_cannot_delete_self(): void
    {
        $admin = $this->createUser('admin');

        Sanctum::actingAs($admin);

        $this->deleteJson('/api/users/'.$admin->id)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Нельзя удалить самого себя.');
    }

    private function createUser(string $roleName): User
    {
        $role = Role::query()->where('name', $roleName)->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
        ]);
    }
}
