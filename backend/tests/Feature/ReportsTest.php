<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\BookingService;
use App\Models\CashRegisterTransaction;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\Refund;
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

class ReportsTest extends TestCase
{
    use DatabaseTransactions;

    protected Role $ownerRole;
    protected Role $accountantRole;
    protected Role $receptionistRole;
    protected User $owner;
    protected User $accountant;
    protected User $receptionist;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(HotelOperationsSeeder::class);

        $this->ownerRole = Role::where('name', 'OWNER')->firstOrFail();
        $this->accountantRole = Role::where('name', 'ACCOUNTANT')->firstOrFail();
        $this->receptionistRole = Role::where('name', 'RECEPTIONIST')->firstOrFail();

        $this->owner = User::create([
            'role_id' => $this->ownerRole->id,
            'name' => 'Hotel Owner',
            'email' => 'owner-' . uniqid() . '@hotel.com',
            'password' => Hash::make('Password@123'),
            'is_active' => true,
        ]);

        $this->accountant = User::create([
            'role_id' => $this->accountantRole->id,
            'name' => 'Chief Accountant',
            'email' => 'acc-' . uniqid() . '@hotel.com',
            'password' => Hash::make('Password@123'),
            'is_active' => true,
        ]);

        $this->receptionist = User::create([
            'role_id' => $this->receptionistRole->id,
            'name' => 'Desk Clerk',
            'email' => 'desk-' . uniqid() . '@hotel.com',
            'password' => Hash::make('Password@123'),
            'is_active' => true,
        ]);
    }

    public function test_executive_financial_summary_calculations(): void
    {
        $guest = Guest::create([
            'first_name' => 'Sunil',
            'last_name' => 'Verma',
            'email' => 'sunil-' . uniqid() . '@hotel.com',
            'phone' => '+91 91234 56789',
            'id_type' => 'PASSPORT',
            'id_number' => 'Z9876543',
        ]);

        $room = Room::where('operational_status', 'READY')->firstOrFail();

        // 1. Create a Booking with 5000 grand total
        $booking = Booking::create([
            'booking_number' => 'GV-TEST-' . rand(1000, 9999),
            'guest_id' => $guest->id,
            'check_in_date' => Carbon::today(),
            'check_out_date' => Carbon::today()->addDays(2),
            'booking_source' => 'WALK_IN',
            'status' => 'CONFIRMED',
            'base_room_charges' => 4000.00,
            'total_tax_amount' => 480.00,
            'grand_total' => 4480.00,
            'created_by' => $this->owner->id,
            'created_at' => Carbon::today(),
        ]);

        // 2. Add Booking Room
        BookingRoom::create([
            'booking_id' => $booking->id,
            'room_id' => $room->id,
            'room_type_id' => $room->room_type_id,
            'check_in_date' => Carbon::today(),
            'check_out_date' => Carbon::today()->addDays(2),
            'rate_per_night' => 2000.00,
            'nights_count' => 2,
            'subtotal' => 4000.00,
            'tax_amount' => 480.00,
            'total_amount' => 4480.00,
        ]);

        // 3. Add Service Charge
        $service = Service::first() ?? Service::create([
            'name' => 'Buffet Breakfast',
            'category' => 'FOOD',
            'unit_price' => 500.00,
            'is_active' => true,
        ]);

        BookingService::create([
            'booking_id' => $booking->id,
            'service_id' => $service->id,
            'unit_price' => 500.00,
            'quantity' => 2,
            'subtotal' => 1000.00,
            'total_amount' => 1000.00,
            'service_date' => Carbon::today(),
            'added_by' => $this->owner->id,
        ]);

        // 4. Record Payment of 3000
        $payment = Payment::create([
            'booking_id' => $booking->id,
            'transaction_reference' => 'PAY-TEST-' . rand(100, 999),
            'payment_method' => 'CASH',
            'payment_type' => 'ADVANCE',
            'amount' => 3000.00,
            'status' => 'SUCCESS',
            'received_by' => $this->owner->id,
            'created_at' => Carbon::today(),
        ]);

        // 5. Record Refund of 500
        Refund::create([
            'payment_id' => $payment->id,
            'booking_id' => $booking->id,
            'refund_reference' => 'REF-TEST-' . rand(100, 999),
            'amount' => 500.00,
            'refund_method' => 'CASH',
            'status' => 'PROCESSED',
            'reason' => 'Partial adjustment',
            'processed_by' => $this->owner->id,
            'created_at' => Carbon::today(),
        ]);

        // 6. Record Expense of 800
        $expenseCategory = ExpenseCategory::firstOrCreate(['code' => 'UTIL'], ['name' => 'Utilities', 'description' => 'Power & Water']);
        Expense::create([
            'expense_category_id' => $expenseCategory->id,
            'title' => 'Water Tanker Refill',
            'amount' => 800.00,
            'paid_to' => 'City Water Supplier',
            'expense_date' => Carbon::today()->format('Y-m-d'),
            'payment_method' => 'CASH',
            'recorded_by' => $this->owner->id,
            'created_at' => Carbon::today(),
        ]);

        // Query Financial Summary for Today
        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/reports/summary?range_type=today');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $fin = $response->json('data.financials');

        // Net collections >= 2500
        $this->assertGreaterThanOrEqual(2500.00, (float) $fin['net_collections']);

        // Service revenue >= 1000 (2 * 500)
        $this->assertGreaterThanOrEqual(1000.00, (float) $fin['service_revenue']);

        // Outstanding amount includes 1480 from this booking
        $this->assertGreaterThanOrEqual(1480.00, (float) $fin['outstanding_amount']);
    }

    public function test_room_occupancy_report_metrics(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/reports/occupancy?range_type=7d');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'total_rooms',
                    'days',
                    'total_nights_available',
                    'total_nights_occupied',
                    'occupancy_rate_percent',
                    'average_daily_rate',
                    'revenue_per_available_room',
                    'room_categories',
                ],
            ]);
    }

    public function test_booking_status_report(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/reports/bookings?range_type=30d');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'total_bookings',
                    'status_distribution',
                    'cancellation_rate',
                    'average_booking_value',
                    'average_stay_length_nights',
                    'bookings',
                ],
            ]);
    }

    public function test_payment_collections_by_method_report(): void
    {
        $response = $this->actingAs($this->accountant, 'sanctum')
            ->getJson('/api/reports/payments?range_type=30d');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'total_collected',
                    'total_refunded',
                    'net_collected',
                    'payment_methods',
                    'outstanding_bookings',
                    'refunds',
                ],
            ]);
    }

    public function test_csv_export_endpoint_returns_csv_stream(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')
            ->get('/api/reports/export?type=summary&range_type=30d');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Total Booking Value', $response->getContent());
    }

    public function test_receptionist_cannot_access_financial_summary_but_can_access_occupancy(): void
    {
        // Receptionist accessing financial summary must receive 403 Forbidden
        $finResp = $this->actingAs($this->receptionist, 'sanctum')
            ->getJson('/api/reports/summary');

        $finResp->assertStatus(403);

        // Receptionist accessing room occupancy report must succeed with 200
        $occResp = $this->actingAs($this->receptionist, 'sanctum')
            ->getJson('/api/reports/occupancy');

        $occResp->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_empty_database_scenario_does_not_crash_or_divide_by_zero(): void
    {
        // Query custom future range where no records exist
        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/reports/summary?range_type=custom&from_date=2040-01-01&to_date=2040-01-31');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.financials.total_booking_value', 0)
            ->assertJsonPath('data.financials.cash_profit', 0);
    }
}
