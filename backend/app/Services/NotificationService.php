<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\HotelSetting;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomMaintenance;
use App\Models\User;
use Carbon\Carbon;

class NotificationService
{
    /**
     * Create and store a notification in the database.
     */
    public function createNotification(
        string $type,
        string $title,
        string $message,
        ?array $data = null,
        ?string $targetRoleName = null,
        ?int $targetUserId = null
    ): Notification {
        $roleId = null;
        if ($targetRoleName) {
            $role = Role::where('name', strtoupper($targetRoleName))->first();
            $roleId = $role?->id;
        }

        return Notification::create([
            'user_id' => $targetUserId,
            'role_id' => $roleId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'is_read' => false,
        ]);
    }

    /**
     * Trigger notification for a new reservation.
     */
    public function notifyNewBooking(Booking $booking): Notification
    {
        $guestName = $booking->guest ? "{$booking->guest->first_name} {$booking->guest->last_name}" : 'Walk-in Guest';
        $dates = $booking->check_in_date && $booking->check_out_date
            ? "{$booking->check_in_date->format('d M')} - {$booking->check_out_date->format('d M')}"
            : '';
        $symbol = HotelSetting::getValue('currency_symbol', '₹');

        return $this->createNotification(
            'NEW_BOOKING',
            "New Booking #{$booking->booking_number}",
            "Reservation confirmed for {$guestName} ({$dates}). Total: {$symbol}" . number_format($booking->grand_total, 2),
            [
                'booking_id' => $booking->id,
                'booking_number' => $booking->booking_number,
                'target_tab' => 'bookings',
                'amount' => (float) $booking->grand_total,
            ],
            'RECEPTIONIST'
        );
    }

    /**
     * Trigger notification for booking cancellation.
     */
    public function notifyBookingCancelled(Booking $booking, ?string $reason = null): Notification
    {
        return $this->createNotification(
            'BOOKING_CANCELLED',
            "Booking Cancelled #{$booking->booking_number}",
            "Booking #{$booking->booking_number} has been cancelled. Reason: " . ($reason ?: 'Cancelled by staff'),
            [
                'booking_id' => $booking->id,
                'booking_number' => $booking->booking_number,
                'target_tab' => 'bookings',
            ],
            'RECEPTIONIST'
        );
    }

    /**
     * Trigger notification for successful payment.
     */
    public function notifyPaymentReceived(Payment $payment, ?Booking $booking = null): Notification
    {
        $bookingNumber = $booking ? "#{$booking->booking_number}" : "Booking ID: {$payment->booking_id}";
        $method = strtoupper($payment->payment_method);
        $symbol = HotelSetting::getValue('currency_symbol', '₹');

        return $this->createNotification(
            'PAYMENT_RECEIVED',
            "Payment Received: {$symbol}" . number_format($payment->amount, 2),
            "Payment of {$symbol}" . number_format($payment->amount, 2) . " received via {$method} for {$bookingNumber}.",
            [
                'payment_id' => $payment->id,
                'booking_id' => $payment->booking_id,
                'target_tab' => 'billing',
                'amount' => (float) $payment->amount,
            ],
            'ACCOUNTANT'
        );
    }

    /**
     * Trigger notification for processed refund.
     */
    public function notifyRefundProcessed(Refund $refund, Payment $payment): Notification
    {
        $symbol = HotelSetting::getValue('currency_symbol', '₹');

        return $this->createNotification(
            'REFUND_PROCESSED',
            "Refund Processed: {$symbol}" . number_format($refund->amount, 2),
            "Refund of {$symbol}" . number_format($refund->amount, 2) . " processed for booking payment (Receipt: {$payment->receipt_number}).",
            [
                'refund_id' => $refund->id,
                'payment_id' => $payment->id,
                'booking_id' => $payment->booking_id,
                'target_tab' => 'billing',
                'amount' => (float) $refund->amount,
            ],
            'ACCOUNTANT'
        );
    }

    /**
     * Trigger notification for housekeeping cleaning requirement upon check-out or turnover.
     */
    public function notifyHousekeepingCleaningRequired(Room $room, ?Booking $booking = null): Notification
    {
        return $this->createNotification(
            'ROOM_CLEANING_REQUIRED',
            "Cleaning Required: Room {$room->room_number}",
            "Room {$room->room_number} is vacant and requires turnover cleaning before next guest arrival.",
            [
                'room_id' => $room->id,
                'room_number' => $room->room_number,
                'booking_id' => $booking?->id,
                'target_tab' => 'checkin-checkout',
            ],
            'HOUSEKEEPING'
        );
    }

    /**
     * Trigger notification when a room is reported for maintenance.
     */
    public function notifyRoomMaintenanceReported(RoomMaintenance $maintenance, Room $room): Notification
    {
        return $this->createNotification(
            'ROOM_MAINTENANCE',
            "Maintenance Required: Room {$room->room_number}",
            "Issue reported for Room {$room->room_number}: {$maintenance->issue_description}",
            [
                'maintenance_id' => $maintenance->id,
                'room_id' => $room->id,
                'room_number' => $room->room_number,
                'target_tab' => 'rooms',
            ],
            'HOUSEKEEPING'
        );
    }

    /**
     * Trigger notification when housekeeping marks room cleaned.
     */
    public function notifyRoomCleaned(Room $room): Notification
    {
        return $this->createNotification(
            'ROOM_CLEANED',
            "Room {$room->room_number} Clean & Ready",
            "Housekeeping has completed cleaning for Room {$room->room_number}. Operational status is READY.",
            [
                'room_id' => $room->id,
                'room_number' => $room->room_number,
                'target_tab' => 'rooms',
            ],
            'RECEPTIONIST'
        );
    }

    /**
     * Get query for notifications visible to the given staff user according to role hierarchy.
     */
    public function getUserNotificationsQuery(User $user)
    {
        $query = Notification::query()->orderByDesc('created_at')->orderByDesc('id');

        // Owners and Admins have oversight and see all operational notifications
        if ($user->isOwner() || $user->isAdmin()) {
            return $query;
        }

        // Other roles see direct personal notifications, their role's broadcast, or general broadcasts
        return $query->where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
                ->orWhere('role_id', $user->role_id)
                ->orWhere(function ($sub) {
                    $sub->whereNull('user_id')->whereNull('role_id');
                });
        });
    }
}

