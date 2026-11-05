<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\BookingStatusHistory;
use App\Models\HotelSetting;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class BookingService
{
    public function __construct(
        protected RoomAvailabilityService $availabilityService,
        protected ?NotificationService $notificationService = null
    ) {
        $this->notificationService = $notificationService ?? app(NotificationService::class);
    }

    /**
     * Create a new booking with one or more rooms inside an atomic transaction.
     *
     * @param array $data
     * @param User|null $user
     * @return Booking
     */
    public function createBooking(array $data, ?User $user = null): Booking
    {
        $checkIn = Carbon::parse($data['check_in_date'])->format('Y-m-d');
        $checkOut = Carbon::parse($data['check_out_date'])->format('Y-m-d');

        $cIn = Carbon::parse($checkIn);
        $cOut = Carbon::parse($checkOut);

        if ($cOut->lessThanOrEqualTo($cIn)) {
            throw new InvalidArgumentException('Check-out date must be strictly after check-in date.');
        }

        $nightsCount = $cIn->diffInDays($cOut);
        if ($nightsCount < 1) {
            throw new InvalidArgumentException('A booking must have a minimum duration of 1 night.');
        }

        $roomIds = array_map('intval', (array) $data['room_ids']);
        if (empty($roomIds)) {
            throw new InvalidArgumentException('At least one room must be selected for the booking.');
        }

        return DB::transaction(function () use ($data, $user, $checkIn, $checkOut, $nightsCount, $roomIds) {
            // Lock requested rooms and strictly guarantee no date overlap
            $lockedRooms = $this->availabilityService->lockAndValidateRooms($roomIds, $checkIn, $checkOut);

            // Fetch configurable settings
            $taxRateSetting = HotelSetting::where('key', 'tax_rate_percent')->first();
            $taxRatePercent = $taxRateSetting ? (float) $taxRateSetting->value : 12.00;

            $advancePercentSetting = HotelSetting::where('key', 'advance_payment_percent')->first();
            $advancePercent = $advancePercentSetting ? (float) $advancePercentSetting->value : 50.00;

            $holdMinsSetting = HotelSetting::where('key', 'hold_expiration_minutes')->first();
            $holdMinutes = $holdMinsSetting ? (int) $holdMinsSetting->value : 30;

            $bookingSource = strtoupper($data['booking_source'] ?? 'WALK_IN');
            if (!in_array($bookingSource, ['WALK_IN', 'ONLINE'], true)) {
                $bookingSource = 'WALK_IN';
            }

            // Determine initial status
            $requestedStatus = $data['status'] ?? null;
            if ($requestedStatus && in_array($requestedStatus, ['CONFIRMED', 'PENDING_PAYMENT', 'CHECKED_IN'])) {
                $status = $requestedStatus;
            } else {
                $status = ($bookingSource === 'ONLINE') ? 'PENDING_PAYMENT' : 'CONFIRMED';
            }

            $holdExpiresAt = null;
            if ($status === 'PENDING_PAYMENT') {
                $holdExpiresAt = Carbon::now()->addMinutes($holdMinutes);
            }

            // Generate unique human-readable booking number BK-YYYYMMDD-XXXX
            $datePrefix = Carbon::now()->format('Ymd');
            $randomHex = strtoupper(Str::random(4));
            $bookingNumber = "BK-{$datePrefix}-{$randomHex}";

            while (Booking::where('booking_number', $bookingNumber)->exists()) {
                $randomHex = strtoupper(Str::random(4));
                $bookingNumber = "BK-{$datePrefix}-{$randomHex}";
            }

            $booking = Booking::create([
                'booking_number' => $bookingNumber,
                'guest_id' => $data['guest_id'],
                'booking_source' => $bookingSource,
                'status' => $status,
                'check_in_date' => $checkIn,
                'check_out_date' => $checkOut,
                'expected_check_in_time' => $data['expected_check_in_time'] ?? null,
                'adults_count' => (int) ($data['adults_count'] ?? 1),
                'children_count' => (int) ($data['children_count'] ?? 0),
                'hold_expires_at' => $holdExpiresAt,
                'special_requests' => $data['special_requests'] ?? null,
                'created_by' => $user?->id,
                'base_room_charges' => 0.00,
                'total_service_charges' => 0.00,
                'total_tax_amount' => 0.00,
                'total_discount_amount' => 0.00,
                'grand_total' => 0.00,
                'required_advance_amount' => 0.00,
            ]);

            $totalBaseRoomCharges = 0.00;
            $totalTaxAmount = 0.00;

            foreach ($lockedRooms as $room) {
                $roomType = $room->roomType;
                $ratePerNight = (float) $roomType->base_price_per_night;
                $subtotal = round($ratePerNight * $nightsCount, 2);
                $taxAmount = round(($subtotal * $taxRatePercent) / 100, 2);
                $totalAmount = round($subtotal + $taxAmount, 2);

                BookingRoom::create([
                    'booking_id' => $booking->id,
                    'room_type_id' => $room->room_type_id,
                    'room_id' => $room->id,
                    'check_in_date' => $checkIn,
                    'check_out_date' => $checkOut,
                    'rate_per_night' => $ratePerNight,
                    'nights_count' => $nightsCount,
                    'subtotal' => $subtotal,
                    'tax_rate_percent' => $taxRatePercent,
                    'tax_amount' => $taxAmount,
                    'total_amount' => $totalAmount,
                ]);

                $totalBaseRoomCharges += $subtotal;
                $totalTaxAmount += $taxAmount;
            }

            $grandTotal = round($totalBaseRoomCharges + $totalTaxAmount, 2);
            $requiredAdvance = round(($grandTotal * $advancePercent) / 100, 2);

            $booking->update([
                'base_room_charges' => $totalBaseRoomCharges,
                'total_tax_amount' => $totalTaxAmount,
                'grand_total' => $grandTotal,
                'required_advance_amount' => $requiredAdvance,
            ]);

            // Audit status history
            BookingStatusHistory::create([
                'booking_id' => $booking->id,
                'from_status' => null,
                'to_status' => $status,
                'reason' => 'Booking created (' . $bookingSource . ')',
                'metadata' => [
                    'room_count' => count($lockedRooms),
                    'nights' => $nightsCount,
                    'grand_total' => $grandTotal,
                ],
                'changed_by' => $user?->id,
            ]);

            $createdBooking = $booking->load(['guest', 'bookingRooms.room.roomType', 'createdBy']);

            // Dispatch notification
            try {
                $this->notificationService->notifyNewBooking($createdBooking);
            } catch (\Throwable $e) {
                // Log non-fatal notification error
            }

            return $createdBooking;
        });
    }

    /**
     * Cancel a booking according to hotel policy.
     */
    public function cancelBooking(Booking $booking, string $reason, ?User $user = null): Booking
    {
        if (in_array($booking->status, ['CANCELLED', 'CHECKED_OUT', 'EXPIRED'], true)) {
            throw new InvalidArgumentException("Booking cannot be cancelled from current status: {$booking->status}.");
        }

        return DB::transaction(function () use ($booking, $reason, $user) {
            $fromStatus = $booking->status;

            $booking->update([
                'status' => 'CANCELLED',
                'cancellation_reason' => $reason,
                'cancelled_at' => Carbon::now(),
                'cancelled_by' => $user?->id,
            ]);

            BookingStatusHistory::create([
                'booking_id' => $booking->id,
                'from_status' => $fromStatus,
                'to_status' => 'CANCELLED',
                'reason' => $reason,
                'metadata' => ['cancelled_at' => Carbon::now()->toIso8601String()],
                'changed_by' => $user?->id,
            ]);

            $cancelledBooking = $booking->fresh(['guest', 'bookingRooms.room.roomType', 'cancelledBy']);

            try {
                $this->notificationService->notifyBookingCancelled($cancelledBooking, $reason);
            } catch (\Throwable $e) {
                // Log non-fatal notification error
            }

            return $cancelledBooking;
        });
    }

    /**
     * Check in guest for an active booking.
     */
    public function checkIn(Booking $booking, ?User $user = null, ?string $time = null): Booking
    {
        if ($booking->status === 'CHECKED_IN') {
            throw new InvalidArgumentException('Guest has already checked in for this booking.');
        }

        if (in_array($booking->status, ['CHECKED_OUT', 'CANCELLED', 'EXPIRED', 'NO_SHOW'], true)) {
            throw new InvalidArgumentException("Cannot check in booking with status: {$booking->status}.");
        }

        return DB::transaction(function () use ($booking, $user, $time) {
            // Verify all allocated rooms are READY
            $allocatedRooms = Room::whereIn('id', $booking->bookingRooms->pluck('room_id'))->get();

            foreach ($allocatedRooms as $room) {
                if ($room->operational_status !== 'READY') {
                    throw new InvalidArgumentException(
                        "Room {$room->room_number} is currently {$room->operational_status}. It must be READY before checking in."
                    );
                }
            }

            $fromStatus = $booking->status;
            $now = Carbon::now();

            $booking->update([
                'status' => 'CHECKED_IN',
                'actual_check_in_at' => $now,
                'expected_check_in_time' => $time ?? $now->format('H:i:s'),
            ]);

            BookingStatusHistory::create([
                'booking_id' => $booking->id,
                'from_status' => $fromStatus,
                'to_status' => 'CHECKED_IN',
                'reason' => 'Guest checked in at front desk',
                'metadata' => ['actual_check_in_at' => $now->toIso8601String()],
                'changed_by' => $user?->id,
            ]);

            return $booking->fresh(['guest', 'bookingRooms.room.roomType']);
        });
    }

    /**
     * Check out guest, record actual timestamp, and put rooms into CLEANING status.
     */
    public function checkOut(Booking $booking, ?User $user = null, ?string $lateUntil = null): Booking
    {
        if ($booking->status !== 'CHECKED_IN') {
            throw new InvalidArgumentException("Only checked-in bookings can be checked out. Current status: {$booking->status}.");
        }

        return DB::transaction(function () use ($booking, $user, $lateUntil) {
            $fromStatus = $booking->status;
            $now = Carbon::now();

            $lateDateTime = $lateUntil ? Carbon::parse($lateUntil) : null;

            $booking->update([
                'status' => 'CHECKED_OUT',
                'actual_check_out_at' => $now,
                'late_check_out_until' => $lateDateTime,
            ]);

            // Set all allocated rooms to CLEANING operational status
            $roomIds = $booking->bookingRooms->pluck('room_id')->toArray();
            Room::whereIn('id', $roomIds)->update(['operational_status' => 'CLEANING']);

            $isEarly = $now->format('Y-m-d') < $booking->check_out_date->format('Y-m-d');
            $reason = $isEarly ? 'Early check-out completed' : ($lateDateTime ? 'Late check-out completed' : 'Standard check-out completed');

            BookingStatusHistory::create([
                'booking_id' => $booking->id,
                'from_status' => $fromStatus,
                'to_status' => 'CHECKED_OUT',
                'reason' => $reason,
                'metadata' => [
                    'actual_check_out_at' => $now->toIso8601String(),
                    'is_early' => $isEarly,
                    'late_until' => $lateDateTime?->toIso8601String(),
                ],
                'changed_by' => $user?->id,
            ]);

            return $booking->fresh(['guest', 'bookingRooms.room.roomType']);
        });
    }

    /**
     * Expire pending holds where hold_expires_at has elapsed.
     */
    public function expirePendingHolds(): int
    {
        $now = Carbon::now();

        $expiredBookings = Booking::query()
            ->where('status', 'PENDING_PAYMENT')
            ->whereNotNull('hold_expires_at')
            ->where('hold_expires_at', '<', $now)
            ->get();

        $count = 0;
        foreach ($expiredBookings as $b) {
            DB::transaction(function () use ($b, $now) {
                $b->update(['status' => 'EXPIRED']);

                BookingStatusHistory::create([
                    'booking_id' => $b->id,
                    'from_status' => 'PENDING_PAYMENT',
                    'to_status' => 'EXPIRED',
                    'reason' => 'Online payment hold expired automatically',
                    'metadata' => ['expired_at' => $now->toIso8601String()],
                    'changed_by' => null,
                ]);
            });
            $count++;
        }

        return $count;
    }
}

