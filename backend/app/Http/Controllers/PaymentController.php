<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\BillingPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function __construct(
        protected BillingPaymentService $billingService
    ) {}

    /**
     * Display a listing of payments with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Payment::query()->with(['booking.guest', 'receivedBy', 'refunds']);

        if ($request->filled('booking_id')) {
            $query->where('booking_id', $request->query('booking_id'));
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', strtoupper($request->query('payment_method')));
        }

        if ($request->filled('status')) {
            $query->where('status', strtoupper($request->query('status')));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('paid_at', '>=', $request->query('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('paid_at', '<=', $request->query('date_to'));
        }

        $payments = $query->orderByDesc('created_at')->orderByDesc('id')->paginate($request->integer('per_page', 20));

        return ApiResponse::success($payments, 'Payments retrieved successfully.');
    }

    /**
     * Record a direct payment for a booking (Cash, UPI, Card, Bank Transfer).
     */
    public function store(Request $request, Booking $booking): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => ['required', Rule::in(['CASH', 'UPI', 'CARD', 'BANK_TRANSFER'])],
            'payment_type' => ['nullable', Rule::in(['ADVANCE', 'INTERIM', 'FINAL_SETTLEMENT', 'SECURITY_DEPOSIT'])],
            'transaction_reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:255',
        ]);

        try {
            $payment = $this->billingService->recordPayment($booking, $validated, $request->user());
            return ApiResponse::success($payment, 'Payment recorded successfully.', 201);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    /**
     * Get complete billing summary for a booking.
     */
    public function billingSummary(Booking $booking): JsonResponse
    {
        $summary = $this->billingService->getBillingSummary($booking);

        return ApiResponse::success($summary, 'Billing summary compiled successfully.');
    }
}
