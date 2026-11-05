<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Room;
use App\Models\RoomMaintenance;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RoomAvailabilityService
{
    /**
     * Get unavailable room IDs for a given date range.
     *
     * Overlap condition:
     * existing_check_in < requested_check_out AND existing_check_out > requested_check_in
     * (check-out date is exclusive).
     *
     * @param string $checkIn Date formatted as Y-m-d
     * @param string $checkOut Date formatted as Y-m-d
     * @param int|null $excludeBookingId Optional booking ID to exclude (e.g. when updating a booking)
     * @return array<int> List of room IDs that cannot be booked
     */
    public function getUnavailableRoomIds(string $checkIn, string $checkOut, ?int $excludeBookingId = null): array
    {
        $this->validateDateRange($checkIn, $checkOut);

        $now = Carbon::now();

        // 1. Rooms booked with CONFIRMED or CHECKED_IN status, or unexpired PENDING_PAYMENT hold
        $bookedRoomIds = BookingRoom::query()
            ->whereHas('booking', function ($query) use ($excludeBookingId, $now) {
                if ($excludeBookingId !== null) {
                    $query->where('id', '!=', $excludeBookingId);
                }

                $query->where(function ($q) use ($now) {
                    $q->whereIn('status', ['CONFIRMED', 'CHECKED_IN'])
                      ->orWhere(function ($holdQuery) use ($now) {
                          $holdQuery->where('status', 'PENDING_PAYMENT')
                                    ->where(function ($expQuery) use ($now) {
                                        $expQuery->whereNull('hold_expires_at')
                                                 ->orWhere('hold_expires_at', '>', $now);
                                    });
                      });
                });
            })
            ->where(function ($q) use ($checkIn, $checkOut) {
                // date overlap: check_in_date < requestedCheckOut AND check_out_date > requestedCheckIn
                $q->where('check_in_date', '<', $checkOut)
                  ->where('check_out_date', '>', $checkIn);
            })
            ->pluck('room_id')
            ->toArray();

        // 2. Rooms under SCHEDULED or IN_PROGRESS maintenance
        $maintenanceRoomIds = RoomMaintenance::query()
            ->whereIn('status', ['SCHEDULED', 'IN_PROGRESS'])
            ->where(function ($q) use ($checkIn, $checkOut) {
                $q->where('start_date', '<', $checkOut)
                  ->where('end_date', '>', $checkIn);
            })
            ->pluck('room_id')
            ->toArray();

        // 3. Rooms that are OUT_OF_SERVICE, MAINTENANCE, or deactivated
        $inactiveOrOosRoomIds = Room::query()
            ->where(function ($q) {
                $q->where('is_active', false)
                  ->orWhere('operational_status', 'OUT_OF_SERVICE')
                  ->orWhere('operational_status', 'MAINTENANCE');
            })
            ->pluck('id')
            ->toArray();

        return array_values(array_unique(array_merge($bookedRoomIds, $maintenanceRoomIds, $inactiveOrOosRoomIds)));
    }

    /**
     * Check if a specific room is available for the given date range.
     */
    public function isRoomAvailable(int $roomId, string $checkIn, string $checkOut, ?int $excludeBookingId = null): bool
    {
        $unavailableIds = $this->getUnavailableRoomIds($checkIn, $checkOut, $excludeBookingId);
        return !in_array($roomId, $unavailableIds, true);
    }

    /**
     * Get all available rooms for a given date range and optional room type.
     */
    public function getAvailableRooms(string $checkIn, string $checkOut, ?int $roomTypeId = null): Collection
    {
        $unavailableIds = $this->getUnavailableRoomIds($checkIn, $checkOut);

        $query = Room::query()
            ->with('roomType')
            ->where('is_active', true)
            ->where('operational_status', '!=', 'OUT_OF_SERVICE')
            ->whereNotIn('id', $unavailableIds);

        if ($roomTypeId !== null) {
            $query->where('room_type_id', $roomTypeId);
        }

        return $query->orderBy('room_number')->get();
    }

    /**
     * Lock and validate the requested rooms inside a database transaction to prevent race conditions.
     * Throws InvalidArgumentException if any room is invalid or unavailable.
     *
     * @param array<int> $roomIds
     * @param string $checkIn
     * @param string $checkOut
     * @param int|null $excludeBookingId
     * @return Collection<int, Room>
     */
    public function lockAndValidateRooms(array $roomIds, string $checkIn, string $checkOut, ?int $excludeBookingId = null): Collection
    {
        if (empty($roomIds)) {
            throw new InvalidArgumentException('At least one room must be selected.');
        }

        $this->validateDateRange($checkIn, $checkOut);

        // Fetch and lock the rooms in predictable order to prevent deadlocks
        sort($roomIds);
        $lockedRooms = Room::query()
            ->whereIn('id', $roomIds)
            ->with('roomType')
            ->lockForUpdate()
            ->get();

        if ($lockedRooms->count() !== count(array_unique($roomIds))) {
            throw new InvalidArgumentException('One or more selected rooms do not exist.');
        }

        // Verify active and operational status
        foreach ($lockedRooms as $room) {
            if (!$room->is_active) {
                throw new InvalidArgumentException("Room {$room->room_number} is currently inactive and cannot be booked.");
            }
            if ($room->operational_status === 'OUT_OF_SERVICE') {
                throw new InvalidArgumentException("Room {$room->room_number} is out of service and cannot be booked.");
            }
            if ($room->operational_status === 'MAINTENANCE') {
                throw new InvalidArgumentException("Room {$room->room_number} is under maintenance and cannot be booked.");
            }
        }

        // Check for concurrent overlap with active bookings
        $now = Carbon::now();
        $conflictingBooking = BookingRoom::query()
            ->whereIn('room_id', $roomIds)
            ->whereHas('booking', function ($query) use ($excludeBookingId, $now) {
                if ($excludeBookingId !== null) {
                    $query->where('id', '!=', $excludeBookingId);
                }
                $query->where(function ($q) use ($now) {
                    $q->whereIn('status', ['CONFIRMED', 'CHECKED_IN'])
                      ->orWhere(function ($holdQuery) use ($now) {
                          $holdQuery->where('status', 'PENDING_PAYMENT')
                                    ->where(function ($expQuery) use ($now) {
                                        $expQuery->whereNull('hold_expires_at')
                                                 ->orWhere('hold_expires_at', '>', $now);
                                    });
                      });
                });
            })
            ->where(function ($q) use ($checkIn, $checkOut) {
                $q->where('check_in_date', '<', $checkOut)
                  ->where('check_out_date', '>', $checkIn);
            })
            ->lockForUpdate()
            ->with(['room', 'booking'])
            ->first();

        if ($conflictingBooking) {
            $roomNum = $conflictingBooking->room?->room_number ?? "ID {$conflictingBooking->room_id}";
            throw new InvalidArgumentException("Room {$roomNum} is already booked between {$checkIn} and {$checkOut}.");
        }

        // Check for conflicting maintenance
        $conflictingMaintenance = RoomMaintenance::query()
            ->whereIn('room_id', $roomIds)
            ->whereIn('status', ['SCHEDULED', 'IN_PROGRESS'])
            ->where(function ($q) use ($checkIn, $checkOut) {
                $q->where('start_date', '<', $checkOut)
                  ->where('end_date', '>', $checkIn);
            })
            ->with('room')
            ->first();

        if ($conflictingMaintenance) {
            $roomNum = $conflictingMaintenance->room?->room_number ?? "ID {$conflictingMaintenance->room_id}";
            throw new InvalidArgumentException("Room {$roomNum} has scheduled maintenance between {$checkIn} and {$checkOut}.");
        }

        return $lockedRooms;
    }

    /**
     * Validate date boundaries.
     */
    protected function validateDateRange(string $checkIn, string $checkOut): void
    {
        $cIn = Carbon::parse($checkIn);
        $cOut = Carbon::parse($checkOut);

        if ($cOut->lessThanOrEqualTo($cIn)) {
            throw new InvalidArgumentException('Check-out date must be strictly after check-in date.');
        }
    }
}

