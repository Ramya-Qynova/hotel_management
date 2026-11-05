<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\BookingRoom;
use App\Models\Room;
use App\Services\RoomAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoomController extends Controller
{
    public function __construct(
        protected RoomAvailabilityService $availabilityService
    ) {}

    /**
     * Display a listing of rooms.
     */
    public function index(Request $request): JsonResponse
    {
        $today = Carbon::today()->format('Y-m-d');
        $query = Room::query()->with([
            'roomType',
            'bookingRooms' => function ($bq) use ($today) {
                $bq->where('check_in_date', '<=', $today)
                   ->where('check_out_date', '>', $today)
                   ->whereHas('booking', function ($q) {
                       $q->whereIn('status', ['CONFIRMED', 'CHECKED_IN']);
                   })
                   ->with('booking.guest');
            },
        ])->withCount(['bookingRooms', 'maintenances', 'bookingServices']);

        if ($request->filled('room_type_id')) {
            $query->where('room_type_id', $request->query('room_type_id'));
        }

        if ($request->filled('operational_status')) {
            $query->where('operational_status', $request->query('operational_status'));
        }

        if ($request->filled('floor')) {
            $query->where('floor', $request->query('floor'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('room_number', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        // Default sorting: Latest records first per Requirement 8, unless floor/room sort requested
        if ($request->query('sort') === 'floor') {
            $rooms = $query->orderBy('floor')->orderBy('room_number')->get();
        } else {
            $rooms = $query->orderByDesc('created_at')->orderByDesc('id')->get();
        }

        return ApiResponse::success($rooms, 'Rooms retrieved successfully.');
    }

    /**
     * Store a newly created physical room.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'room_number' => 'required|string|max:20|unique:rooms,room_number',
            'room_type_id' => 'required|exists:room_types,id',
            'floor' => 'required|integer|min:-2|max:100',
            'operational_status' => ['nullable', Rule::in(['READY', 'CLEANING', 'MAINTENANCE', 'OUT_OF_SERVICE'])],
            'is_active' => 'nullable|boolean',
            'notes' => 'nullable|string|max:255',
        ]);

        $room = Room::create([
            'room_number' => $validated['room_number'],
            'room_type_id' => $validated['room_type_id'],
            'floor' => $validated['floor'],
            'operational_status' => $validated['operational_status'] ?? 'READY',
            'is_active' => $validated['is_active'] ?? true,
            'notes' => $validated['notes'] ?? null,
        ]);

        return ApiResponse::success($room->load('roomType'), 'Room created successfully.', 201);
    }

    /**
     * Display the specified room.
     */
    public function show(Room $room): JsonResponse
    {
        $room->load([
            'roomType',
            'maintenances' => function ($q) {
                $q->latest()->limit(10)->with(['reportedBy', 'resolvedBy']);
            },
            'bookingRooms' => function ($q) {
                $q->latest()->limit(5)->with('booking.guest');
            },
        ]);

        return ApiResponse::success($room, 'Room details retrieved.');
    }

    /**
     * Update the specified room.
     */
    public function update(Request $request, Room $room): JsonResponse
    {
        $validated = $request->validate([
            'room_number' => ['sometimes', 'required', 'string', 'max:20', Rule::unique('rooms', 'room_number')->ignore($room->id)],
            'room_type_id' => ['sometimes', 'required', 'exists:room_types,id'],
            'floor' => ['sometimes', 'required', 'integer', 'min:-2|max:100'],
            'operational_status' => ['nullable', Rule::in(['READY', 'CLEANING', 'MAINTENANCE', 'OUT_OF_SERVICE'])],
            'is_active' => 'nullable|boolean',
            'notes' => 'nullable|string|max:255',
        ]);

        // If deactivating room, verify it has no active, checked-in, or upcoming bookings
        if (isset($validated['is_active']) && ! $validated['is_active'] && $room->is_active) {
            if ($this->hasActiveBookingsOrCheckIns($room)) {
                return ApiResponse::error(
                    "Cannot deactivate Room {$room->room_number} because it currently has active, checked-in, or upcoming bookings. Please reassign or conclude those stays first.",
                    422
                );
            }
        }

        // If room_type_id is changing, check whether future bookings exist for this room
        if (isset($validated['room_type_id']) && (int) $validated['room_type_id'] !== (int) $room->room_type_id) {
            $hasFutureBookings = BookingRoom::query()
                ->where('room_id', $room->id)
                ->where('check_out_date', '>=', Carbon::today()->format('Y-m-d'))
                ->whereHas('booking', function ($q) {
                    $q->whereIn('status', ['CONFIRMED', 'CHECKED_IN', 'PENDING_PAYMENT']);
                })
                ->exists();

            if ($hasFutureBookings) {
                return ApiResponse::error(
                    'Cannot change room category because this room has active or upcoming bookings. Complete or reassign them first.',
                    422
                );
            }
        }

        $room->update($validated);

        return ApiResponse::success($room->fresh(['roomType'])->loadCount(['bookingRooms', 'maintenances', 'bookingServices']), 'Room updated successfully.');
    }

    /**
     * Toggle active/inactive status of a physical room (OWNER or ADMIN only).
     */
    public function toggleActive(Request $request, Room $room): JsonResponse
    {
        $targetActive = $request->has('is_active') ? $request->boolean('is_active') : ! $room->is_active;

        if (! $targetActive && $room->is_active) {
            if ($this->hasActiveBookingsOrCheckIns($room)) {
                return ApiResponse::error(
                    "Cannot deactivate Room {$room->room_number} because it currently has active, checked-in, or upcoming bookings. Please reassign or conclude those stays first.",
                    422
                );
            }
        }

        $room->update(['is_active' => $targetActive]);

        $message = $targetActive
            ? "Room {$room->room_number} activated successfully."
            : "Room {$room->room_number} deactivated successfully.";

        return ApiResponse::success(
            $room->fresh(['roomType'])->loadCount(['bookingRooms', 'maintenances', 'bookingServices']),
            $message
        );
    }

    /**
     * Permanently delete a physical room if safe (no booking, payment, invoice, or maintenance history).
     */
    public function destroy(Room $room): JsonResponse
    {
        // 1. Check for booking history (which links to payments, invoices, guest history)
        if ($room->bookingRooms()->exists()) {
            return ApiResponse::error(
                "Cannot delete Room {$room->room_number} because it has historical booking records. To preserve financial, invoice, and guest audit trails, deactivate or archive the room instead.",
                422
            );
        }

        // 2. Check for maintenance records
        if ($room->maintenances()->exists()) {
            return ApiResponse::error(
                "Cannot delete Room {$room->room_number} because it has maintenance service history. Deactivate the room instead.",
                422
            );
        }

        // 3. Check for service orders
        if ($room->bookingServices()->exists()) {
            return ApiResponse::error(
                "Cannot delete Room {$room->room_number} because it has service order history. Deactivate the room instead.",
                422
            );
        }

        $roomNumber = $room->room_number;
        $room->delete();

        return ApiResponse::success(null, "Room {$roomNumber} permanently deleted.");
    }

    /**
     * Check whether a room has active, checked-in, or upcoming confirmed bookings.
     */
    protected function hasActiveBookingsOrCheckIns(Room $room): bool
    {
        $now = Carbon::now();
        $today = Carbon::today()->format('Y-m-d');

        return BookingRoom::query()
            ->where('room_id', $room->id)
            ->where('check_out_date', '>=', $today)
            ->whereHas('booking', function ($q) use ($now) {
                $q->whereIn('status', ['CONFIRMED', 'CHECKED_IN'])
                  ->orWhere(function ($holdQuery) use ($now) {
                      $holdQuery->where('status', 'PENDING_PAYMENT')
                                ->where(function ($exp) use ($now) {
                                    $exp->whereNull('hold_expires_at')
                                        ->orWhere('hold_expires_at', '>', $now);
                                });
                  });
            })
            ->exists();
    }

    /**
     * Update operational status of a room (READY, CLEANING, MAINTENANCE, OUT_OF_SERVICE).
     */
    public function updateStatus(Request $request, Room $room): JsonResponse
    {
        $validated = $request->validate([
            'operational_status' => ['required', Rule::in(['READY', 'CLEANING', 'MAINTENANCE', 'OUT_OF_SERVICE'])],
            'notes' => 'nullable|string|max:255',
        ]);

        $room->update([
            'operational_status' => $validated['operational_status'],
            'notes' => $validated['notes'] ?? $room->notes,
        ]);

        return ApiResponse::success($room->fresh('roomType'), "Room status updated to {$room->operational_status}.");
    }

    /**
     * Query available rooms for a given date range.
     */
    public function available(Request $request): JsonResponse
    {
        $request->validate([
            'check_in_date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'check_out_date' => 'required|date_format:Y-m-d|after:check_in_date',
            'room_type_id' => 'nullable|integer|exists:room_types,id',
        ]);

        try {
            $availableRooms = $this->availabilityService->getAvailableRooms(
                $request->query('check_in_date'),
                $request->query('check_out_date'),
                $request->filled('room_type_id') ? (int) $request->query('room_type_id') : null
            );

            return ApiResponse::success($availableRooms, 'Available rooms retrieved successfully.');
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }
}

