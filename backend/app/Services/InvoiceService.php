<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\HotelSetting;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class InvoiceService
{
    /**
     * Generate or retrieve the single official invoice for a booking.
     */
    public function generateOrGetInvoice(Booking $booking, User $user): Invoice
    {
        $existing = Invoice::where('booking_id', $booking->id)
            ->with(['items', 'guest', 'booking.bookingRooms.room.roomType', 'booking.bookingServices.service', 'payments.receivedBy', 'generatedBy'])
            ->first();

        if ($existing) {
            return $existing; // Strict requirement: Once issued, an invoice must not be silently overwritten
        }

        return DB::transaction(function () use ($booking, $user) {
            $year = Carbon::now()->format('Y');
            $prefix = HotelSetting::getValue('invoice_prefix', 'INV') ?: 'INV';
            $randomHex = strtoupper(Str::random(4));
            $invoiceNumber = "{$prefix}-{$year}-{$randomHex}";

            while (Invoice::where('invoice_number', $invoiceNumber)->exists()) {
                $randomHex = strtoupper(Str::random(4));
                $invoiceNumber = "{$prefix}-{$year}-{$randomHex}";
            }

            $issueDate = Carbon::today()->format('Y-m-d');
            $dueDate = $booking->check_out_date ? $booking->check_out_date->format('Y-m-d') : $issueDate;

            $invoice = Invoice::create([
                'invoice_number' => $invoiceNumber,
                'booking_id' => $booking->id,
                'guest_id' => $booking->guest_id,
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'subtotal' => 0.00,
                'tax_amount' => 0.00,
                'discount_amount' => 0.00,
                'total_amount' => 0.00,
                'paid_amount' => 0.00,
                'balance_due' => 0.00,
                'status' => 'ISSUED',
                'generated_by' => $user->id,
            ]);

            $totalSubtotal = 0.00;
            $totalTax = 0.00;

            // 1. Room Charge Items (from booking_rooms snapshots)
            foreach ($booking->bookingRooms as $br) {
                $roomNum = $br->room?->room_number ?? "Room #{$br->room_id}";
                $typeName = $br->roomType?->name ?? 'Room';
                $desc = "Accommodation: {$roomNum} ({$typeName}) - {$br->nights_count} Night(s)";

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'item_type' => 'ROOM_CHARGE',
                    'booking_room_id' => $br->id,
                    'description' => $desc,
                    'quantity' => $br->nights_count,
                    'unit_price' => (float) $br->rate_per_night,
                    'subtotal' => (float) $br->subtotal,
                    'tax_rate_percent' => (float) $br->tax_rate_percent,
                    'tax_amount' => (float) $br->tax_amount,
                    'total_amount' => (float) $br->total_amount,
                ]);

                $totalSubtotal += (float) $br->subtotal;
                $totalTax += (float) $br->tax_amount;
            }

            // 2. Chargeable Services (from booking_services snapshots)
            foreach ($booking->bookingServices as $bs) {
                $svcName = $bs->service?->name ?? 'Service Order';
                $desc = "Hotel Service: {$svcName} (Qty: {$bs->quantity})";
                if ($bs->room) {
                    $desc .= " - Room {$bs->room->room_number}";
                }

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'item_type' => 'SERVICE_CHARGE',
                    'booking_service_id' => $bs->id,
                    'description' => $desc,
                    'quantity' => $bs->quantity,
                    'unit_price' => (float) $bs->unit_price,
                    'subtotal' => (float) $bs->subtotal,
                    'tax_rate_percent' => (float) $bs->tax_rate_percent,
                    'tax_amount' => (float) $bs->tax_amount,
                    'total_amount' => (float) $bs->total_amount,
                ]);

                $totalSubtotal += (float) $bs->subtotal;
                $totalTax += (float) $bs->tax_amount;
            }

            $totalAmount = round($totalSubtotal + $totalTax, 2);

            // Link existing successful payments
            $totalPaid = (float) Payment::where('booking_id', $booking->id)
                ->where('status', 'SUCCESS')
                ->sum('amount');

            $totalRefunded = (float) \App\Models\Refund::where('booking_id', $booking->id)
                ->where('status', 'PROCESSED')
                ->sum('amount');

            $netPaid = max(0.00, round($totalPaid - $totalRefunded, 2));
            $balanceDue = max(0.00, round($totalAmount - $netPaid, 2));

            // Attach invoice_id to payments
            Payment::where('booking_id', $booking->id)->update(['invoice_id' => $invoice->id]);

            $status = $balanceDue <= 0.00 ? 'PAID' : ($netPaid > 0.00 ? 'PARTIALLY_PAID' : 'ISSUED');

            $invoice->update([
                'subtotal' => round($totalSubtotal, 2),
                'tax_amount' => round($totalTax, 2),
                'total_amount' => $totalAmount,
                'paid_amount' => $netPaid,
                'balance_due' => $balanceDue,
                'status' => $status,
            ]);

            return $invoice->fresh(['items', 'guest', 'booking.bookingRooms.room.roomType', 'booking.bookingServices.service', 'payments.receivedBy', 'generatedBy']);
        });
    }

    /**
     * Add a controlled adjustment or discount line item to an issued invoice.
     */
    public function addAdjustment(Invoice $invoice, array $data): Invoice
    {
        if ($invoice->status === 'VOID') {
            throw new InvalidArgumentException('Cannot adjust a void invoice.');
        }

        $type = strtoupper($data['item_type'] ?? 'ADJUSTMENT');
        if (!in_array($type, ['ADJUSTMENT', 'DISCOUNT', 'DAMAGE_FEE', 'LATE_CHECKOUT'], true)) {
            $type = 'ADJUSTMENT';
        }

        $amount = (float) $data['amount'];
        $desc = trim($data['description'] ?? "Invoice {$type}");
        $taxRate = isset($data['tax_rate_percent']) ? (float) $data['tax_rate_percent'] : 0.00;
        $taxAmount = round(($amount * $taxRate) / 100, 2);
        $totalAmount = round($amount + $taxAmount, 2);

        return DB::transaction(function () use ($invoice, $type, $desc, $amount, $taxRate, $taxAmount, $totalAmount) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'item_type' => $type,
                'description' => $desc,
                'quantity' => 1.00,
                'unit_price' => $amount,
                'subtotal' => $amount,
                'tax_rate_percent' => $taxRate,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
            ]);

            $newSubtotal = round((float) $invoice->subtotal + $amount, 2);
            $newTax = round((float) $invoice->tax_amount + $taxAmount, 2);
            $newTotal = round((float) $invoice->total_amount + $totalAmount, 2);
            $newBalance = max(0.00, round($newTotal - (float) $invoice->paid_amount, 2));

            $invoice->update([
                'subtotal' => $newSubtotal,
                'tax_amount' => $newTax,
                'total_amount' => $newTotal,
                'balance_due' => $newBalance,
                'status' => $newBalance <= 0.00 ? 'PAID' : ($invoice->paid_amount > 0 ? 'PARTIALLY_PAID' : 'ISSUED'),
            ]);

            return $invoice->fresh(['items', 'guest', 'booking', 'payments']);
        });
    }

    /**
     * Get complete printable invoice document structure.
     */
    public function getPrintableInvoice(Invoice $invoice): array
    {
        $invoice->load(['items', 'guest', 'booking.bookingRooms.room.roomType', 'booking.bookingServices.service', 'payments.receivedBy', 'generatedBy']);

        $hotelName = HotelSetting::getValue('hotel_name', 'Hotel Operations');
        $hotelTagline = HotelSetting::getValue('hotel_tagline', 'Staff Management Portal');

        return [
            'hotel' => [
                'name' => $hotelName,
                'tagline' => $hotelTagline,
                'address' => HotelSetting::getValue('hotel_address', ''),
                'phone' => HotelSetting::getValue('hotel_phone', ''),
                'email' => HotelSetting::getValue('hotel_email', ''),
                'gstin' => HotelSetting::getValue('hotel_gstin', ''),
            ],
            'invoice' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'issue_date' => $invoice->issue_date?->format('d M, Y'),
                'due_date' => $invoice->due_date?->format('d M, Y'),
                'status' => $invoice->status,
                'generated_by' => $invoice->generatedBy?->name,
                'subtotal' => (float) $invoice->subtotal,
                'tax_amount' => (float) $invoice->tax_amount,
                'discount_amount' => (float) $invoice->discount_amount,
                'total_amount' => (float) $invoice->total_amount,
                'paid_amount' => (float) $invoice->paid_amount,
                'balance_due' => (float) $invoice->balance_due,
            ],
            'guest' => [
                'name' => $invoice->guest ? "{$invoice->guest->first_name} {$invoice->guest->last_name}" : 'Guest',
                'phone' => $invoice->guest?->phone,
                'email' => $invoice->guest?->email,
                'id_type' => $invoice->guest?->id_type,
                'city' => $invoice->guest?->city,
                'state' => $invoice->guest?->state,
            ],
            'booking' => [
                'booking_number' => $invoice->booking?->booking_number,
                'check_in_date' => $invoice->booking?->check_in_date?->format('d M, Y'),
                'check_out_date' => $invoice->booking?->check_out_date?->format('d M, Y'),
            ],
            'items' => $invoice->items->map(fn ($it) => [
                'id' => $it->id,
                'type' => $it->item_type,
                'description' => $it->description,
                'quantity' => (float) $it->quantity,
                'unit_price' => (float) $it->unit_price,
                'subtotal' => (float) $it->subtotal,
                'tax_rate_percent' => (float) $it->tax_rate_percent,
                'tax_amount' => (float) $it->tax_amount,
                'total_amount' => (float) $it->total_amount,
            ]),
            'payments' => $invoice->payments->where('status', 'SUCCESS')->map(fn ($p) => [
                'reference' => $p->transaction_reference,
                'method' => $p->payment_method,
                'type' => $p->payment_type,
                'amount' => (float) $p->amount,
                'paid_at' => $p->paid_at ? $p->paid_at->format('d M, Y H:i') : null,
            ]),
        ];
    }
}
