<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingStatusHistory;
use App\Models\CashRegisterTransaction;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class BillingPaymentService
{
    /**
     * Calculate current outstanding balance for a booking.
     */
    public function getOutstandingBalance(Booking $booking): float
    {
        $grandTotal = (float) $booking->grand_total;

        $totalPaid = (float) Payment::where('booking_id', $booking->id)
            ->where('status', 'SUCCESS')
            ->sum('amount');

        $totalRefunded = (float) Refund::where('booking_id', $booking->id)
            ->where('status', 'PROCESSED')
            ->sum('amount');

        $netPaid = max(0.00, round($totalPaid - $totalRefunded, 2));
        return max(0.00, round($grandTotal - $netPaid, 2));
    }

    /**
     * Record an offline or direct payment (Cash, UPI, Card, Bank Transfer).
     */
    public function recordPayment(Booking $booking, array $data, User $user): Payment
    {
        $amount = (float) $data['amount'];
        if ($amount <= 0.00) {
            throw new InvalidArgumentException('Payment amount must be greater than zero.');
        }

        return DB::transaction(function () use ($booking, $data, $amount, $user) {
            // Lock booking record
            $lockedBooking = Booking::where('id', $booking->id)->lockForUpdate()->firstOrFail();

            if (in_array($lockedBooking->status, ['CANCELLED', 'EXPIRED'], true)) {
                throw new InvalidArgumentException("Cannot record payment for a {$lockedBooking->status} booking.");
            }

            $outstanding = $this->getOutstandingBalance($lockedBooking);

            // Never allow overpayment
            if (bccomp((string) $amount, (string) $outstanding, 2) === 1) {
                throw new InvalidArgumentException("Payment amount (₹{$amount}) exceeds outstanding balance (₹{$outstanding}).");
            }

            $method = strtoupper($data['payment_method'] ?? 'CASH');
            if (!in_array($method, ['CASH', 'UPI', 'CARD', 'BANK_TRANSFER'], true)) {
                throw new InvalidArgumentException("Invalid payment method: {$method}.");
            }

            $type = strtoupper($data['payment_type'] ?? 'INTERIM');
            if (!in_array($type, ['ADVANCE', 'INTERIM', 'FINAL_SETTLEMENT', 'SECURITY_DEPOSIT'], true)) {
                $type = 'INTERIM';
            }

            $ref = $data['transaction_reference'] ?? null;
            if (empty($ref)) {
                $dateStr = Carbon::now()->format('Ymd');
                $randomHex = strtoupper(Str::random(4));
                $ref = "PAY-{$dateStr}-{$randomHex}";

                while (Payment::where('transaction_reference', $ref)->exists()) {
                    $randomHex = strtoupper(Str::random(4));
                    $ref = "PAY-{$dateStr}-{$randomHex}";
                }
            } else {
                // Ensure unique transaction reference
                if (Payment::where('transaction_reference', $ref)->exists()) {
                    throw new InvalidArgumentException("Transaction reference '{$ref}' has already been recorded.");
                }
            }

            $payment = Payment::create([
                'booking_id' => $lockedBooking->id,
                'invoice_id' => $lockedBooking->invoice?->id,
                'transaction_reference' => $ref,
                'payment_method' => $method,
                'payment_type' => $type,
                'amount' => $amount,
                'status' => 'SUCCESS',
                'paid_at' => Carbon::now(),
                'received_by' => $user->id,
                'notes' => $data['notes'] ?? null,
            ]);

            // If cash payment, record physical cash transaction
            if ($method === 'CASH') {
                $today = Carbon::today()->format('Y-m-d');
                $lastBalance = (float) (CashRegisterTransaction::where('register_date', $today)
                    ->latest('id')
                    ->value('balance_after') ?? 0.00);

                $balanceAfter = round($lastBalance + $amount, 2);

                CashRegisterTransaction::create([
                    'register_date' => $today,
                    'transaction_type' => 'CASH_IN',
                    'category' => 'ROOM_PAYMENT',
                    'amount' => $amount,
                    'balance_after' => $balanceAfter,
                    'payment_id' => $payment->id,
                    'performed_by' => $user->id,
                    'notes' => "Cash received for Booking {$lockedBooking->booking_number} ({$ref})",
                ]);
            }

            // If booking was PENDING_PAYMENT, transition to CONFIRMED
            if ($lockedBooking->status === 'PENDING_PAYMENT') {
                $lockedBooking->update(['status' => 'CONFIRMED']);

                BookingStatusHistory::create([
                    'booking_id' => $lockedBooking->id,
                    'from_status' => 'PENDING_PAYMENT',
                    'to_status' => 'CONFIRMED',
                    'reason' => "Advance payment received via {$method}",
                    'metadata' => [
                        'payment_reference' => $ref,
                        'amount' => $amount,
                    ],
                    'changed_by' => $user->id,
                ]);
            }

            // If an invoice is issued, update its paid amount and balance due
            $invoice = $lockedBooking->invoice;
            if ($invoice) {
                $newPaid = round((float) $invoice->paid_amount + $amount, 2);
                $newBalance = max(0.00, round((float) $invoice->total_amount - $newPaid, 2));

                $invoice->update([
                    'paid_amount' => $newPaid,
                    'balance_due' => $newBalance,
                    'status' => $newBalance <= 0 ? 'PAID' : 'PARTIALLY_PAID',
                ]);

                $payment->update(['invoice_id' => $invoice->id]);
            }

            try {
                app(\App\Services\NotificationService::class)->notifyPaymentReceived($payment, $lockedBooking);
            } catch (\Throwable $e) {
                // Ignore notification failure
            }

            return $payment->load(['booking.guest', 'receivedBy']);
        });
    }

    /**
     * Get complete billing summary for a booking.
     */
    public function getBillingSummary(Booking $booking): array
    {
        $booking->load(['guest', 'bookingRooms.room.roomType', 'bookingServices.service', 'payments.receivedBy', 'refunds.processedBy', 'invoice']);

        $grandTotal = (float) $booking->grand_total;
        $totalPaid = (float) $booking->payments->where('status', 'SUCCESS')->sum('amount');
        $totalRefunded = (float) $booking->refunds->where('status', 'PROCESSED')->sum('amount');
        $netPaid = max(0.00, round($totalPaid - $totalRefunded, 2));
        $outstanding = max(0.00, round($grandTotal - $netPaid, 2));

        return [
            'booking_id' => $booking->id,
            'booking_number' => $booking->booking_number,
            'status' => $booking->status,
            'guest' => [
                'id' => $booking->guest?->id,
                'name' => $booking->guest ? "{$booking->guest->first_name} {$booking->guest->last_name}" : 'Guest',
                'phone' => $booking->guest?->phone,
                'email' => $booking->guest?->email,
            ],
            'financials' => [
                'base_room_charges' => (float) $booking->base_room_charges,
                'total_service_charges' => (float) $booking->total_service_charges,
                'total_tax_amount' => (float) $booking->total_tax_amount,
                'grand_total' => $grandTotal,
                'required_advance' => (float) $booking->required_advance_amount,
                'total_paid' => $totalPaid,
                'total_refunded' => $totalRefunded,
                'net_paid' => $netPaid,
                'outstanding_balance' => $outstanding,
                'is_fully_paid' => $outstanding <= 0.00,
            ],
            'rooms' => $booking->bookingRooms->map(fn ($br) => [
                'id' => $br->id,
                'room_number' => $br->room?->room_number,
                'room_type' => $br->roomType?->name,
                'rate_per_night' => (float) $br->rate_per_night,
                'nights_count' => $br->nights_count,
                'subtotal' => (float) $br->subtotal,
                'tax_amount' => (float) $br->tax_amount,
                'total_amount' => (float) $br->total_amount,
            ]),
            'services' => $booking->bookingServices->map(fn ($bs) => [
                'id' => $bs->id,
                'service_name' => $bs->service?->name,
                'room_number' => $bs->room?->room_number,
                'quantity' => $bs->quantity,
                'unit_price' => (float) $bs->unit_price,
                'subtotal' => (float) $bs->subtotal,
                'tax_amount' => (float) $bs->tax_amount,
                'total_amount' => (float) $bs->total_amount,
                'service_date' => $bs->service_date?->format('Y-m-d'),
            ]),
            'payments' => $booking->payments->map(fn ($p) => [
                'id' => $p->id,
                'transaction_reference' => $p->transaction_reference,
                'method' => $p->payment_method,
                'type' => $p->payment_type,
                'amount' => (float) $p->amount,
                'status' => $p->status,
                'paid_at' => $p->paid_at ? $p->paid_at->toIso8601String() : null,
                'received_by' => $p->receivedBy?->name,
                'notes' => $p->notes,
                'refundable_balance' => max(0.00, round((float) $p->amount - (float) $p->refunds->where('status', 'PROCESSED')->sum('amount'), 2)),
            ]),
            'refunds' => $booking->refunds->map(fn ($r) => [
                'id' => $r->id,
                'refund_reference' => $r->refund_reference,
                'payment_reference' => $r->payment?->transaction_reference,
                'amount' => (float) $r->amount,
                'cancellation_fee' => (float) $r->cancellation_fee,
                'method' => $r->refund_method,
                'status' => $r->status,
                'reason' => $r->reason,
                'processed_at' => $r->processed_at ? $r->processed_at->toIso8601String() : null,
                'processed_by' => $r->processedBy?->name,
            ]),
            'invoice' => $booking->invoice ? [
                'id' => $booking->invoice->id,
                'invoice_number' => $booking->invoice->invoice_number,
                'status' => $booking->invoice->status,
                'issue_date' => $booking->invoice->issue_date?->format('Y-m-d'),
                'total_amount' => (float) $booking->invoice->total_amount,
                'paid_amount' => (float) $booking->invoice->paid_amount,
                'balance_due' => (float) $booking->invoice->balance_due,
            ] : null,
        ];
    }
}
