<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingTest extends TestCase
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

    public function test_employee_cannot_create_overlapping_booking(): void
    {
        $room = Room::query()->create([
            'name' => 'Переговорка',
            'capacity' => 6,
            'floor' => 2,
            'description' => 'Тестовая комната',
            'is_active' => true,
        ]);

        $firstEmployee = $this->createUser('employee');
        $secondEmployee = $this->createUser('employee');

        Booking::query()->create([
            'user_id' => $firstEmployee->id,
            'room_id' => $room->id,
            'title' => 'Первая бронь',
            'start_time' => '2030-04-11 10:00:00',
            'end_time' => '2030-04-11 11:00:00',
            'status' => 'active',
        ]);

        Sanctum::actingAs($secondEmployee);

        $response = $this->postJson('/api/bookings', [
            'room_id' => $room->id,
            'title' => 'Вторая бронь',
            'start_time' => '2030-04-11 10:30:00',
            'end_time' => '2030-04-11 11:30:00',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'На это время комната уже занята.');
    }

    public function test_employee_can_cancel_only_own_booking(): void
    {
        $room = Room::query()->create([
            'name' => 'Рабочее место',
            'capacity' => 1,
            'floor' => 1,
            'description' => 'Для теста',
            'is_active' => true,
        ]);

        $employee = $this->createUser('employee');
        $otherEmployee = $this->createUser('employee');

        $ownBooking = Booking::query()->create([
            'user_id' => $employee->id,
            'room_id' => $room->id,
            'title' => 'Моя бронь',
            'start_time' => '2030-04-12 09:00:00',
            'end_time' => '2030-04-12 10:00:00',
            'status' => 'active',
        ]);

        $otherBooking = Booking::query()->create([
            'user_id' => $otherEmployee->id,
            'room_id' => $room->id,
            'title' => 'Чужая бронь',
            'start_time' => '2030-04-12 11:00:00',
            'end_time' => '2030-04-12 12:00:00',
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);

        $this->patchJson('/api/bookings/'.$ownBooking->id.'/cancel')
            ->assertOk()
            ->assertJsonPath('status', 'cancelled');

        $this->patchJson('/api/bookings/'.$otherBooking->id.'/cancel')
            ->assertForbidden()
            ->assertJsonPath('message', 'Можно отменять только свои бронирования.');
    }

    public function test_office_manager_can_cancel_any_booking(): void
    {
        $room = Room::query()->create([
            'name' => 'Конференц-зал',
            'capacity' => 12,
            'floor' => 4,
            'description' => 'Для презентаций',
            'is_active' => true,
        ]);

        $employee = $this->createUser('employee');
        $manager = $this->createUser('office_manager');

        $booking = Booking::query()->create([
            'user_id' => $employee->id,
            'room_id' => $room->id,
            'title' => 'Планёрка',
            'start_time' => '2030-04-13 14:00:00',
            'end_time' => '2030-04-13 15:00:00',
            'status' => 'active',
        ]);

        Sanctum::actingAs($manager);

        $this->patchJson('/api/bookings/'.$booking->id.'/cancel')
            ->assertOk()
            ->assertJsonPath('status', 'cancelled');
    }

    public function test_employee_can_create_booking_and_see_it_in_active_list(): void
    {
        $room = Room::query()->create([
            'name' => 'Свободная комната',
            'capacity' => 6,
            'floor' => 3,
            'description' => 'Для бронирования',
            'is_active' => true,
        ]);

        $employee = $this->createUser('employee');

        Sanctum::actingAs($employee);

        $this->postJson('/api/bookings', [
            'room_id' => $room->id,
            'title' => 'Новая бронь',
            'start_time' => '2030-04-14 10:00:00',
            'end_time' => '2030-04-14 11:00:00',
        ])->assertCreated()
            ->assertJsonPath('title', 'Новая бронь');

        $this->getJson('/api/bookings')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.title', 'Новая бронь');
    }

    public function test_history_endpoint_marks_past_active_booking_as_completed(): void
    {
        Carbon::setTestNow('2030-04-15 12:00:00');

        $room = Room::query()->create([
            'name' => 'Старая переговорка',
            'capacity' => 4,
            'floor' => 2,
            'description' => 'Для истории',
            'is_active' => true,
        ]);

        $employee = $this->createUser('employee');

        Booking::query()->create([
            'user_id' => $employee->id,
            'room_id' => $room->id,
            'title' => 'Утренний созвон',
            'start_time' => '2030-04-15 08:00:00',
            'end_time' => '2030-04-15 09:00:00',
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/bookings/history')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.status', 'completed');

        $this->assertDatabaseHas('bookings', [
            'title' => 'Утренний созвон',
            'status' => 'completed',
        ]);
    }

    private function createUser(string $roleName): User
    {
        $role = Role::query()->where('name', $roleName)->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
        ]);
    }
}
