<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Booking::completeExpired();

        $bookings = $request->user()
            ->bookings()
            ->with('room.amenities')
            ->where('status', 'active')
            ->orderBy('start_time')
            ->get();

        return response()->json($bookings);
    }

    public function history(Request $request): JsonResponse
    {
        Booking::completeExpired();

        $bookings = $request->user()
            ->bookings()
            ->with('room.amenities')
            ->where('status', '!=', 'active')
            ->orderByDesc('start_time')
            ->get();

        return response()->json($bookings);
    }

    public function store(StoreBookingRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            Booking::completeExpired();

            $room = Room::query()
                ->whereKey($request->integer('room_id'))
                ->lockForUpdate()
                ->firstOrFail();

            if (!$room->is_active) {
                return response()->json([
                    'message' => 'Эта комната сейчас недоступна.',
                ], 422);
            }

            $startTime = Carbon::parse($request->input('start_time'));
            $endTime = Carbon::parse($request->input('end_time'));

            $hasConflict = Booking::query()
                ->where('room_id', $room->id)
                ->active()
                ->overlapping($startTime, $endTime)
                ->lockForUpdate()
                ->exists();

            if ($hasConflict) {
                return response()->json([
                    'message' => 'На это время комната уже занята.',
                ], 422);
            }

            $booking = Booking::query()->create([
                'user_id' => $request->user()->id,
                'room_id' => $room->id,
                'title' => $request->input('title'),
                'start_time' => $startTime,
                'end_time' => $endTime,
                'status' => 'active',
            ]);

            return response()->json(
                $booking->load('room.amenities', 'user.role'),
                201
            );
        });
    }

    public function cancel(Request $request, Booking $booking): JsonResponse
    {
        Booking::completeExpired();

        $user = $request->user();
        $canCancelAny = $user->hasPermission('bookings.cancel_any');
        $canCancelOwn = $user->hasPermission('bookings.cancel_own');

        if (!$canCancelAny && !$canCancelOwn) {
            return response()->json([
                'message' => 'У вас нет прав на отмену бронирования.',
            ], 403);
        }

        if (!$canCancelAny && $booking->user_id !== $user->id) {
            return response()->json([
                'message' => 'Можно отменять только свои бронирования.',
            ], 403);
        }

        if ($booking->status !== 'active') {
            return response()->json([
                'message' => 'Это бронирование уже нельзя отменить.',
            ], 422);
        }

        $booking->update([
            'status' => 'cancelled',
        ]);

        return response()->json(
            $booking->fresh()->load('room.amenities', 'user.role')
        );
    }
}
