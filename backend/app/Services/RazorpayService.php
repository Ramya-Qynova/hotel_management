<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingStatusHistory;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class RazorpayService
{
    protected ?string $keyId;
    protected ?string $keySecret;
    protected ?string $webhookSecret;

    public function __construct()
    {
        $this->keyId = config('services.razorpay.key_id') ?: env('RAZORPAY_KEY_ID');
        $this->keySecret = config('services.razorpay.key_secret') ?: env('RAZORPAY_KEY_SECRET');
        $this->webhookSecret = config('services.razorpay.webhook_secret') ?: env('RAZORPAY_WEBHOOK_SECRET');
    }

    /**
     * Check if Razorpay credentials are fully configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->keyId) && !empty($this->keySecret);
    }

    /**
     * Get the public Razorpay Key ID for frontend checkout.
     */
    public function getPublicKeyId(): ?string
    {
        return $this->keyId;
    }

    /**
     * Create a Razorpay Order and initialize an internal pending payment record.
     *
     * @param Booking $booking
     * @param float $amount
     * @param User|null $user
     * @param array $extraNotes
     * @return array
     */
    public function createOrder(Booking $booking, float $amount, ?User $user = null, array $extraNotes = []): array
    {
        if ($amount <= 0.00) {
            throw new InvalidArgumentException('Payment amount must be greater than zero.');
        }

        $amountInPaise = (int) round($amount * 100);
        $dateStr = Carbon::now()->format('Ymd');
        $uniqueSuffix = strtoupper(Str::random(6));
        $internalRef = "RZP-ORD-{$dateStr}-{$uniqueSuffix}";

        // If credentials are configured, call the live/test Razorpay Orders API
        $gatewayOrderId = null;
        if ($this->isConfigured()) {
            try {
                $response = Http::withBasicAuth($this->keyId, $this->keySecret)
                    ->timeout(15)
                    ->post('https://api.razorpay.com/v1/orders', [
                        'amount' => $amountInPaise,
                        'currency' => 'INR',
                        'receipt' => $booking->booking_number,
                        'notes' => array_merge([
                            'booking_id' => $booking->id,
                            'booking_number' => $booking->booking_number,
                            'guest_id' => $booking->guest_id,
                        ], $extraNotes),
                    ]);

                if ($response->successful()) {
                    $gatewayOrderId = $response->json('id');
                } else {
                    Log::error('[Razorpay]: Order API call failed', ['body' => $response->body()]);
                    throw new RuntimeException('Failed to create Razorpay payment order: ' . ($response->json('error.description') ?? $response->body()));
                }
            } catch (\Exception $e) {
                Log::error('[Razorpay]: Order creation exception', ['error' => $e->getMessage()]);
                throw new RuntimeException('Razorpay communication error: ' . $e->getMessage());
            }
        } else {
            // Test Mode Fallback Order ID when credentials are not yet supplied
            $gatewayOrderId = 'order_test_' . strtolower(Str::random(14));
        }

        // Store internal pending payment record
        $payment = Payment::create([
            'booking_id' => $booking->id,
            'invoice_id' => $booking->invoice?->id,
            'transaction_reference' => $internalRef,
            'payment_method' => 'ONLINE_GATEWAY',
            'payment_type' => 'ADVANCE',
            'amount' => $amount,
            'status' => 'PENDING',
            'payment_gateway' => 'RAZORPAY',
            'gateway_order_id' => $gatewayOrderId,
            'notes' => 'Razorpay checkout order initiated',
            'received_by' => $user?->id,
        ]);

        return [
            'payment_id' => $payment->id,
            'transaction_reference' => $payment->transaction_reference,
            'order_id' => $gatewayOrderId,
            'key_id' => $this->keyId ?: 'rzp_test_placeholder',
            'amount' => $amountInPaise,
            'amount_in_rupees' => $amount,
            'currency' => 'INR',
            'booking_number' => $booking->booking_number,
            'guest_name' => $booking->guest ? "{$booking->guest->first_name} {$booking->guest->last_name}" : 'Guest',
            'guest_email' => $booking->guest?->email,
            'guest_phone' => $booking->guest?->phone,
        ];
    }

    /**
     * Verify payment signature using HMAC SHA256 and constant-time string comparison.
     */
    public function verifyPaymentSignature(string $orderId, string $paymentId, string $signature): bool
    {
        if (empty($orderId) || empty($paymentId) || empty($signature)) {
            return false;
        }

        $secret = $this->keySecret ?: 'test_secret_fallback';
        $payload = "{$orderId}|{$paymentId}";
        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Confirm a Razorpay payment after signature and provider validation.
     */
    public function confirmPayment(
        Payment $payment,
        string $razorpayPaymentId,
        string $razorpaySignature,
        ?User $user = null
    ): Payment {
        // Verify signature first before transaction to prevent rollback on failure recording
        $isValidSignature = $this->verifyPaymentSignature(
            $payment->gateway_order_id,
            $razorpayPaymentId,
            $razorpaySignature
        );

        if (!$isValidSignature) {
            $payment->update([
                'status' => 'FAILED',
                'notes' => 'Payment signature verification failed. Forged or mismatched payload.',
            ]);
            throw new InvalidArgumentException('Invalid Razorpay signature. Payment confirmation rejected.');
        }

        // Verify actual capture status with Razorpay API if live keys are present
        $providerStatus = 'captured';
        $gatewayResponse = null;
        if ($this->isConfigured()) {
            try {
                $response = Http::withBasicAuth($this->keyId, $this->keySecret)
                    ->timeout(15)
                    ->get("https://api.razorpay.com/v1/payments/{$razorpayPaymentId}");

                if ($response->successful()) {
                    $providerStatus = $response->json('status');
                    $gatewayResponse = $response->json();
                } else {
                    Log::warning('[Razorpay]: Unable to fetch payment status from API', ['body' => $response->body()]);
                }
            } catch (\Exception $e) {
                Log::warning('[Razorpay]: Fetch payment exception: ' . $e->getMessage());
            }
        }

        if (!in_array($providerStatus, ['captured', 'authorized'], true)) {
            $payment->update([
                'status' => 'FAILED',
                'gateway_response' => $gatewayResponse,
                'notes' => "Payment rejected by gateway with provider status: {$providerStatus}",
            ]);
            throw new RuntimeException("Payment was not captured by Razorpay. Provider status: {$providerStatus}");
        }

        return DB::transaction(function () use ($payment, $razorpayPaymentId, $razorpaySignature, $gatewayResponse, $user) {
            // Lock payment record to guarantee idempotency
            $lockedPayment = Payment::where('id', $payment->id)->lockForUpdate()->firstOrFail();

            if ($lockedPayment->status === 'SUCCESS') {
                return $lockedPayment; // Already successfully captured
            }

            $now = Carbon::now();
            $lockedPayment->update([
                'status' => 'SUCCESS',
                'gateway_payment_id' => $razorpayPaymentId,
                'gateway_signature' => $razorpaySignature,
                'gateway_response' => $gatewayResponse,
                'paid_at' => $now,
                'received_by' => $user?->id ?? $lockedPayment->received_by,
                'notes' => 'Razorpay online payment captured successfully',
            ]);

            // If booking was in PENDING_PAYMENT, confirm it!
            $booking = $lockedPayment->booking;
            if ($booking && $booking->status === 'PENDING_PAYMENT') {
                $booking->update(['status' => 'CONFIRMED']);

                BookingStatusHistory::create([
                    'booking_id' => $booking->id,
                    'from_status' => 'PENDING_PAYMENT',
                    'to_status' => 'CONFIRMED',
                    'reason' => 'Online payment confirmed via Razorpay',
                    'metadata' => [
                        'razorpay_payment_id' => $razorpayPaymentId,
                        'amount' => $lockedPayment->amount,
                    ],
                    'changed_by' => $user?->id,
                ]);
            }

            // If invoice exists, adjust paid amount and balance due
            $invoice = $booking?->invoice;
            if ($invoice) {
                $newPaid = round((float) $invoice->paid_amount + (float) $lockedPayment->amount, 2);
                $newBalance = max(0.00, round((float) $invoice->total_amount - $newPaid, 2));

                $invoice->update([
                    'paid_amount' => $newPaid,
                    'balance_due' => $newBalance,
                    'status' => $newBalance <= 0 ? 'PAID' : 'PARTIALLY_PAID',
                ]);

                $lockedPayment->update(['invoice_id' => $invoice->id]);
            }

            try {
                app(\App\Services\NotificationService::class)->notifyPaymentReceived($lockedPayment, $booking);
            } catch (\Throwable $e) {}

            return $lockedPayment->fresh(['booking.guest']);
        });
    }

    /**
     * Verify raw webhook payload signature.
     */
    public function verifyWebhookSignature(string $rawPayload, string $signatureHeader): bool
    {
        if (empty($this->webhookSecret) || empty($signatureHeader)) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $rawPayload, $this->webhookSecret);
        return hash_equals($expectedSignature, $signatureHeader);
    }

    /**
     * Process an incoming Razorpay webhook event with idempotency.
     */
    public function processWebhook(array $eventPayload, string $rawPayload, string $signatureHeader): array
    {
        if (!$this->verifyWebhookSignature($rawPayload, $signatureHeader)) {
            throw new InvalidArgumentException('Invalid Razorpay webhook signature.');
        }

        $event = $eventPayload['event'] ?? '';
        $payloadData = $eventPayload['payload'] ?? [];

        Log::info("[Razorpay Webhook]: Received event {$event}");

        switch ($event) {
            case 'payment.captured':
            case 'order.paid':
                $paymentEntity = $payloadData['payment']['entity'] ?? [];
                $orderId = $paymentEntity['order_id'] ?? ($payloadData['order']['entity']['id'] ?? null);
                $paymentId = $paymentEntity['id'] ?? null;

                if (!$orderId) {
                    return ['status' => 'ignored', 'message' => 'No order_id present in webhook entity'];
                }

                $internalPayment = Payment::where('gateway_order_id', $orderId)->first();
                if (!$internalPayment) {
                    Log::warning("[Razorpay Webhook]: Order {$orderId} not found internally");
                    return ['status' => 'ignored', 'message' => 'Order not found in database'];
                }

                if ($internalPayment->status === 'SUCCESS') {
                    return ['status' => 'already_processed', 'payment_id' => $internalPayment->id];
                }

                DB::transaction(function () use ($internalPayment, $paymentId, $paymentEntity) {
                    $locked = Payment::where('id', $internalPayment->id)->lockForUpdate()->firstOrFail();
                    if ($locked->status === 'SUCCESS') {
                        return;
                    }

                    $locked->update([
                        'status' => 'SUCCESS',
                        'gateway_payment_id' => $paymentId,
                        'gateway_response' => $paymentEntity,
                        'paid_at' => Carbon::now(),
                        'notes' => 'Confirmed via Razorpay webhook ' . ($paymentEntity['id'] ?? ''),
                    ]);

                    $booking = $locked->booking;
                    if ($booking && $booking->status === 'PENDING_PAYMENT') {
                        $booking->update(['status' => 'CONFIRMED']);

                        BookingStatusHistory::create([
                            'booking_id' => $booking->id,
                            'from_status' => 'PENDING_PAYMENT',
                            'to_status' => 'CONFIRMED',
                            'reason' => 'Online payment confirmed via Razorpay Webhook',
                            'metadata' => [
                                'event' => 'payment.captured',
                                'payment_id' => $paymentId,
                            ],
                            'changed_by' => null,
                        ]);
                    }

                    // Update invoice
                    $invoice = $booking?->invoice;
                    if ($invoice) {
                        $newPaid = round((float) $invoice->paid_amount + (float) $locked->amount, 2);
                        $newBalance = max(0.00, round((float) $invoice->total_amount - $newPaid, 2));
                        $invoice->update([
                            'paid_amount' => $newPaid,
                            'balance_due' => $newBalance,
                            'status' => $newBalance <= 0 ? 'PAID' : 'PARTIALLY_PAID',
                        ]);
                    }
                });

                return ['status' => 'success', 'event' => $event];

            case 'payment.failed':
                $paymentEntity = $payloadData['payment']['entity'] ?? [];
                $orderId = $paymentEntity['order_id'] ?? null;

                if ($orderId) {
                    $internalPayment = Payment::where('gateway_order_id', $orderId)->first();
                    if ($internalPayment && $internalPayment->status === 'PENDING') {
                        $internalPayment->update([
                            'status' => 'FAILED',
                            'gateway_response' => $paymentEntity,
                            'notes' => 'Payment failed at gateway: ' . ($paymentEntity['error_description'] ?? 'Declined'),
                        ]);
                    }
                }
                return ['status' => 'recorded_failure', 'event' => $event];

            default:
                return ['status' => 'unhandled_event', 'event' => $event];
        }
    }
}
