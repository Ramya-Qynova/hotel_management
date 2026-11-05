<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ExpenseCategory;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HotelServicesAndBillingTest extends TestCase
{
    use DatabaseTransactions;

    protected User $owner;
    protected User $receptionist;
    protected User $accountant;
    protected Booking $booking;
    protected Service $laundryService;
    protected Service $breakfastService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $ownerRole = Role::where('name', 'OWNER')->firstOrFail();
        $receptionistRole = Role::where('name', 'RECEPTIONIST')->firstOrFail();
        $accountantRole = Role::where('name', 'ACCOUNTANT')->firstOrFail();

        $uniq = uniqid();
        $this->owner = User::create([
            'role_id' => $ownerRole->id,
            'name' => 'Owner Admin',
            'email' => "owner-b3-{$uniq}@hotel.com",
            'password' => Hash::make('Password@123'),
        ]);

        $this->receptionist = User::create([
            'role_id' => $receptionistRole->id,
            'name' => 'Front Desk Reception',
            'email' => "recep-b3-{$uniq}@hotel.com",
            'password' => Hash::make('Password@123'),
        ]);

        $this->accountant = User::create([
            'role_id' => $accountantRole->id,
            'name' => 'Finance Accountant',
            'email' => "acc-b3-{$uniq}@hotel.com",
            'password' => Hash::make('Password@123'),
        ]);

        $roomType = RoomType::create([
            'name' => 'Deluxe Suite ' . $uniq,
            'slug' => 'deluxe-suite-' . $uniq,
            'base_price_per_night' => 2000.00,
            'max_occupancy' => 2,
            'is_active' => true,
        ]);

        $room = Room::create([
            'room_number' => 'B3-' . rand(100, 999),
            'room_type_id' => $roomType->id,
            'floor' => 1,
            'operational_status' => 'READY',
            'is_active' => true,
        ]);

        $guest = Guest::create([
            'first_name' => 'Kavita',
            'last_name' => 'Iyer',
            'phone' => '+91987' . rand(1000000, 9999999),
            'email' => "kavita-{$uniq}@example.com",
        ]);

        // Create booking: 2 nights @ 2000 = 4000 + 12% tax (480) = 4480.00
        $bookingResp = $this->actingAs($this->receptionist, 'sanctum')->postJson('/api/bookings', [
            'guest_id' => $guest->id,
            'booking_source' => 'WALK_IN',
            'check_in_date' => date('Y-m-d', strtotime('+1 day')),
            'check_out_date' => date('Y-m-d', strtotime('+3 days')),
            'room_ids' => [$room->id],
        ]);
        $this->booking = Booking::with('bookingRooms')->findOrFail($bookingResp->json('data.id'));

        // Seed sample services
        $this->laundryService = Service::create([
            'name' => 'Express Laundry ' . $uniq,
            'category' => 'LAUNDRY',
            'unit_price' => 300.00,
            'tax_rate_percent' => 18.00,
            'is_active' => true,
        ]);

        $this->breakfastService = Service::create([
            'name' => 'Buffet Breakfast ' . $uniq,
            'category' => 'FOOD',
            'unit_price' => 450.00,
            'tax_rate_percent' => 5.00,
            'is_active' => true,
        ]);
    }

    /**
     * Test service catalog CRUD and role-based access.
     */
    public function test_service_catalog_management(): void
    {
        // Owner can create service
        $resp = $this->actingAs($this->owner, 'sanctum')->postJson('/api/services', [
            'name' => 'Airport Luxury Transfer ' . uniqid(),
            'category' => 'OTHER',
            'unit_price' => 1500.00,
            'tax_rate_percent' => 12.00,
            'description' => 'Chauffeured sedan transfer to terminal',
        ]);
        $resp->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.category', 'OTHER');

        $svcId = $resp->json('data.id');

        // Receptionist cannot create service
        $forbidden = $this->actingAs($this->receptionist, 'sanctum')->postJson('/api/services', [
            'name' => 'Unauthorized Service',
            'category' => 'FOOD',
            'unit_price' => 500,
        ]);
        $forbidden->assertStatus(403);

        // Owner can toggle active
        $toggleResp = $this->actingAs($this->owner, 'sanctum')->patchJson("/api/services/{$svcId}/toggle-active");
        $toggleResp->assertStatus(200)
            ->assertJsonPath('data.is_active', false);
    }

    /**
     * Test attaching and removing chargeable service orders to a reservation.
     */
    public function test_attach_and_remove_service_to_booking(): void
    {
        $initialGrandTotal = (float) $this->booking->grand_total;

        // Add 2 laundry services (2 * 300 = 600 + 18% tax 108 = 708)
        $resp = $this->actingAs($this->receptionist, 'sanctum')->postJson("/api/bookings/{$this->booking->id}/services", [
            'service_id' => $this->laundryService->id,
            'quantity' => 2,
            'notes' => 'Suit dry cleaning and press',
        ]);

        $resp->assertStatus(201)
            ->assertJsonPath('data.quantity', 2)
            ->assertJsonPath('data.unit_price', '300.00')
            ->assertJsonPath('data.subtotal', '600.00')
            ->assertJsonPath('data.tax_amount', '108.00')
            ->assertJsonPath('data.total_amount', '708.00');

        $bsId = $resp->json('data.id');

        $freshBooking = $this->booking->fresh();
        $this->assertEquals(600.00, (float) $freshBooking->total_service_charges);
        $this->assertEquals(round($initialGrandTotal + 708.00, 2), (float) $freshBooking->grand_total);

        // Remove service order
        $delResp = $this->actingAs($this->receptionist, 'sanctum')->deleteJson("/api/booking-services/{$bsId}");
        $delResp->assertStatus(200);

        $freshBookingAfter = $this->booking->fresh();
        $this->assertEquals(0.00, (float) $freshBookingAfter->total_service_charges);
        $this->assertEquals($initialGrandTotal, (float) $freshBookingAfter->grand_total);
    }

    /**
     * Test offline payment recording, overpayment rejection, and cash register integration.
     */
    public function test_payment_recording_and_overpayment_prevention(): void
    {
        $outstanding = (float) $this->booking->grand_total; // 4480.00

        // Attempt overpayment -> MUST FAIL
        $overpayResp = $this->actingAs($this->receptionist, 'sanctum')->postJson("/api/bookings/{$this->booking->id}/payments", [
            'amount' => $outstanding + 500.00,
            'payment_method' => 'CASH',
            'payment_type' => 'ADVANCE',
        ]);
        $overpayResp->assertStatus(422)
            ->assertJsonFragment(['success' => false]);

        // Partial Cash payment of 2000.00
        $payResp = $this->actingAs($this->receptionist, 'sanctum')->postJson("/api/bookings/{$this->booking->id}/payments", [
            'amount' => 2000.00,
            'payment_method' => 'CASH',
            'payment_type' => 'ADVANCE',
            'notes' => 'Front desk advance cash deposit',
        ]);

        $payResp->assertStatus(201)
            ->assertJsonPath('data.status', 'SUCCESS')
            ->assertJsonPath('data.amount', '2000.00');

        $paymentId = $payResp->json('data.id');
        $payment = Payment::find($paymentId);

        // Verify cash register transaction was auto-recorded
        $this->assertNotNull($payment->cashRegisterTransaction);
        $this->assertEquals('CASH_IN', $payment->cashRegisterTransaction->transaction_type);
        $this->assertEquals(2000.00, (float) $payment->cashRegisterTransaction->amount);

        // Verify updated billing summary
        $summaryResp = $this->actingAs($this->accountant, 'sanctum')->getJson("/api/bookings/{$this->booking->id}/billing");
        $summaryResp->assertStatus(200)
            ->assertJsonPath('data.financials.total_paid', 2000)
            ->assertJsonPath('data.financials.is_fully_paid', false);
        $this->assertEquals(round($outstanding - 2000.00, 2), (float) $summaryResp->json('data.financials.outstanding_balance'));
    }

    /**
     * Test refund processing and restriction against exceeding payment amount.
     */
    public function test_refund_processing_and_limits(): void
    {
        // Record successful card payment of 3000.00
        $payResp = $this->actingAs($this->receptionist, 'sanctum')->postJson("/api/bookings/{$this->booking->id}/payments", [
            'amount' => 3000.00,
            'payment_method' => 'CARD',
            'payment_type' => 'ADVANCE',
        ]);
        $paymentId = $payResp->json('data.id');
        $payment = Payment::find($paymentId);

        // 1. Partial refund of 1000.00
        $refResp1 = $this->actingAs($this->accountant, 'sanctum')->postJson("/api/payments/{$payment->id}/refunds", [
            'amount' => 1000.00,
            'refund_method' => 'CARD',
            'reason' => 'Guest requested partial reduction',
        ]);
        $refResp1->assertStatus(201)
            ->assertJsonPath('data.status', 'PROCESSED');

        $this->assertEquals('PARTIALLY_REFUNDED', $payment->fresh()->status);

        // 2. Attempt refund exceeding remaining refundable balance (2000.00 remaining, try 2500.00) -> MUST FAIL
        $exceedResp = $this->actingAs($this->accountant, 'sanctum')->postJson("/api/payments/{$payment->id}/refunds", [
            'amount' => 2500.00,
            'refund_method' => 'CARD',
            'reason' => 'Exceeding remaining balance',
        ]);
        $exceedResp->assertStatus(422);

        // 3. Complete remaining refund of 2000.00
        $refResp2 = $this->actingAs($this->accountant, 'sanctum')->postJson("/api/payments/{$payment->id}/refunds", [
            'amount' => 2000.00,
            'refund_method' => 'CARD',
            'reason' => 'Full remaining settlement refund',
        ]);
        $refResp2->assertStatus(201);

        $this->assertEquals('REFUNDED', $payment->fresh()->status);
    }

    /**
     * Test invoice generation (one invoice per booking constraint) and line item adjustments.
     */
    public function test_invoice_generation_and_adjustments(): void
    {
        // 1. Generate invoice
        $invResp = $this->actingAs($this->receptionist, 'sanctum')->postJson("/api/bookings/{$this->booking->id}/invoice");
        $invResp->assertStatus(200)
            ->assertJsonPath('data.booking_id', $this->booking->id)
            ->assertJsonPath('data.status', 'ISSUED');

        $invoiceId = $invResp->json('data.id');
        $initialTotal = (float) $invResp->json('data.total_amount');

        // 2. Subsequent call returns existing invoice (strict preservation, no silent overwrite)
        $invResp2 = $this->actingAs($this->receptionist, 'sanctum')->postJson("/api/bookings/{$this->booking->id}/invoice");
        $this->assertEquals($invoiceId, $invResp2->json('data.id'));

        // 3. Add adjustment / discount line item
        $adjResp = $this->actingAs($this->accountant, 'sanctum')->postJson("/api/invoices/{$invoiceId}/adjustments", [
            'item_type' => 'DISCOUNT',
            'description' => 'Corporate loyalty discount',
            'amount' => -200.00,
        ]);
        $adjResp->assertStatus(200);

        $freshInv = $adjResp->json('data');
        $this->assertEquals(round($initialTotal - 200.00, 2), (float) $freshInv['total_amount']);

        // 4. Test printable endpoint
        $printResp = $this->actingAs($this->receptionist, 'sanctum')->getJson("/api/invoices/{$invoiceId}/printable");
        $printResp->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'hotel' => ['name', 'address', 'gstin'],
                    'invoice' => ['invoice_number', 'total_amount', 'balance_due'],
                    'guest' => ['name', 'phone'],
                    'items',
                    'payments',
                ],
            ]);
    }

    /**
     * Test expenses and daily cash register reconciliation.
     */
    public function test_expenses_and_cash_register_reconciliation(): void
    {
        $today = date('Y-m-d');

        // 1. Create expense category
        $catResp = $this->actingAs($this->owner, 'sanctum')->postJson('/api/expense-categories', [
            'name' => 'Kitchen Provisions ' . uniqid(),
            'description' => 'Fresh dairy, vegetables and pantry ingredients',
        ]);
        $catResp->assertStatus(201);
        $catId = $catResp->json('data.id');

        // 2. Open Cash register with float 5000.00
        $openResp = $this->actingAs($this->accountant, 'sanctum')->postJson('/api/cash-register/open', [
            'date' => $today,
            'amount' => 5000.00,
            'notes' => 'Morning cash drawer float',
        ]);
        $openResp->assertStatus(201);

        // 3. Record cash expense 800.00
        $expResp = $this->actingAs($this->accountant, 'sanctum')->postJson('/api/expenses', [
            'expense_category_id' => $catId,
            'title' => 'Fresh Milk & Bakery Supplies',
            'amount' => 800.00,
            'payment_method' => 'CASH',
            'expense_date' => $today,
            'paid_to' => 'Local Dairy Vendor',
        ]);
        $expResp->assertStatus(201);

        // 4. Cash summary audit
        $summaryResp = $this->actingAs($this->accountant, 'sanctum')->getJson("/api/cash-register/summary?date={$today}");
        $summaryResp->assertStatus(200)
            ->assertJsonPath('data.opening_balance', 5000)
            ->assertJsonPath('data.cash_expenses', 800)
            ->assertJsonPath('data.expected_closing_balance', 4200);

        // 5. Reconcile and close drawer with actual cash 4200.00 (variance 0.00)
        $closeResp = $this->actingAs($this->accountant, 'sanctum')->postJson('/api/cash-register/reconcile', [
            'date' => $today,
            'actual_cash' => 4200.00,
            'notes' => 'Balanced drawer audit',
        ]);
        $closeResp->assertStatus(200)
            ->assertJsonPath('data.transaction_type', 'CLOSING_BALANCE');
    }
}
