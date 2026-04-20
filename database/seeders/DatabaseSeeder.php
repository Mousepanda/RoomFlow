<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\Booking;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        $amenities = [
            'Проектор',
            'Доска',
            'Видеосвязь',
            'Кондиционер',
        ];

        foreach ($amenities as $amenityName) {
            Amenity::query()->firstOrCreate([
                'name' => $amenityName,
            ]);
        }

        $rooms = [
            [
                'name' => 'Переговорка 1',
                'capacity' => 6,
                'floor' => 2,
                'description' => 'Небольшая переговорная у ресепшена',
                'is_active' => true,
                'amenities' => ['Проектор', 'Доска'],
            ],
            [
                'name' => 'Конференц-зал',
                'capacity' => 20,
                'floor' => 4,
                'description' => 'Большой зал для встреч и презентаций',
                'is_active' => true,
                'amenities' => ['Проектор', 'Видеосвязь', 'Кондиционер'],
            ],
            [
                'name' => 'Рабочее место A-12',
                'capacity' => 1,
                'floor' => 1,
                'description' => 'Тихая зона у окна',
                'is_active' => true,
                'amenities' => ['Кондиционер'],
            ],
        ];

        foreach ($rooms as $roomData) {
            $amenityNames = $roomData['amenities'];
            unset($roomData['amenities']);

            $room = Room::query()->updateOrCreate(
                ['name' => $roomData['name']],
                $roomData
            );

            $amenityIds = Amenity::query()
                ->whereIn('name', $amenityNames)
                ->pluck('id');

            $room->amenities()->sync($amenityIds);
        }

        $employeeRole = Role::query()->where('name', 'employee')->first();
        $managerRole = Role::query()->where('name', 'office_manager')->first();
        $adminRole = Role::query()->where('name', 'admin')->first();

        $employee = User::query()->updateOrCreate(
            ['email' => 'employee@roomflow.test'],
            [
                'name' => 'Сотрудник',
                'password' => 'password',
                'role_id' => $employeeRole?->id,
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'manager@roomflow.test'],
            [
                'name' => 'Менеджер офиса',
                'password' => 'password',
                'role_id' => $managerRole?->id,
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'admin@roomflow.test'],
            [
                'name' => 'Администратор',
                'password' => 'password',
                'role_id' => $adminRole?->id,
            ]
        );

        $conferenceRoom = Room::query()->where('name', 'Конференц-зал')->first();

        if ($employee && $conferenceRoom) {
            Booking::query()->updateOrCreate(
                [
                    'user_id' => $employee->id,
                    'room_id' => $conferenceRoom->id,
                    'title' => 'Командная встреча',
                ],
                [
                    'start_time' => now()->addDay()->setHour(11)->setMinute(0),
                    'end_time' => now()->addDay()->setHour(12)->setMinute(0),
                    'status' => 'active',
                ]
            );
        }
    }
}
