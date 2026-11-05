<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Guest;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomMaintenance;
use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BookingAndCheckInOutTest extends TestCase
{
    use DatabaseTransactions;

    protected User $receptionist;
    protected User $owner;
    protected User $housekeeping;
    protected RoomType $roomType;
    protected Room $room1;
    protected Room $room2;
    protected Guest $guest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $ownerRole = Role::where('name', 'OWNER')->firstOrFail();
        $receptionistRole = Role::where('name', 'RECEPTIONIST')->firstOrFail();
        $housekeepingRole = Role::where('name', 'HOUSEKEEPING')->firstOrFail();

        $uniq = uniqid();
        $this->owner = User::create([
            'role_id' => $ownerRole->id,
            'name' => 'Owner Admin',
            'email' => "owner-bkg-{$uniq}@hotel.com",
            'password' => Hash::make('Password@123'),
        ]);

        $this->receptionist = User::create([
            'role_id' => $receptionistRole->id,
            'name' => 'Receptionist Frontdesk',
            'email' => "recep-bkg-{$uniq}@hotel.com",
            'password' => Hash::make('Password@123'),
        ]);

        $this->housekeeping = User::create([
            'role_id' => $housekeepingRole->id,
            'name' => 'Housekeeper Clean',
            'email' => "hk-bkg-{$uniq}@hotel.com",
            'password' => Hash::make('Password@123'),
        ]);

        $this->roomType = RoomType::create([
            'name' => 'Executive Suite ' . $uniq,
            'slug' => 'exec-suite-' . $uniq,
            'base_price_per_night' => 3500.00,
            'max_occupancy' => 3,
            'amenities' => ['WiFi', 'Smart TV', 'Balcony'],
            'is_active' => true,
        ]);

        $this->room1 = Room::create([
            'room_number' => 'R-' . rand(100, 499),
            'room_type_id' => $this->roomType->id,
            'floor' => 1,
            'operational_status' => 'READY',
            'is_active' => true,
        ]);

        $this->room2 = Room::create([
            'room_number' => 'R-' . rand(500, 999),
            'room_type_id' => $this->roomType->id,
            'floor' => 1,
            'operational_status' => 'READY',
            'is_active' => true,
        ]);

        $this->guest = Guest::create([
            'first_name' => 'Vikram',
            'last_name' => 'Singhania',
            'email' => "vikram-{$uniq}@example.com",
            'phone' => '+919900' . rand(100000, 999999),
            'id_type' => 'PASSPORT',
            'id_number' => 'Z' . rand(1000000, 9999999),
        ]);
    }

    /**
     * Test single-room booking creation with rate snapshotting.
     */
    public function test_single_room_booking_creation_with_snapshot_rate(): void
    {
        $checkIn = date('Y-m-d', strtotime('+1 day'));
        $checkOut = date('Y-m-d', strtotime('+4 days')); // 3 nights

        $response = $this->actingAs($this->receptionist, 'sanctum')->postJson('/api/bookings', [
            'guest_id' => $this->guest->id,
            'booking_source' => 'WALK_IN',
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'room_ids' => [$this->room1->id],
            'adults_count' => 2,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'CONFIRMED');

        $booking = $response->json('data');
        $this->assertNotEmpty($booking['booking_number']);
        $this->assertCount(1, $booking['booking_rooms']);

        $bookingRoom = $booking['booking_rooms'][0];
        $this->assertEquals(3, $bookingRoom['nights_count']);
        $this->assertEquals('3500.00', $bookingRoom['rate_per_night']);
        $this->assertEquals('10500.00', $bookingRoom['subtotal']);

        // Verify snapshotting: later price changes do not change this booking
        $this->roomType->update(['base_price_per_night' => 5000.00]);

        $freshBooking = Booking::with('bookingRooms')->find($booking['id']);
        $this->assertEquals('10500.00', $freshBooking->base_room_charges);
        $this->assertEquals('3500.00', $freshBooking->bookingRooms->first()->rate_per_night);
    }

    /**
     * Test multi-room booking creation.
     */
    public function test_multi_room_booking_creation(): void
    {
        $checkIn = date('Y-m-d', strtotime('+10 days'));
        $checkOut = date('Y-m-d', strtotime('+12 days')); // 2 nights

        $response = $this->actingAs($this->receptionist, 'sanctum')->postJson('/api/bookings', [
            'guest_id' => $this->guest->id,
            'booking_source' => 'WALK_IN',
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'room_ids' => [$this->room1->id, $this->room2->id],
            'adults_count' => 4,
        ]);

        $response->assertStatus(201);
        $booking = $response->json('data');

        $this->assertCount(2, $booking['booking_rooms']);
        // 2 rooms * 2 nights * 3500 = 14000
        $this->assertEquals('14000.00', $booking['base_room_charges']);
    }

    /**
     * Test date overlap condition prevents double booking:
     * existing_check_in < requested_check_out AND existing_check_out > requested_check_in
     */
    public function test_date_overlap_prevents_double_booking(): void
    {
        // Existing confirmed booking: Days +5 to +10
        $existingCheckIn = date('Y-m-d', strtotime('+5 days'));
        $existingCheckOut = date('Y-m-d', strtotime('+10 days'));

        $this->actingAs($this->receptionist, 'sanctum')->postJson('/api/bookings', [
            'guest_id' => $this->guest->id,
            'booking_source' => 'WALK_IN',
            'check_in_date' => $existingCheckIn,
            'check_out_date' => $existingCheckOut,
            'room_ids' => [$this->room1->id],
        ])->assertStatus(201);

        // Case A: Overlaps middle (Days +6 to +8) -> MUST FAIL
        $this->actingAs($this->receptionist, 'sanctum')->postJson('/api/bookings', [
            'guest_id' => $this->guest->id,
            'check_in_date' => date('Y-m-d', strtotime('+6 days')),
            'check_out_date' => date('Y-m-d', strtotime('+8 days')),
            'room_ids' => [$this->room1->id],
        ])->assertStatus(422)
          ->assertJsonFragment(['success' => false]);

        // Case B: Overlaps start (Days +4 to +7) -> MUST FAIL
        $this->actingAs($this->receptionist, 'sanctum')->postJson('/api/bookings', [
            'guest_id' => $this->guest->id,
            'check_in_date' => date('Y-m-d', strtotime('+4 days')),
            'check_out_date' => date('Y-m-d', strtotime('+7 days')),
            'room_ids' => [$this->room1->id],
        ])->assertStatus(422);

        // Case C: Overlaps end (Days +8 to +12) -> MUST FAIL
        $this->actingAs($this->receptionist, 'sanctum')->postJson('/api/bookings', [
            'guest_id' => $this->guest->id,
            'check_in_date' => date('Y-m-d', strtotime('+8 days')),
            'check_out_date' => date('Y-m-d', strtotime('+12 days')),
            'room_ids' => [$this->room1->id],
        ])->assertStatus(422);

        // Case D: Exact touch boundaries (Days +2 to +5) -> check-out date is exclusive -> MUST SUCCEED
        $this->actingAs($this->receptionist, 'sanctum')->postJson('/api/bookings', [
            'guest_id' => $this->guest->id,
            'check_in_date' => date('Y-m-d', strtotime('+2 days')),
            'check_out_date' => date('Y-m-d', strtotime('+5 days')),
            'room_ids' => [$this->room1->id],
        ])->assertStatus(201);

        // Case E: Exact touch boundaries at check-out (Days +10 to +13) -> MUST SUCCEED
        $this->actingAs($this->receptionist, 'sanctum')->postJson('/api/bookings', [
            'guest_id' => $this->guest->id,
            'check_in_date' => date('Y-m-d', strtotime('+10 days')),
            'check_out_date' => date('Y-m-d', strtotime('+13 days')),
            'room_ids' => [$this->room1->id],
        ])->assertStatus(201);
    }

    /**
     * Test scheduled maintenance prevents booking.
     */
    public function test_scheduled_maintenance_blocks_booking(): void
    {
        $mStart = date('Y-m-d', strtotime('+15 days'));
        $mEnd = date('Y-m-d', strtotime('+18 days'));

        RoomMaintenance::create([
            'room_id' => $this->room2->id,
            'start_date' => $mStart,
            'end_date' => $mEnd,
            'reason' => 'Plumbing upgrade',
            'status' => 'SCHEDULED',
            'reported_by' => $this->owner->id,
        ]);

        // Attempt booking overlapping maintenance
        $resp = $this->actingAs($this->receptionist, 'sanctum')->postJson('/api/bookings', [
            'guest_id' => $this->guest->id,
            'check_in_date' => date('Y-m-d', strtotime('+16 days')),
            'check_out_date' => date('Y-m-d', strtotime('+20 days')),
            'room_ids' => [$this->room2->id],
        ]);

        $resp->assertStatus(422);
    }

    /**
     * Test check-in, checkout, cleaning status transition, and early/late checkout.
     */
    public function test_check_in_and_check_out_flow(): void
    {
        $today = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));

        $bookingResp = $this->actingAs($this->receptionist, 'sanctum')->postJson('/api/bookings', [
            'guest_id' => $this->guest->id,
            'booking_source' => 'WALK_IN',
            'check_in_date' => $today,
            'check_out_date' => $tomorrow,
            'room_ids' => [$this->room1->id],
        ]);
        $bookingResp->assertStatus(201);
        $bookingId = $bookingResp->json('data.id');

        // Check in
        $checkInResp = $this->actingAs($this->receptionist, 'sanctum')->postJson("/api/check-in-out/{$bookingId}/check-in", [
            'expected_check_in_time' => '14:30:00',
        ]);
        $checkInResp->assertStatus(200)
            ->assertJsonPath('data.status', 'CHECKED_IN');

        $this->assertNotNull($checkInResp->json('data.actual_check_in_at'));

        // Check out
        $checkOutResp = $this->actingAs($this->receptionist, 'sanctum')->postJson("/api/check-in-out/{$bookingId}/check-out");
        $checkOutResp->assertStatus(200)
            ->assertJsonPath('data.status', 'CHECKED_OUT');

        $this->assertNotNull($checkOutResp->json('data.actual_check_out_at'));

        // Allocated room MUST now be in CLEANING status
        $this->assertEquals('CLEANING', $this->room1->fresh()->operational_status);

        // Attempting to check-in another booking on room1 while it is still in CLEANING status must fail
        $newBookingResp = $this->actingAs($this->receptionist, 'sanctum')->postJson('/api/bookings', [
            'guest_id' => $this->guest->id,
            'booking_source' => 'WALK_IN',
            'check_in_date' => $today,
            'check_out_date' => $tomorrow,
            'room_ids' => [$this->room1->id],
        ]);
        $newBookingResp->assertStatus(201);
        $newBookingId = $newBookingResp->json('data.id');

        $blockedCheckIn = $this->actingAs($this->receptionist, 'sanctum')->postJson("/api/check-in-out/{$newBookingId}/check-in");
        $blockedCheckIn->assertStatus(422)
            ->assertJsonFragment(['success' => false]);

        // Housekeeper cleans the room and marks it READY
        $cleanResp = $this->actingAs($this->housekeeping, 'sanctum')->postJson("/api/check-in-out/rooms/{$this->room1->id}/cleaned");
        $cleanResp->assertStatus(200)
            ->assertJsonPath('data.operational_status', 'READY');

        // Now check-in succeeds!
        $allowedCheckIn = $this->actingAs($this->receptionist, 'sanctum')->postJson("/api/check-in-out/{$newBookingId}/check-in");
        $allowedCheckIn->assertStatus(200)
            ->assertJsonPath('data.status', 'CHECKED_IN');
    }

    /**
     * Test dashboard dynamic metrics endpoint.
     */
    public function test_dashboard_endpoint_returns_dynamic_metrics(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')->getJson('/api/dashboard?days=7');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'metrics' => [
                        'total_rooms',
                        'occupied_rooms',
                        'available_rooms',
                        'today_check_ins',
                        'today_check_outs',
                        'today_revenue',
                    ],
                    'revenue_overview' => [
                        'days',
                        'data',
                    ],
                    'room_status',
                    'recent_bookings',
                    'room_overview',
                    'hotel_info',
                ],
            ]);
    }
}

