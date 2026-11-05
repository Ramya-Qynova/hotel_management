<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\HotelSetting;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\HotelOperationsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EndToEndSystemWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    protected Role $ownerRole;
    protected Role $adminRole;
    protected Role $receptionistRole;
    protected Role $housekeepingRole;
    protected Role $accountantRole;
    protected User $owner;
    protected User $receptionist;
    protected User $housekeeper;
    protected User $accountant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(HotelOperationsSeeder::class);

        $this->ownerRole = Role::where('name', 'OWNER')->firstOrFail();
        $this->adminRole = Role::where('name', 'ADMIN')->firstOrFail();
        $this->receptionistRole = Role::where('name', 'RECEPTIONIST')->firstOrFail();
        $this->housekeepingRole = Role::where('name', 'HOUSEKEEPING')->firstOrFail();
        $this->accountantRole = Role::where('name', 'ACCOUNTANT')->firstOrFail();

        $this->owner = User::create([
            'role_id' => $this->ownerRole->id,
            'name' => 'Owner Admin',
            'email' => 'owner-e2e-' . uniqid() . '@hotel.com',
            'password' => Hash::make('Password@123'),
            'is_active' => true,
        ]);

        $this->receptionist = User::create([
            'role_id' => $this->receptionistRole->id,
            'name' => 'Reception Desk',
            'email' => 'recep-e2e-' . uniqid() . '@hotel.com',
            'password' => Hash::make('Password@123'),
            'is_active' => true,
        ]);

        $this->housekeeper = User::create([
            'role_id' => $this->housekeepingRole->id,
            'name' => 'Housekeeping Lead',
            'email' => 'hk-e2e-' . uniqid() . '@hotel.com',
            'password' => Hash::make('Password@123'),
            'is_active' => true,
        ]);

        $this->accountant = User::create([
            'role_id' => $this->accountantRole->id,
            'name' => 'Finance Lead',
            'email' => 'acc-e2e-' . uniqid() . '@hotel.com',
            'password' => Hash::make('Password@123'),
            'is_active' => true,
        ]);
    }

    public function test_complete_twelve_step_hotel_management_lifecycle(): void
    {
        // -------------------------------------------------------------
        // Step 1: Create a room category and physical room (by Owner)
        // -------------------------------------------------------------
        $roomTypeResp = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/room-types', [
                'name' => 'Royal Heritage Suite',
                'base_price_per_night' => 5000.00,
                'max_occupancy' => 3,
                'extra_person_charge' => 1000.00,
                'amenities' => ['Balcony', 'Jacuzzi', 'WiFi', 'Mini Bar'],
                'description' => 'Luxury royal heritage suite with royal decor',
            ]);
        $roomTypeResp->assertStatus(201);
        $roomTypeId = $roomTypeResp->json('data.id');

        $roomResp = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/rooms', [
                'room_number' => 'RHS-901',
                'room_type_id' => $roomTypeId,
                'floor' => 9,
                'operational_status' => 'READY',
                'notes' => 'Top floor suite',
            ]);
        $roomResp->assertStatus(201);
        $roomId = $roomResp->json('data.id');

        // -------------------------------------------------------------
        // Step 2: Register a guest (by Receptionist)
        // -------------------------------------------------------------
        $guestResp = $this->actingAs($this->receptionist, 'sanctum')
            ->postJson('/api/guests', [
                'first_name' => 'Vikram',
                'last_name' => 'Singhania',
                'email' => 'vikram-' . uniqid() . '@royal.com',
                'phone' => '9888877777',
                'id_type' => 'PASSPORT',
                'id_number' => 'P12345678',
                'vip_status' => true,
            ]);
        $guestResp->assertStatus(201);
        $guestId = $guestResp->json('data.id');

        // -------------------------------------------------------------
        // Step 3: Create a booking (by Receptionist)
        // -------------------------------------------------------------
        $today = Carbon::today()->format('Y-m-d');
        $twoDaysLater = Carbon::today()->addDays(2)->format('Y-m-d');

        $bookingResp = $this->actingAs($this->receptionist, 'sanctum')
            ->postJson('/api/bookings', [
                'guest_id' => $guestId,
                'check_in_date' => $today,
                'check_out_date' => $twoDaysLater,
                'room_ids' => [$roomId],
                'guest_count' => 2,
            ]);
        $bookingResp->assertStatus(201);
        $bookingId = $bookingResp->json('data.id');

        // -------------------------------------------------------------
        // Step 4: Check in the guest (by Receptionist)
        // -------------------------------------------------------------
        $checkInResp = $this->actingAs($this->receptionist, 'sanctum')
            ->postJson("/api/check-in-out/{$bookingId}/check-in", [
                'expected_check_in_time' => '14:00',
            ]);
        $checkInResp->assertStatus(200);
        $this->assertEquals('CHECKED_IN', Booking::find($bookingId)->status);

        // -------------------------------------------------------------
        // Step 5: Add chargeable services (by Receptionist)
        // -------------------------------------------------------------
        $service = Service::create([
            'name' => 'Airport Luxury Transfer',
            'category' => 'OTHER',
            'unit_price' => 1500.00,
            'is_active' => true,
        ]);

        $serviceResp = $this->actingAs($this->receptionist, 'sanctum')
            ->postJson("/api/bookings/{$bookingId}/services", [
                'service_id' => $service->id,
                'quantity' => 1,
                'room_id' => $roomId,
                'service_date' => $today,
            ]);
        $serviceResp->assertStatus(201);

        // -------------------------------------------------------------
        // Step 6: Record payments (by Receptionist/Accountant)
        // -------------------------------------------------------------
        $booking = Booking::findOrFail($bookingId);
        $amountToPay = (float) $booking->grand_total;

        $paymentResp = $this->actingAs($this->receptionist, 'sanctum')
            ->postJson("/api/bookings/{$bookingId}/payments", [
                'amount' => $amountToPay,
                'payment_method' => 'UPI',
                'payment_type' => 'FINAL_SETTLEMENT',
                'notes' => 'Settled via GPay',
            ]);
        $paymentResp->assertStatus(201);
        $paymentId = $paymentResp->json('data.id');

        // -------------------------------------------------------------
        // Step 7: Generate an invoice (by Accountant/Receptionist)
        // -------------------------------------------------------------
        $invoiceResp = $this->actingAs($this->accountant, 'sanctum')
            ->postJson("/api/bookings/{$bookingId}/invoice");
        $invoiceResp->assertStatus(200);
        $invoiceId = $invoiceResp->json('data.id');
        $this->assertEquals('PAID', Invoice::find($invoiceId)->status);

        // -------------------------------------------------------------
        // Step 8: Record an expense (by Accountant)
        // -------------------------------------------------------------
        $expenseCategory = \App\Models\ExpenseCategory::firstOrCreate(
            ['code' => 'SUPPLY'],
            ['name' => 'Supplies & Maintenance', 'description' => 'Operating supplies']
        );
        $expenseResp = $this->actingAs($this->accountant, 'sanctum')
            ->postJson('/api/expenses', [
                'expense_category_id' => $expenseCategory->id,
                'title' => 'Lobby Fresh Flowers',
                'amount' => 1200.00,
                'paid_to' => 'Orchid Florist',
                'expense_date' => $today,
                'payment_method' => 'CASH',
            ]);
        $expenseResp->assertStatus(201);

        // -------------------------------------------------------------
        // Step 9: Check out the guest (by Receptionist)
        // -------------------------------------------------------------
        $checkOutResp = $this->actingAs($this->receptionist, 'sanctum')
            ->postJson("/api/check-in-out/{$bookingId}/check-out");
        $checkOutResp->assertStatus(200);
        $this->assertEquals('CHECKED_OUT', Booking::find($bookingId)->status);

        // Room operational status must be moved to CLEANING
        $this->assertEquals('CLEANING', Room::find($roomId)->operational_status);

        // -------------------------------------------------------------
        // Step 10: Complete housekeeping (by Housekeeper)
        // -------------------------------------------------------------
        $cleanResp = $this->actingAs($this->housekeeper, 'sanctum')
            ->postJson("/api/check-in-out/rooms/{$roomId}/cleaned");
        $cleanResp->assertStatus(200);
        $this->assertEquals('READY', Room::find($roomId)->operational_status);

        // -------------------------------------------------------------
        // Step 11: Verify reports and dashboard figures (by Owner)
        // -------------------------------------------------------------
        $dashResp = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/dashboard');
        $dashResp->assertStatus(200)
            ->assertJsonPath('success', true);

        $reportResp = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/reports/summary?range_type=today');
        $reportResp->assertStatus(200)
            ->assertJsonPath('success', true);

        $reportData = $reportResp->json('data.financials');
        $this->assertGreaterThan(0, (float) $reportData['amount_collected']);
        $this->assertGreaterThan(0, (float) $reportData['cash_profit']);

        // -------------------------------------------------------------
        // Step 12: Verify staff permissions and notifications
        // -------------------------------------------------------------
        // Receptionist cannot access settings or financial summary
        $this->actingAs($this->receptionist, 'sanctum')
            ->putJson('/api/settings', ['settings' => ['hotel_name' => 'Hacked Name']])
            ->assertStatus(403);

        $this->actingAs($this->receptionist, 'sanctum')
            ->getJson('/api/reports/summary')
            ->assertStatus(403);

        // Notifications exist for new booking, payment, housekeeping cleaning, and room cleaned
        $notifResp = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/notifications');
        $notifResp->assertStatus(200);
        $this->assertNotEmpty($notifResp->json('data.notifications'));
    }
}
