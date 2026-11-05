<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Guest;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomMaintenance;
use App\Models\RoomType;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HotelImprovementsValidationTest extends TestCase
{
    use DatabaseTransactions;

    protected User $owner;
    protected User $admin;
    protected User $receptionist;
    protected RoomType $suiteType;
    protected Room $activeRoom;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $ownerRole = Role::where('name', 'OWNER')->firstOrFail();
        $adminRole = Role::where('name', 'ADMIN')->firstOrFail();
        $receptionistRole = Role::where('name', 'RECEPTIONIST')->firstOrFail();

        $uniq = uniqid();
        $this->owner = User::create([
            'name' => 'Owner Testing',
            'email' => "owner-test-{$uniq}@hotel.com",
            'password' => Hash::make('SecretPass123!'),
            'role_id' => $ownerRole->id,
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Admin Testing',
            'email' => "admin-test-{$uniq}@hotel.com",
            'password' => Hash::make('SecretPass123!'),
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $this->receptionist = User::create([
            'name' => 'Receptionist Testing',
            'email' => "rec-test-{$uniq}@hotel.com",
            'password' => Hash::make('SecretPass123!'),
            'role_id' => $receptionistRole->id,
            'is_active' => true,
        ]);

        $this->suiteType = RoomType::firstOrCreate(
            ['slug' => 'test-deluxe-suite'],
            [
                'name' => 'Test Deluxe Suite',
                'base_price_per_night' => 3500.00,
                'max_occupancy' => 3,
                'extra_person_charge' => 500.00,
                'extra_bed_charge' => 300.00,
                'is_active' => true,
            ]
        );

        $this->activeRoom = Room::create([
            'room_number' => 'TST-' . rand(1000, 9999),
            'room_type_id' => $this->suiteType->id,
            'floor' => 4,
            'operational_status' => 'READY',
            'is_active' => true,
        ]);
    }

    /**
     * Requirement 1: Only OWNER or ADMIN can toggle room active status.
     */
    public function test_room_activation_authorization(): void
    {
        // Receptionist cannot toggle active
        $recResp = $this->actingAs($this->receptionist, 'sanctum')
            ->patchJson("/api/rooms/{$this->activeRoom->id}/toggle-active", ['is_active' => false]);
        $recResp->assertStatus(403);

        // Owner can toggle active
        $ownerResp = $this->actingAs($this->owner, 'sanctum')
            ->patchJson("/api/rooms/{$this->activeRoom->id}/toggle-active", ['is_active' => false]);
        $ownerResp->assertStatus(200)
            ->assertJsonPath('data.is_active', false);

        // Admin can toggle back to active
        $adminResp = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/rooms/{$this->activeRoom->id}/toggle-active", ['is_active' => true]);
        $adminResp->assertStatus(200)
            ->assertJsonPath('data.is_active', true);
    }

    /**
     * Requirement 1: Prevent deactivation of rooms that currently have active or upcoming bookings.
     */
    public function test_cannot_deactivate_room_with_active_bookings(): void
    {
        $guest = Guest::create([
            'first_name' => 'Aarav',
            'last_name' => 'Mehta',
            'email' => 'aarav-' . uniqid() . '@example.com',
            'phone' => '9876543210',
        ]);

        $checkIn = Carbon::today()->format('Y-m-d');
        $checkOut = Carbon::today()->addDays(2)->format('Y-m-d');

        $booking = Booking::create([
            'booking_number' => 'BK-TEST-' . rand(1000, 9999),
            'guest_id' => $guest->id,
            'booking_source' => 'WALK_IN',
            'status' => 'CHECKED_IN',
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'adults_count' => 1,
            'children_count' => 0,
            'base_room_charges' => 7000.00,
            'tax_amount' => 840.00,
            'grand_total' => 7840.00,
        ]);

        BookingRoom::create([
            'booking_id' => $booking->id,
            'room_id' => $this->activeRoom->id,
            'room_type_id' => $this->suiteType->id,
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'rate_per_night' => 3500.00,
            'nights_count' => 2,
            'subtotal' => 7000.00,
            'tax_rate_percent' => 12.00,
            'tax_amount' => 840.00,
            'total_amount' => 7840.00,
        ]);

        // Attempt deactivation
        $resp = $this->actingAs($this->owner, 'sanctum')
            ->patchJson("/api/rooms/{$this->activeRoom->id}/toggle-active", ['is_active' => false]);

        $resp->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertTrue($this->activeRoom->fresh()->is_active);
    }

    /**
     * Requirement 1: Cannot permanently delete room with historical booking records.
     */
    public function test_cannot_delete_room_with_booking_history(): void
    {
        $guest = Guest::create([
            'first_name' => 'Neha',
            'last_name' => 'Sharma',
            'email' => 'neha-' . uniqid() . '@example.com',
            'phone' => '9876543211',
        ]);

        $booking = Booking::create([
            'booking_number' => 'BK-HIST-' . rand(1000, 9999),
            'guest_id' => $guest->id,
            'booking_source' => 'WALK_IN',
            'status' => 'CHECKED_OUT',
            'check_in_date' => Carbon::today()->subDays(5)->format('Y-m-d'),
            'check_out_date' => Carbon::today()->subDays(3)->format('Y-m-d'),
            'adults_count' => 1,
            'children_count' => 0,
            'base_room_charges' => 7000.00,
            'tax_amount' => 840.00,
            'grand_total' => 7840.00,
        ]);

        BookingRoom::create([
            'booking_id' => $booking->id,
            'room_id' => $this->activeRoom->id,
            'room_type_id' => $this->suiteType->id,
            'check_in_date' => Carbon::today()->subDays(5)->format('Y-m-d'),
            'check_out_date' => Carbon::today()->subDays(3)->format('Y-m-d'),
            'rate_per_night' => 3500.00,
            'nights_count' => 2,
            'subtotal' => 7000.00,
            'tax_rate_percent' => 12.00,
            'tax_amount' => 840.00,
            'total_amount' => 7840.00,
        ]);

        // Attempt deletion
        $resp = $this->actingAs($this->owner, 'sanctum')
            ->deleteJson("/api/rooms/{$this->activeRoom->id}");

        $resp->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('rooms', ['id' => $this->activeRoom->id]);
    }

    /**
     * Requirement 1: Fresh room without dependencies can be deleted safely.
     */
    public function test_safe_deletion_of_unused_room(): void
    {
        $freshRoom = Room::create([
            'room_number' => 'FRESH-' . rand(1000, 9999),
            'room_type_id' => $this->suiteType->id,
            'floor' => 1,
            'operational_status' => 'READY',
            'is_active' => true,
        ]);

        $resp = $this->actingAs($this->owner, 'sanctum')
            ->deleteJson("/api/rooms/{$freshRoom->id}");

        $resp->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('rooms', ['id' => $freshRoom->id]);
    }

    /**
     * Requirement 3: Availability strictly excludes inactive and maintenance rooms.
     */
    public function test_availability_excludes_inactive_and_maintenance(): void
    {
        $inactiveRoom = Room::create([
            'room_number' => 'INACT-' . rand(1000, 9999),
            'room_type_id' => $this->suiteType->id,
            'floor' => 2,
            'operational_status' => 'READY',
            'is_active' => false,
        ]);

        $maintRoom = Room::create([
            'room_number' => 'MNT-' . rand(1000, 9999),
            'room_type_id' => $this->suiteType->id,
            'floor' => 2,
            'operational_status' => 'MAINTENANCE',
            'is_active' => true,
        ]);

        $checkIn = Carbon::today()->addDays(1)->format('Y-m-d');
        $checkOut = Carbon::today()->addDays(3)->format('Y-m-d');

        $resp = $this->actingAs($this->receptionist, 'sanctum')
            ->getJson("/api/rooms/available?check_in_date={$checkIn}&check_out_date={$checkOut}");

        $resp->assertStatus(200);
        $availableIds = collect($resp->json('data'))->pluck('id')->toArray();

        $this->assertNotContains($inactiveRoom->id, $availableIds);
        $this->assertNotContains($maintRoom->id, $availableIds);
        $this->assertContains($this->activeRoom->id, $availableIds);
    }

    /**
     * Requirement 7: Phone number validation enforces exactly 10 digits.
     */
    public function test_phone_number_validation(): void
    {
        // 1. Valid 10-digit numeric phone succeeds
        $validResp = $this->actingAs($this->receptionist, 'sanctum')->postJson('/api/guests', [
            'first_name' => 'Kiran',
            'last_name' => 'Rao',
            'phone' => '9876543210',
        ]);
        $validResp->assertStatus(201);

        // 2. Too short (< 10 digits) fails
        $shortResp = $this->actingAs($this->receptionist, 'sanctum')->postJson('/api/guests', [
            'first_name' => 'Kiran',
            'last_name' => 'Rao',
            'phone' => '98765432',
        ]);
        $shortResp->assertStatus(422)
            ->assertJsonValidationErrors('phone');

        // 3. Too long (> 10 digits) fails
        $longResp = $this->actingAs($this->receptionist, 'sanctum')->postJson('/api/guests', [
            'first_name' => 'Kiran',
            'last_name' => 'Rao',
            'phone' => '987654321099',
        ]);
        $longResp->assertStatus(422)
            ->assertJsonValidationErrors('phone');

        // 4. Letters or special characters fail
        $alphaResp = $this->actingAs($this->receptionist, 'sanctum')->postJson('/api/guests', [
            'first_name' => 'Kiran',
            'last_name' => 'Rao',
            'phone' => '+91-987654',
        ]);
        $alphaResp->assertStatus(422)
            ->assertJsonValidationErrors('phone');
    }

    /**
     * Requirement 8: List endpoints return latest records first.
     */
    public function test_latest_records_first_sorting(): void
    {
        $guest1 = Guest::create([
            'first_name' => 'First',
            'last_name' => 'Created',
            'phone' => '9876543212',
            'created_at' => Carbon::now()->subMinutes(10),
        ]);

        $guest2 = Guest::create([
            'first_name' => 'Second',
            'last_name' => 'Created',
            'phone' => '9876543213',
            'created_at' => Carbon::now(),
        ]);

        $resp = $this->actingAs($this->receptionist, 'sanctum')
            ->getJson('/api/guests?per_page=10');

        $resp->assertStatus(200);
        $data = $resp->json('data.data');

        // Latest created guest appears before earlier created guest
        $ids = collect($data)->pluck('id')->toArray();
        $pos1 = array_search($guest1->id, $ids);
        $pos2 = array_search($guest2->id, $ids);

        if ($pos1 !== false && $pos2 !== false) {
            $this->assertLessThan($pos1, $pos2, 'Guest 2 should appear before Guest 1 in latest-first ordering');
        }
    }
}
