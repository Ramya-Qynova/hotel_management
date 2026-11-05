<?php

namespace Tests\Feature;

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

class RoomAndGuestOperationsTest extends TestCase
{
    use DatabaseTransactions;

    protected User $owner;
    protected User $receptionist;
    protected User $housekeeping;

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
            'name' => 'Owner Testing',
            'email' => "owner-test-{$uniq}@hotel.com",
            'password' => Hash::make('Password@123'),
        ]);

        $this->receptionist = User::create([
            'role_id' => $receptionistRole->id,
            'name' => 'Receptionist Testing',
            'email' => "recep-test-{$uniq}@hotel.com",
            'password' => Hash::make('Password@123'),
        ]);

        $this->housekeeping = User::create([
            'role_id' => $housekeepingRole->id,
            'name' => 'Housekeeper Testing',
            'email' => "housekeeper-test-{$uniq}@hotel.com",
            'password' => Hash::make('Password@123'),
        ]);
    }

    /**
     * Test room category CRUD including dynamic categories like Premium, Deluxe, Suite.
     */
    public function test_owner_can_create_and_manage_dynamic_room_types(): void
    {
        // 1. Create Premium room category dynamically
        $response = $this->actingAs($this->owner, 'sanctum')->postJson('/api/room-types', [
            'name' => 'Premium Suite Room',
            'base_price_per_night' => 4500.00,
            'max_occupancy' => 4,
            'extra_person_charge' => 500.00,
            'extra_bed_charge' => 300.00,
            'amenities' => ['WiFi', 'Air Conditioning', 'King Bed', 'Balcony', 'Jacuzzi'],
            'description' => 'Luxury top-floor suite with city view',
            'is_active' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Premium Suite Room')
            ->assertJsonPath('data.slug', 'premium-suite-room')
            ->assertJsonPath('data.base_price_per_night', '4500.00');

        $typeId = $response->json('data.id');

        // 2. Receptionist cannot create room categories
        $forbiddenResp = $this->actingAs($this->receptionist, 'sanctum')->postJson('/api/room-types', [
            'name' => 'Unauthorized Category',
            'base_price_per_night' => 1000,
            'max_occupancy' => 2,
        ]);
        $forbiddenResp->assertStatus(403);

        // 3. Update the category price and amenities
        $updateResp = $this->actingAs($this->owner, 'sanctum')->putJson("/api/room-types/{$typeId}", [
            'base_price_per_night' => 4800.00,
            'description' => 'Updated luxury suite description',
        ]);
        $updateResp->assertStatus(200)
            ->assertJsonPath('data.base_price_per_night', '4800.00');

        // 4. List categories
        $listResp = $this->actingAs($this->receptionist, 'sanctum')->getJson('/api/room-types');
        $listResp->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    /**
     * Test dynamic physical room creation under any room type.
     */
    public function test_owner_can_create_rooms_under_dynamic_categories(): void
    {
        $roomType = RoomType::create([
            'name' => 'Deluxe Room ' . uniqid(),
            'slug' => 'deluxe-' . uniqid(),
            'base_price_per_night' => 3000.00,
            'max_occupancy' => 2,
            'amenities' => ['WiFi', 'AC'],
        ]);

        $roomNum = 'D-' . rand(100, 999);
        $response = $this->actingAs($this->owner, 'sanctum')->postJson('/api/rooms', [
            'room_number' => $roomNum,
            'room_type_id' => $roomType->id,
            'floor' => 2,
            'operational_status' => 'READY',
            'notes' => 'Corner room with mountain view',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.room_number', $roomNum)
            ->assertJsonPath('data.operational_status', 'READY');

        $roomId = $response->json('data.id');

        // Receptionist can change operational status
        $statusResp = $this->actingAs($this->receptionist, 'sanctum')->patchJson("/api/rooms/{$roomId}/status", [
            'operational_status' => 'CLEANING',
            'notes' => 'Guest just vacated',
        ]);
        $statusResp->assertStatus(200)
            ->assertJsonPath('data.operational_status', 'CLEANING');

        // Housekeeper can set back to READY
        $readyResp = $this->actingAs($this->housekeeping, 'sanctum')->patchJson("/api/rooms/{$roomId}/status", [
            'operational_status' => 'READY',
        ]);
        $readyResp->assertStatus(200)
            ->assertJsonPath('data.operational_status', 'READY');
    }

    /**
     * Test room maintenance scheduling and resolution.
     */
    public function test_room_maintenance_lifecycle(): void
    {
        $roomType = RoomType::create([
            'name' => 'Standard AC ' . uniqid(),
            'slug' => 'std-ac-' . uniqid(),
            'base_price_per_night' => 2000.00,
            'max_occupancy' => 2,
        ]);

        $room = Room::create([
            'room_number' => 'M-' . rand(100, 999),
            'room_type_id' => $roomType->id,
            'floor' => 1,
            'operational_status' => 'READY',
        ]);

        // Schedule maintenance
        $mResp = $this->actingAs($this->owner, 'sanctum')->postJson('/api/room-maintenance', [
            'room_id' => $room->id,
            'start_date' => date('Y-m-d'),
            'end_date' => date('Y-m-d', strtotime('+3 days')),
            'reason' => 'AC unit filter replacement and pipe servicing',
            'status' => 'IN_PROGRESS',
            'maintenance_cost' => 1200.00,
        ]);

        $mResp->assertStatus(201);
        $mId = $mResp->json('data.id');

        // Room operational status updated to MAINTENANCE
        $this->assertEquals('MAINTENANCE', $room->fresh()->operational_status);

        // Resolve maintenance
        $resolveResp = $this->actingAs($this->owner, 'sanctum')->postJson("/api/room-maintenance/{$mId}/resolve", [
            'status' => 'COMPLETED',
            'maintenance_cost' => 1350.00,
            'remarks' => 'Completed ahead of time',
            'room_operational_status' => 'READY',
        ]);

        $resolveResp->assertStatus(200)
            ->assertJsonPath('data.status', 'COMPLETED');

        $this->assertEquals('READY', $room->fresh()->operational_status);
    }

    /**
     * Test Guest CRUD and identification data protection.
     */
    public function test_guest_crud_and_id_masking(): void
    {
        $uniq = uniqid();
        $guestData = [
            'first_name' => 'Rajesh',
            'last_name' => 'Varma',
            'email' => "rajesh-{$uniq}@example.com",
            'phone' => '98765' . rand(10000, 99999),
            'id_type' => 'AADHAAR',
            'id_number' => '5432-8765-9012',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'country' => 'India',
        ];

        // 1. Receptionist creates guest
        $createResp = $this->actingAs($this->receptionist, 'sanctum')->postJson('/api/guests', $guestData);
        $createResp->assertStatus(201)
            ->assertJsonPath('data.first_name', 'Rajesh')
            ->assertJsonPath('data.id_number', '5432-8765-9012');

        $guestId = $createResp->json('data.id');

        // 2. Search guest by phone
        $searchResp = $this->actingAs($this->receptionist, 'sanctum')->getJson("/api/guests?search={$guestData['phone']}");
        $searchResp->assertStatus(200)
            ->assertJsonFragment(['phone' => $guestData['phone']]);

        // 3. Update guest
        $updateResp = $this->actingAs($this->receptionist, 'sanctum')->putJson("/api/guests/{$guestId}", [
            'special_requests' => 'Prefers quiet high-floor room',
        ]);
        $updateResp->assertStatus(200)
            ->assertJsonPath('data.special_requests', 'Prefers quiet high-floor room');
    }
}

