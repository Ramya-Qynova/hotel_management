<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\Booking;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    public function __construct(
        protected InvoiceService $invoiceService
    ) {}

    /**
     * Display a listing of invoices.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Invoice::query()->with(['guest', 'booking', 'generatedBy']);

        if ($request->filled('booking_id')) {
            $query->where('booking_id', $request->query('booking_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', strtoupper($request->query('status')));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('issue_date', '>=', $request->query('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('issue_date', '<=', $request->query('date_to'));
        }

        $invoices = $query->orderByDesc('created_at')->orderByDesc('id')->paginate($request->integer('per_page', 20));

        return ApiResponse::success($invoices, 'Invoices retrieved successfully.');
    }

    /**
     * Display invoice details.
     */
    public function show(Invoice $invoice): JsonResponse
    {
        $invoice->load([
            'items',
            'guest',
            'booking.bookingRooms.room.roomType',
            'booking.bookingServices.service',
            'payments.receivedBy',
            'generatedBy',
        ]);

        return ApiResponse::success($invoice, 'Invoice details retrieved.');
    }

    /**
     * Generate or retrieve official invoice for a booking.
     */
    public function generateForBooking(Request $request, Booking $booking): JsonResponse
    {
        $invoice = $this->invoiceService->generateOrGetInvoice($booking, $request->user());

        return ApiResponse::success($invoice, 'Invoice ready for reservation.', 200);
    }

    /**
     * Retrieve structured printable invoice document.
     */
    public function printable(Invoice $invoice): JsonResponse
    {
        $printableData = $this->invoiceService->getPrintableInvoice($invoice);

        return ApiResponse::success($printableData, 'Printable invoice compiled successfully.');
    }

    /**
     * Add a controlled adjustment or discount line item.
     */
    public function addAdjustment(Request $request, Invoice $invoice): JsonResponse
    {
        $validated = $request->validate([
            'item_type' => ['required', Rule::in(['ADJUSTMENT', 'DISCOUNT', 'DAMAGE_FEE', 'LATE_CHECKOUT'])],
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric',
            'tax_rate_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        try {
            $updated = $this->invoiceService->addAdjustment($invoice, $validated);
            return ApiResponse::success($updated, 'Adjustment line item added to invoice.');
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }
}
