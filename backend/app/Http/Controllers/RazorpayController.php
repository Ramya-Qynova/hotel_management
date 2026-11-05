<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\RazorpayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RazorpayController extends Controller
{
    public function __construct(
        protected RazorpayService $razorpayService
    ) {}

    /**
     * Create a Razorpay Order for online payment.
     */
    public function createOrder(Request $request, Booking $booking): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1.00',
            'notes' => 'nullable|array',
        ]);

        try {
            $orderData = $this->razorpayService->createOrder(
                $booking,
                (float) $validated['amount'],
                $request->user(),
                $validated['notes'] ?? []
            );

            return ApiResponse::success($orderData, 'Razorpay checkout order created.', 201);
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    /**
     * Verify payment signature from frontend callback.
     */
    public function verifyPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payment_id' => 'required|exists:payments,id',
            'razorpay_payment_id' => 'required|string',
            'razorpay_order_id' => 'required|string',
            'razorpay_signature' => 'required|string',
        ]);

        $payment = Payment::findOrFail($validated['payment_id']);

        // Verify that internal payment's gateway_order_id matches the reported razorpay_order_id
        if ($payment->gateway_order_id !== $validated['razorpay_order_id']) {
            return ApiResponse::error('Order ID mismatch. Verification rejected.', 422);
        }

        try {
            $confirmed = $this->razorpayService->confirmPayment(
                $payment,
                $validated['razorpay_payment_id'],
                $validated['razorpay_signature'],
                $request->user()
            );

            return ApiResponse::success($confirmed, 'Payment verified and captured successfully.');
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        } catch (\Exception $e) {
            return ApiResponse::error('Payment confirmation error: ' . $e->getMessage(), 400);
        }
    }

    /**
     * Webhook listener with raw-body HMAC SHA256 signature verification.
     */
    public function webhook(Request $request): JsonResponse
    {
        $signatureHeader = $request->header('X-Razorpay-Signature');
        $rawPayload = $request->getContent();

        if (empty($signatureHeader) || empty($rawPayload)) {
            Log::warning('[Razorpay Webhook]: Missing signature or payload');
            return response()->json(['success' => false, 'message' => 'Missing signature or payload'], 400);
        }

        try {
            $eventPayload = json_decode($rawPayload, true);
            if (!is_array($eventPayload)) {
                return response()->json(['success' => false, 'message' => 'Invalid JSON payload'], 400);
            }

            $result = $this->razorpayService->processWebhook($eventPayload, $rawPayload, $signatureHeader);

            return response()->json([
                'success' => true,
                'status' => 'acknowledged',
                'result' => $result,
            ], 200);
        } catch (\InvalidArgumentException $e) {
            Log::warning('[Razorpay Webhook Signature Mismatch]: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            Log::error('[Razorpay Webhook Error]: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Internal processing error'], 500);
        }
    }
}
