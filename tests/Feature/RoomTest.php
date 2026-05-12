<?php

namespace Tests\Feature;

use App\Models\Amenity;
use App\Models\Booking;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoomTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_guest_sees_only_free_active_rooms_for_selected_time(): void
    {
        $projector = Amenity::query()->create(['name' => 'Проектор']);

        $freeRoom = Room::query()->create([
            'name' => 'Свободная переговорка',
            'capacity' => 8,
            'floor' => 2,
            'description' => 'Комната свободна',
            'is_active' => true,
        ]);
        $freeRoom->amenities()->sync([$projector->id]);

        $busyRoom = Room::query()->create([
            'name' => 'Занятая переговорка',
            'capacity' => 8,
            'floor' => 2,
            'description' => 'Комната занята',
            'is_active' => true,
        ]);
        $busyRoom->amenities()->sync([$projector->id]);

        $inactiveRoom = Room::query()->create([
            'name' => 'Комната на ремонте',
            'capacity' => 8,
            'floor' => 2,
            'description' => 'Сейчас недоступна',
            'is_active' => false,
        ]);
        $inactiveRoom->amenities()->sync([$projector->id]);

        $employee = $this->createUser('employee');

        Booking::query()->create([
            'user_id' => $employee->id,
            'room_id' => $busyRoom->id,
            'title' => 'Встреча',
            'start_time' => '2030-04-11 10:00:00',
            'end_time' => '2030-04-11 11:00:00',
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/rooms?start_time=2030-04-11%2010:15:00&end_time=2030-04-11%2010:45:00&capacity=6&floor=2&amenity_ids[0]='.$projector->id);

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'Свободная переговорка');
    }

    public function test_office_manager_can_create_room_with_amenities(): void
    {
        $manager = $this->createUser('office_manager');
        $projector = Amenity::query()->create(['name' => 'Проектор']);
        $board = Amenity::query()->create(['name' => 'Доска']);

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/rooms', [
            'name' => 'Новая комната',
            'capacity' => 10,
            'floor' => 3,
            'description' => 'Тестовая комната',
            'is_active' => true,
            'amenity_ids' => [$projector->id, $board->id],
        ]);

        $response->assertCreated()
            ->assertJsonPath('name', 'Новая комната');

        $this->assertDatabaseHas('rooms', [
            'name' => 'Новая комната',
        ]);
    }

    public function test_employee_cannot_create_room(): void
    {
        $employee = $this->createUser('employee');

        Sanctum::actingAs($employee);

        $this->postJson('/api/rooms', [
            'name' => 'Закрытая комната',
            'capacity' => 8,
            'floor' => 2,
        ])->assertForbidden();
    }

    public function test_guest_sees_only_currently_free_rooms_without_time_filter(): void
    {
        Carbon::setTestNow('2030-04-11 10:30:00');

        $freeRoom = Room::query()->create([
            'name' => 'Свободная сейчас',
            'capacity' => 4,
            'floor' => 2,
            'description' => 'Свободна',
            'is_active' => true,
        ]);

        $busyRoom = Room::query()->create([
            'name' => 'Занятая сейчас',
            'capacity' => 4,
            'floor' => 2,
            'description' => 'Занята',
            'is_active' => true,
        ]);

        $employee = $this->createUser('employee');

        Booking::query()->create([
            'user_id' => $employee->id,
            'room_id' => $busyRoom->id,
            'title' => 'Текущая встреча',
            'start_time' => '2030-04-11 10:00:00',
            'end_time' => '2030-04-11 11:00:00',
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/rooms');

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'Свободная сейчас');
    }

    public function test_employee_can_request_all_active_rooms_without_availability_filter(): void
    {
        Carbon::setTestNow('2030-04-11 10:30:00');

        $freeRoom = Room::query()->create([
            'name' => 'Свободная сейчас',
            'capacity' => 4,
            'floor' => 2,
            'description' => 'Свободна',
            'is_active' => true,
        ]);

        $busyRoom = Room::query()->create([
            'name' => 'Занятая сейчас',
            'capacity' => 4,
            'floor' => 2,
            'description' => 'Занята',
            'is_active' => true,
        ]);

        $employee = $this->createUser('employee');

        Booking::query()->create([
            'user_id' => $employee->id,
            'room_id' => $busyRoom->id,
            'title' => 'Текущая встреча',
            'start_time' => '2030-04-11 10:00:00',
            'end_time' => '2030-04-11 11:00:00',
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/rooms?available_only=0');

        $response->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment(['name' => $freeRoom->name])
            ->assertJsonFragment(['name' => $busyRoom->name]);
    }

    private function createUser(string $roleName): User
    {
        $role = Role::query()->where('name', $roleName)->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
        ]);
    }
}
