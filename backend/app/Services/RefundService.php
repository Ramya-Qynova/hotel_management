<?php

namespace App\Services;

use App\Models\CashRegisterTransaction;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class RefundService
{
    public function __construct(
        protected RazorpayService $razorpayService
    ) {}

    /**
     * Process a full or partial refund against a successful payment.
     */
    public function processRefund(Payment $payment, array $data, User $user): Refund
    {
        return DB::transaction(function () use ($payment, $data, $user) {
            // Lock payment row to prevent race conditions
            $lockedPayment = Payment::where('id', $payment->id)->lockForUpdate()->firstOrFail();

            if ($lockedPayment->status !== 'SUCCESS' && $lockedPayment->status !== 'PARTIALLY_REFUNDED') {
                throw new InvalidArgumentException("Refund can only be issued against a successful payment. Current payment status: {$lockedPayment->status}.");
            }

            $amount = (float) $data['amount'];
            if ($amount <= 0.00) {
                throw new InvalidArgumentException('Refund amount must be greater than zero.');
            }

            // Calculate existing refunds on this payment
            $alreadyRefunded = (float) Refund::where('payment_id', $lockedPayment->id)
                ->where('status', 'PROCESSED')
                ->sum('amount');

            $maxRefundable = max(0.00, round((float) $lockedPayment->amount - $alreadyRefunded, 2));

            if (bccomp((string) $amount, (string) $maxRefundable, 2) === 1) {
                throw new InvalidArgumentException("Requested refund (₹{$amount}) exceeds maximum refundable balance (₹{$maxRefundable}) on this payment.");
            }

            $cancellationFee = isset($data['cancellation_fee']) ? (float) $data['cancellation_fee'] : 0.00;
            $reason = trim($data['reason'] ?? 'Guest requested refund');
            $refundMethod = strtoupper($data['refund_method'] ?? $lockedPayment->payment_method);

            $dateStr = Carbon::now()->format('Ymd');
            $randomHex = strtoupper(Str::random(4));
            $refundRef = "REF-{$dateStr}-{$randomHex}";
            while (Refund::where('refund_reference', $refundRef)->exists()) {
                $randomHex = strtoupper(Str::random(4));
                $refundRef = "REF-{$dateStr}-{$randomHex}";
            }

            // If original payment was processed via Razorpay and online gateway refund is requested
            $gatewayRefundId = null;
            $gatewayResponse = null;

            if ($lockedPayment->payment_gateway === 'RAZORPAY' && $refundMethod === 'ONLINE_GATEWAY') {
                if ($this->razorpayService->isConfigured()) {
                    try {
                        $keyId = config('services.razorpay.key_id') ?: env('RAZORPAY_KEY_ID');
                        $keySecret = config('services.razorpay.key_secret') ?: env('RAZORPAY_KEY_SECRET');
                        $amountInPaise = (int) round($amount * 100);

                        $rzpPaymentId = $lockedPayment->gateway_payment_id;
                        if (!$rzpPaymentId) {
                            throw new RuntimeException('No Razorpay payment ID on record to process online refund.');
                        }

                        $response = Http::withBasicAuth($keyId, $keySecret)
                            ->timeout(15)
                            ->post("https://api.razorpay.com/v1/payments/{$rzpPaymentId}/refund", [
                                'amount' => $amountInPaise,
                                'notes' => [
                                    'reason' => $reason,
                                    'booking_id' => $lockedPayment->booking_id,
                                    'refund_reference' => $refundRef,
                                ],
                            ]);

                        if ($response->successful()) {
                            $gatewayRefundId = $response->json('id');
                            $gatewayResponse = $response->json();
                        } else {
                            Log::error('[Razorpay Refund]: Provider API error', ['body' => $response->body()]);
                            throw new RuntimeException('Razorpay refund API error: ' . ($response->json('error.description') ?? $response->body()));
                        }
                    } catch (\Exception $e) {
                        Log::error('[Razorpay Refund]: Exception during refund', ['error' => $e->getMessage()]);
                        throw new RuntimeException('Failed to process online refund through Razorpay: ' . $e->getMessage());
                    }
                } else {
                    // Test fallback refund ID
                    $gatewayRefundId = 'rfnd_test_' . strtolower(Str::random(14));
                    $gatewayResponse = ['status' => 'processed', 'mock' => true];
                }
            }

            // Create Refund record
            $refund = Refund::create([
                'payment_id' => $lockedPayment->id,
                'booking_id' => $lockedPayment->booking_id,
                'refund_reference' => $refundRef,
                'amount' => $amount,
                'cancellation_fee' => $cancellationFee,
                'refund_method' => $refundMethod,
                'status' => 'PROCESSED',
                'reason' => $reason,
                'gateway_refund_id' => $gatewayRefundId,
                'gateway_response' => $gatewayResponse,
                'processed_at' => Carbon::now(),
                'processed_by' => $user->id,
            ]);

            // Update parent payment status
            $totalRefundedAfter = round($alreadyRefunded + $amount, 2);
            if (bccomp((string) $totalRefundedAfter, (string) $lockedPayment->amount, 2) >= 0) {
                $lockedPayment->update(['status' => 'REFUNDED']);
            } else {
                $lockedPayment->update(['status' => 'PARTIALLY_REFUNDED']);
            }

            // If cash refund, record cash register movement
            if ($refundMethod === 'CASH') {
                $today = Carbon::today()->format('Y-m-d');
                $lastBalance = (float) (CashRegisterTransaction::where('register_date', $today)
                    ->latest('id')
                    ->value('balance_after') ?? 0.00);

                $balanceAfter = max(0.00, round($lastBalance - $amount, 2));

                CashRegisterTransaction::create([
                    'register_date' => $today,
                    'transaction_type' => 'CASH_OUT',
                    'category' => 'REFUND',
                    'amount' => $amount,
                    'balance_after' => $balanceAfter,
                    'refund_id' => $refund->id,
                    'performed_by' => $user->id,
                    'notes' => "Cash refund for Booking #{$lockedPayment->booking_id} ({$refundRef})",
                ]);
            }

            // Adjust invoice if issued
            $invoice = $lockedPayment->booking?->invoice;
            if ($invoice) {
                $newPaid = max(0.00, round((float) $invoice->paid_amount - $amount, 2));
                $newBalance = max(0.00, round((float) $invoice->total_amount - $newPaid, 2));

                $invoice->update([
                    'paid_amount' => $newPaid,
                    'balance_due' => $newBalance,
                    'status' => $newBalance <= 0 ? 'PAID' : ($newPaid > 0 ? 'PARTIALLY_PAID' : 'ISSUED'),
                ]);
            }

            $processedRefund = $refund->load(['payment', 'booking.guest', 'processedBy']);

            try {
                app(\App\Services\NotificationService::class)->notifyRefundProcessed($processedRefund, $lockedPayment);
            } catch (\Throwable $e) {
                // Ignore notification failure
            }

            return $processedRefund;
        });
    }
}
