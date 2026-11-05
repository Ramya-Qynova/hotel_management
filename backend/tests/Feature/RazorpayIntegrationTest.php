<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RazorpayIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Booking $onlineBooking;
    protected string $secret = 'test_razorpay_secret_12345';
    protected string $webhookSecret = 'test_webhook_secret_67890';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        Config::set('services.razorpay.key_id', 'rzp_test_mockKeyId123');
        Config::set('services.razorpay.key_secret', $this->secret);
        Config::set('services.razorpay.webhook_secret', $this->webhookSecret);

        Http::fake([
            'https://api.razorpay.com/v1/orders' => function ($request) {
                $body = json_decode($request->body(), true);
                return Http::response([
                    'id' => 'order_test_' . uniqid(),
                    'entity' => 'order',
                    'amount' => $body['amount'] ?? 100000,
                    'currency' => 'INR',
                    'status' => 'created',
                ], 200);
            },
            'https://api.razorpay.com/v1/payments/*' => Http::response([
                'id' => 'pay_test_' . uniqid(),
                'entity' => 'payment',
                'status' => 'captured',
            ], 200),
        ]);

        $receptionistRole = Role::where('name', 'RECEPTIONIST')->firstOrFail();
        $uniq = uniqid();

        $this->user = User::create([
            'role_id' => $receptionistRole->id,
            'name' => 'Receptionist User',
            'email' => "rzp-tester-{$uniq}@hotel.com",
            'password' => Hash::make('Password@123'),
        ]);

        $roomType = RoomType::create([
            'name' => 'AC Room ' . $uniq,
            'slug' => 'ac-room-' . $uniq,
            'base_price_per_night' => 2500.00,
            'max_occupancy' => 2,
            'is_active' => true,
        ]);

        $room = Room::create([
            'room_number' => 'RZ-' . rand(100, 999),
            'room_type_id' => $roomType->id,
            'floor' => 1,
            'operational_status' => 'READY',
            'is_active' => true,
        ]);

        $guest = Guest::create([
            'first_name' => 'Arjun',
            'last_name' => 'Kapoor',
            'phone' => '+919988' . rand(100000, 999999),
            'email' => "arjun-{$uniq}@example.com",
        ]);

        // Online booking with PENDING_PAYMENT status
        $bkgResp = $this->actingAs($this->user, 'sanctum')->postJson('/api/bookings', [
            'guest_id' => $guest->id,
            'booking_source' => 'ONLINE',
            'check_in_date' => date('Y-m-d', strtotime('+2 days')),
            'check_out_date' => date('Y-m-d', strtotime('+4 days')),
            'room_ids' => [$room->id],
        ]);

        $this->onlineBooking = Booking::findOrFail($bkgResp->json('data.id'));
        $this->assertEquals('PENDING_PAYMENT', $this->onlineBooking->status);
    }

    /**
     * Test server-side order creation for Razorpay checkout.
     */
    public function test_razorpay_order_creation(): void
    {
        $amount = 1400.00;
        $resp = $this->actingAs($this->user, 'sanctum')->postJson("/api/bookings/{$this->onlineBooking->id}/razorpay/create-order", [
            'amount' => $amount,
        ]);

        $resp->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.amount', 140000) // In paise
            ->assertJsonPath('data.currency', 'INR')
            ->assertJsonPath('data.key_id', 'rzp_test_mockKeyId123');

        $this->assertNotEmpty($resp->json('data.order_id'));
        $this->assertNotEmpty($resp->json('data.payment_id'));

        // Booking remains PENDING_PAYMENT until payment is confirmed
        $this->assertEquals('PENDING_PAYMENT', $this->onlineBooking->fresh()->status);
    }

    /**
     * Test payment confirmation succeeds with a valid HMAC SHA256 signature and confirms online booking.
     */
    public function test_valid_signature_captures_payment_and_confirms_booking(): void
    {
        $orderResp = $this->actingAs($this->user, 'sanctum')->postJson("/api/bookings/{$this->onlineBooking->id}/razorpay/create-order", [
            'amount' => 1500.00,
        ]);
        $orderData = $orderResp->json('data');

        $orderId = $orderData['order_id'];
        $paymentId = $orderData['payment_id'];
        $razorpayPaymentId = 'pay_' . uniqid();

        // Compute valid signature: hash_hmac('sha256', "$orderId|$razorpayPaymentId", $secret)
        $validSignature = hash_hmac('sha256', "{$orderId}|{$razorpayPaymentId}", $this->secret);

        $verifyResp = $this->actingAs($this->user, 'sanctum')->postJson('/api/razorpay/verify-payment', [
            'payment_id' => $paymentId,
            'razorpay_order_id' => $orderId,
            'razorpay_payment_id' => $razorpayPaymentId,
            'razorpay_signature' => $validSignature,
        ]);

        $verifyResp->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'SUCCESS');

        // Online booking MUST now be CONFIRMED!
        $this->assertEquals('CONFIRMED', $this->onlineBooking->fresh()->status);
    }

    /**
     * Test forged or invalid signature is strictly rejected and unverified payment never confirms booking.
     */
    public function test_invalid_or_forged_signature_fails_and_does_not_confirm_booking(): void
    {
        $orderResp = $this->actingAs($this->user, 'sanctum')->postJson("/api/bookings/{$this->onlineBooking->id}/razorpay/create-order", [
            'amount' => 1500.00,
        ]);
        $orderData = $orderResp->json('data');

        $orderId = $orderData['order_id'];
        $paymentId = $orderData['payment_id'];
        $razorpayPaymentId = 'pay_' . uniqid();
        $fakeSignature = 'fake_signature_attempt_654321';

        $verifyResp = $this->actingAs($this->user, 'sanctum')->postJson('/api/razorpay/verify-payment', [
            'payment_id' => $paymentId,
            'razorpay_order_id' => $orderId,
            'razorpay_payment_id' => $razorpayPaymentId,
            'razorpay_signature' => $fakeSignature,
        ]);

        $verifyResp->assertStatus(422)
            ->assertJsonPath('success', false);

        // Booking remains strictly unconfirmed
        $this->assertEquals('PENDING_PAYMENT', $this->onlineBooking->fresh()->status);
        $this->assertEquals('FAILED', Payment::find($paymentId)->status);
    }

    /**
     * Test webhook raw signature verification and idempotent processing.
     */
    public function test_razorpay_webhook_processing_and_idempotency(): void
    {
        $orderResp = $this->actingAs($this->user, 'sanctum')->postJson("/api/bookings/{$this->onlineBooking->id}/razorpay/create-order", [
            'amount' => 2000.00,
        ]);
        $orderData = $orderResp->json('data');
        $orderId = $orderData['order_id'];
        $rzpPaymentId = 'pay_webhook_' . uniqid();

        $webhookPayload = [
            'event' => 'payment.captured',
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => $rzpPaymentId,
                        'order_id' => $orderId,
                        'amount' => 200000,
                        'currency' => 'INR',
                        'status' => 'captured',
                    ],
                ],
            ],
        ];

        $rawJson = json_encode($webhookPayload);

        // 1. Invalid webhook signature -> MUST FAIL (400)
        $badResp = $this->postJson('/api/razorpay/webhook', $webhookPayload, [
            'X-Razorpay-Signature' => 'invalid_webhook_sig',
        ]);
        $badResp->assertStatus(400);

        // 2. Valid webhook signature -> MUST SUCCEED (200)
        $validWebhookSig = hash_hmac('sha256', $rawJson, $this->webhookSecret);

        $goodResp = $this->call(
            'POST',
            '/api/razorpay/webhook',
            [],
            [],
            [],
            ['HTTP_X_RAZORPAY_SIGNATURE' => $validWebhookSig, 'CONTENT_TYPE' => 'application/json'],
            $rawJson
        );

        $goodResp->assertStatus(200);
        $this->assertEquals('CONFIRMED', $this->onlineBooking->fresh()->status);

        // 3. Duplicate webhook delivery -> MUST BE IDEMPOTENT without error
        $dupResp = $this->call(
            'POST',
            '/api/razorpay/webhook',
            [],
            [],
            [],
            ['HTTP_X_RAZORPAY_SIGNATURE' => $validWebhookSig, 'CONTENT_TYPE' => 'application/json'],
            $rawJson
        );

        $dupResp->assertStatus(200)
            ->assertJsonPath('result.status', 'already_processed');
    }
}
