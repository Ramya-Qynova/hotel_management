<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\Booking;
use App\Models\Room;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckInOutController extends Controller
{
    public function __construct(
        protected BookingService $bookingService
    ) {}

    /**
     * List guests arriving today or pending check-in.
     */
    public function todayArrivals(Request $request): JsonResponse
    {
        $today = Carbon::today()->format('Y-m-d');

        $arrivals = Booking::query()
            ->with(['guest', 'bookingRooms.room.roomType'])
            ->whereDate('check_in_date', '<=', $today)
            ->whereIn('status', ['CONFIRMED', 'PENDING_PAYMENT'])
            ->orderBy('expected_check_in_time')
            ->orderBy('id')
            ->get();

        return ApiResponse::success($arrivals, 'Arrivals retrieved successfully.');
    }

    /**
     * List guests departing today or currently checked-in.
     */
    public function todayDepartures(Request $request): JsonResponse
    {
        $today = Carbon::today()->format('Y-m-d');

        $departures = Booking::query()
            ->with(['guest', 'bookingRooms.room.roomType'])
            ->whereDate('check_out_date', '<=', $today)
            ->where('status', 'CHECKED_IN')
            ->orderBy('id')
            ->get();

        return ApiResponse::success($departures, 'Departures retrieved successfully.');
    }

    /**
     * Execute guest check-in.
     */
    public function checkIn(Request $request, Booking $booking): JsonResponse
    {
        $validated = $request->validate([
            'expected_check_in_time' => 'nullable|string',
        ]);

        try {
            $updatedBooking = $this->bookingService->checkIn(
                $booking,
                $request->user(),
                $validated['expected_check_in_time'] ?? null
            );

            return ApiResponse::success($updatedBooking, 'Guest has been checked in successfully.');
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    /**
     * Execute guest check-out (moves rooms to CLEANING status).
     */
    public function checkOut(Request $request, Booking $booking): JsonResponse
    {
        $validated = $request->validate([
            'late_check_out_until' => 'nullable|date_format:Y-m-d H:i:s',
        ]);

        try {
            $updatedBooking = $this->bookingService->checkOut(
                $booking,
                $request->user(),
                $validated['late_check_out_until'] ?? null
            );

            // Notify Housekeeping for each room now requiring turnover cleaning
            foreach ($booking->bookingRooms as $br) {
                if ($br->room) {
                    try {
                        app(\App\Services\NotificationService::class)->notifyHousekeepingCleaningRequired($br->room, $booking);
                    } catch (\Throwable $e) {}
                }
            }

            return ApiResponse::success($updatedBooking, 'Guest check-out completed. Rooms moved to CLEANING status.');
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    /**
     * Mark a room as cleaned, returning it to READY operational status.
     */
    public function markRoomCleaned(Request $request, Room $room): JsonResponse
    {
        if ($room->operational_status !== 'CLEANING') {
            return ApiResponse::error("Room {$room->room_number} is currently {$room->operational_status}, not in CLEANING status.", 422);
        }

        $room->update(['operational_status' => 'READY']);

        try {
            app(\App\Services\NotificationService::class)->notifyRoomCleaned($room);
        } catch (\Throwable $e) {}

        return ApiResponse::success($room->fresh('roomType'), "Room {$room->room_number} is now marked READY.");
    }
}

