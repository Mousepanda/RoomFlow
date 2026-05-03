<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoomRequest;
use App\Http\Requests\UpdateRoomRequest;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class RoomController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'start_time' => ['nullable', 'required_with:end_time', 'date'],
            'end_time' => ['nullable', 'required_with:start_time', 'date', 'after:start_time'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'floor' => ['nullable', 'integer'],
            'amenity_ids' => ['nullable', 'array'],
            'amenity_ids.*' => ['integer', 'exists:amenities,id'],
            'available_only' => ['nullable', 'boolean'],
            'show_inactive' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        $query = Room::query()->with('amenities');

        $canSeeInactive = $user && $user->hasPermission('rooms.manage') && $request->boolean('show_inactive');

        if (!$canSeeInactive) {
            $query->where('is_active', true);
        }

        if ($request->filled('capacity')) {
            $query->where('capacity', '>=', (int) $request->input('capacity'));
        }

        if ($request->filled('floor')) {
            $query->where('floor', (int) $request->input('floor'));
        }

        foreach ($request->input('amenity_ids', []) as $amenityId) {
            $query->whereHas('amenities', function ($amenityQuery) use ($amenityId) {
                $amenityQuery->where('amenities.id', $amenityId);
            });
        }

        if ($request->filled('start_time') && $request->filled('end_time')) {
            $query->availableBetween(
                Carbon::parse($request->input('start_time')),
                Carbon::parse($request->input('end_time'))
            );
        } elseif ($user ? $request->boolean('available_only', true) : true) {
            $query->availableBetween(now(), now()->copy()->addMinute());
        }

        return response()->json(
            $query->orderBy('floor')->orderBy('name')->get()
        );
    }

    public function show(Request $request, Room $room): JsonResponse
    {
        $room->load('amenities');

        $canManageRooms = $request->user() && $request->user()->hasPermission('rooms.manage');

        if (!$room->is_active && !$canManageRooms) {
            return response()->json([
                'message' => 'Комната не найдена.',
            ], 404);
        }

        return response()->json($room);
    }

    public function store(StoreRoomRequest $request): JsonResponse
    {
        $data = $request->validated();
        $amenityIds = $data['amenity_ids'] ?? [];

        unset($data['amenity_ids']);

        $room = Room::query()->create($data);
        $room->amenities()->sync($amenityIds);

        return response()->json(
            $room->load('amenities'),
            201
        );
    }

    public function update(UpdateRoomRequest $request, Room $room): JsonResponse
    {
        $data = $request->validated();
        $amenityIds = $data['amenity_ids'] ?? [];

        unset($data['amenity_ids']);

        $room->update($data);
        $room->amenities()->sync($amenityIds);

        return response()->json(
            $room->fresh()->load('amenities')
        );
    }

    public function destroy(Room $room): JsonResponse
    {
        $room->delete();

        return response()->json([
            'message' => 'Комната удалена.',
        ]);
    }
}
