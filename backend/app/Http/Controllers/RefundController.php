<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\RefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RefundController extends Controller
{
    public function __construct(
        protected RefundService $refundService
    ) {}

    /**
     * Display a listing of refunds.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Refund::query()->with(['payment.booking.guest', 'processedBy']);

        if ($request->filled('booking_id')) {
            $query->where('booking_id', $request->query('booking_id'));
        }

        if ($request->filled('payment_id')) {
            $query->where('payment_id', $request->query('payment_id'));
        }

        if ($request->filled('refund_method')) {
            $query->where('refund_method', strtoupper($request->query('refund_method')));
        }

        if ($request->filled('status')) {
            $query->where('status', strtoupper($request->query('status')));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('processed_at', '>=', $request->query('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('processed_at', '<=', $request->query('date_to'));
        }

        $refunds = $query->orderByDesc('created_at')->orderByDesc('id')->paginate($request->integer('per_page', 20));

        return ApiResponse::success($refunds, 'Refunds retrieved successfully.');
    }

    /**
     * Process a refund against a payment.
     */
    public function store(Request $request, Payment $payment): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'cancellation_fee' => 'nullable|numeric|min:0',
            'refund_method' => ['nullable', Rule::in(['CASH', 'UPI', 'CARD', 'BANK_TRANSFER', 'ONLINE_GATEWAY'])],
            'reason' => 'required|string|max:255',
        ]);

        try {
            $refund = $this->refundService->processRefund($payment, $validated, $request->user());
            return ApiResponse::success($refund, 'Refund processed successfully.', 201);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Refund execution error: ' . $e->getMessage(), 400);
        }
    }
}
