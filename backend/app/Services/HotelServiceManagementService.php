<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingService;
use App\Models\InvoiceItem;
use App\Models\Room;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class HotelServiceManagementService
{
    /**
     * List all services with optional filtering.
     */
    public function getServices(bool $activeOnly = false, ?string $category = null, ?string $search = null): Collection
    {
        $query = Service::query();

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        if ($category) {
            $query->where('category', strtoupper($category));
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $query->orderByDesc('created_at')->orderByDesc('id')->get();
    }

    /**
     * Create a new chargeable hotel service.
     */
    public function createService(array $data): Service
    {
        $category = strtoupper($data['category'] ?? 'OTHER');
        if (!in_array($category, ['FOOD', 'LAUNDRY', 'EXTRA_BED', 'OTHER'], true)) {
            $category = 'OTHER';
        }

        return Service::create([
            'name' => trim($data['name']),
            'category' => $category,
            'unit_price' => (float) $data['unit_price'],
            'tax_rate_percent' => isset($data['tax_rate_percent']) ? (float) $data['tax_rate_percent'] : 0.00,
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true,
            'description' => $data['description'] ?? null,
        ]);
    }

    /**
     * Update an existing hotel service.
     */
    public function updateService(Service $service, array $data): Service
    {
        $payload = [];
        if (isset($data['name'])) {
            $payload['name'] = trim($data['name']);
        }
        if (isset($data['category'])) {
            $category = strtoupper($data['category']);
            if (in_array($category, ['FOOD', 'LAUNDRY', 'EXTRA_BED', 'OTHER'], true)) {
                $payload['category'] = $category;
            }
        }
        if (isset($data['unit_price'])) {
            $payload['unit_price'] = (float) $data['unit_price'];
        }
        if (isset($data['tax_rate_percent'])) {
            $payload['tax_rate_percent'] = (float) $data['tax_rate_percent'];
        }
        if (isset($data['is_active'])) {
            $payload['is_active'] = (bool) $data['is_active'];
        }
        if (array_key_exists('description', $data)) {
            $payload['description'] = $data['description'];
        }

        $service->update($payload);
        return $service->fresh();
    }

    /**
     * Attach a chargeable service order to an existing booking.
     */
    public function addServiceToBooking(Booking $booking, array $data, User $user): BookingService
    {
        if (in_array($booking->status, ['CANCELLED', 'EXPIRED'], true)) {
            throw new InvalidArgumentException("Cannot add service charges to a booking with status: {$booking->status}.");
        }

        $service = Service::findOrFail($data['service_id']);
        if (!$service->is_active) {
            throw new InvalidArgumentException("Service '{$service->name}' is currently deactivated.");
        }

        $quantity = (int) ($data['quantity'] ?? 1);
        if ($quantity < 1) {
            throw new InvalidArgumentException("Quantity must be at least 1.");
        }

        $roomId = isset($data['room_id']) ? (int) $data['room_id'] : null;
        if ($roomId !== null) {
            // Verify room is allocated to this booking
            $isRoomAllocated = $booking->bookingRooms()->where('room_id', $roomId)->exists();
            if (!$isRoomAllocated) {
                $room = Room::find($roomId);
                $roomNum = $room ? $room->room_number : "ID {$roomId}";
                throw new InvalidArgumentException("Room {$roomNum} is not part of reservation {$booking->booking_number}.");
            }
        }

        $serviceDate = $data['service_date'] ?? Carbon::today()->format('Y-m-d');
        $unitPrice = (float) $service->unit_price;
        $subtotal = round($unitPrice * $quantity, 2);
        $taxRate = (float) $service->tax_rate_percent;
        $taxAmount = round(($subtotal * $taxRate) / 100, 2);
        $totalAmount = round($subtotal + $taxAmount, 2);

        return DB::transaction(function () use (
            $booking,
            $service,
            $roomId,
            $quantity,
            $unitPrice,
            $subtotal,
            $taxRate,
            $taxAmount,
            $totalAmount,
            $serviceDate,
            $user,
            $data
        ) {
            $bookingService = BookingService::create([
                'booking_id' => $booking->id,
                'service_id' => $service->id,
                'room_id' => $roomId,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
                'tax_rate_percent' => $taxRate,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'service_date' => $serviceDate,
                'added_by' => $user->id,
                'notes' => $data['notes'] ?? null,
            ]);

            // Increment booking totals
            $booking->update([
                'total_service_charges' => round((float) $booking->total_service_charges + $subtotal, 2),
                'total_tax_amount' => round((float) $booking->total_tax_amount + $taxAmount, 2),
                'grand_total' => round((float) $booking->grand_total + $totalAmount, 2),
            ]);

            // If an invoice is already issued, append the item and adjust totals
            $invoice = $booking->invoice;
            if ($invoice && !in_array($invoice->status, ['VOID'], true)) {
                $desc = "{$service->name} (Qty: {$quantity})";
                if ($roomId) {
                    $room = Room::find($roomId);
                    if ($room) {
                        $desc .= " - Room {$room->room_number}";
                    }
                }

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'item_type' => 'SERVICE_CHARGE',
                    'booking_service_id' => $bookingService->id,
                    'description' => $desc,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                    'tax_rate_percent' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'total_amount' => $totalAmount,
                ]);

                $newTotal = round((float) $invoice->total_amount + $totalAmount, 2);
                $newSubtotal = round((float) $invoice->subtotal + $subtotal, 2);
                $newTax = round((float) $invoice->tax_amount + $taxAmount, 2);
                $newBalance = max(0.00, round($newTotal - (float) $invoice->paid_amount, 2));

                $invoice->update([
                    'subtotal' => $newSubtotal,
                    'tax_amount' => $newTax,
                    'total_amount' => $newTotal,
                    'balance_due' => $newBalance,
                    'status' => $newBalance <= 0 ? 'PAID' : ($invoice->paid_amount > 0 ? 'PARTIALLY_PAID' : 'ISSUED'),
                ]);
            }

            return $bookingService->load(['service', 'room', 'addedBy']);
        });
    }

    /**
     * Remove a service order from a booking and reverse charges.
     */
    public function removeServiceFromBooking(BookingService $bookingService): bool
    {
        return DB::transaction(function () use ($bookingService) {
            $booking = $bookingService->booking;

            // Decrement booking totals
            $subtotal = (float) $bookingService->subtotal;
            $taxAmount = (float) $bookingService->tax_amount;
            $totalAmount = (float) $bookingService->total_amount;

            $booking->update([
                'total_service_charges' => max(0.00, round((float) $booking->total_service_charges - $subtotal, 2)),
                'total_tax_amount' => max(0.00, round((float) $booking->total_tax_amount - $taxAmount, 2)),
                'grand_total' => max(0.00, round((float) $booking->grand_total - $totalAmount, 2)),
            ]);

            // Adjust invoice if item was linked
            $invoiceItem = InvoiceItem::where('booking_service_id', $bookingService->id)->first();
            if ($invoiceItem) {
                $invoice = $invoiceItem->invoice;
                $invoiceItem->delete();

                if ($invoice) {
                    $newSubtotal = max(0.00, round((float) $invoice->subtotal - $subtotal, 2));
                    $newTax = max(0.00, round((float) $invoice->tax_amount - $taxAmount, 2));
                    $newTotal = max(0.00, round((float) $invoice->total_amount - $totalAmount, 2));
                    $newBalance = max(0.00, round($newTotal - (float) $invoice->paid_amount, 2));

                    $invoice->update([
                        'subtotal' => $newSubtotal,
                        'tax_amount' => $newTax,
                        'total_amount' => $newTotal,
                        'balance_due' => $newBalance,
                        'status' => $newBalance <= 0 ? 'PAID' : ($invoice->paid_amount > 0 ? 'PARTIALLY_PAID' : 'ISSUED'),
                    ]);
                }
            }

            return $bookingService->delete();
        });
    }
}
