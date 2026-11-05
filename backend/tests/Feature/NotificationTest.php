<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Guest;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\NotificationService;
use Carbon\Carbon;
use Database\Seeders\HotelOperationsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use DatabaseTransactions;

    protected Role $ownerRole;
    protected Role $receptionistRole;
    protected Role $housekeepingRole;
    protected User $owner;
    protected User $receptionist;
    protected User $housekeeper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(HotelOperationsSeeder::class);

        $this->ownerRole = Role::where('name', 'OWNER')->firstOrFail();
        $this->receptionistRole = Role::where('name', 'RECEPTIONIST')->firstOrFail();
        $this->housekeepingRole = Role::where('name', 'HOUSEKEEPING')->firstOrFail();

        $this->owner = User::create([
            'role_id' => $this->ownerRole->id,
            'name' => 'Owner User',
            'email' => 'owner-' . uniqid() . '@hotel.com',
            'password' => Hash::make('Password@123'),
            'is_active' => true,
        ]);

        $this->receptionist = User::create([
            'role_id' => $this->receptionistRole->id,
            'name' => 'Receptionist User',
            'email' => 'recep-' . uniqid() . '@hotel.com',
            'password' => Hash::make('Password@123'),
            'is_active' => true,
        ]);

        $this->housekeeper = User::create([
            'role_id' => $this->housekeepingRole->id,
            'name' => 'Housekeeper User',
            'email' => 'hk-' . uniqid() . '@hotel.com',
            'password' => Hash::make('Password@123'),
            'is_active' => true,
        ]);

        // Ensure clean notification table baseline for this test transaction
        Notification::query()->delete();
    }

    public function test_can_list_notifications_and_fetch_unread_count(): void
    {
        // Seed a direct notification for receptionist
        $notif = Notification::create([
            'user_id' => $this->receptionist->id,
            'type' => 'NEW_BOOKING',
            'title' => 'New Reservation #GV-1001',
            'message' => 'Guest Ramesh Patel reserved Deluxe AC Room.',
            'data' => ['booking_id' => 1],
            'is_read' => false,
        ]);

        $response = $this->actingAs($this->receptionist, 'sanctum')
            ->getJson('/api/notifications');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.unread_count', 1);

        $this->assertEquals(1, count($response->json('data.notifications')));

        // Unread count endpoint
        $countResp = $this->actingAs($this->receptionist, 'sanctum')
            ->getJson('/api/notifications/unread-count');

        $countResp->assertStatus(200)
            ->assertJsonPath('data.unread_count', 1);
    }

    public function test_can_mark_notification_as_read(): void
    {
        $notif = Notification::create([
            'user_id' => $this->receptionist->id,
            'type' => 'ROOM_CLEANED',
            'title' => 'Room Ready',
            'message' => 'Room 101 has been marked clean.',
            'is_read' => false,
        ]);

        $readResp = $this->actingAs($this->receptionist, 'sanctum')
            ->patchJson("/api/notifications/{$notif->id}/read");

        $readResp->assertStatus(200);
        $this->assertTrue($notif->fresh()->is_read);
        $this->assertNotNull($notif->fresh()->read_at);
    }

    public function test_can_mark_all_notifications_as_read(): void
    {
        Notification::create([
            'user_id' => $this->receptionist->id,
            'type' => 'ALERT_1',
            'title' => 'Alert 1',
            'message' => 'Test alert 1',
            'is_read' => false,
        ]);

        Notification::create([
            'user_id' => $this->receptionist->id,
            'type' => 'ALERT_2',
            'title' => 'Alert 2',
            'message' => 'Test alert 2',
            'is_read' => false,
        ]);

        $markAllResp = $this->actingAs($this->receptionist, 'sanctum')
            ->postJson('/api/notifications/mark-all-read');

        $markAllResp->assertStatus(200);

        $unread = Notification::where('user_id', $this->receptionist->id)
            ->where('is_read', false)
            ->count();

        $this->assertEquals(0, $unread);
    }

    public function test_role_aware_notification_visibility(): void
    {
        // Notification specifically broadcast to HOUSEKEEPING role
        Notification::create([
            'role_id' => $this->housekeepingRole->id,
            'type' => 'ROOM_CLEANING_REQUIRED',
            'title' => 'Turnover Cleaning Required',
            'message' => 'Room 205 needs immediate cleaning.',
            'is_read' => false,
        ]);

        // Housekeeper can see it
        $hkResp = $this->actingAs($this->housekeeper, 'sanctum')
            ->getJson('/api/notifications');

        $hkResp->assertStatus(200)
            ->assertJsonPath('data.unread_count', 1);

        // Receptionist cannot see housekeeping role notifications
        $recepResp = $this->actingAs($this->receptionist, 'sanctum')
            ->getJson('/api/notifications');

        $recepResp->assertStatus(200)
            ->assertJsonPath('data.unread_count', 0);

        // Owner has operational oversight and can see all
        $ownerResp = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/notifications');

        $ownerResp->assertStatus(200)
            ->assertJsonPath('data.unread_count', 1);
    }

    public function test_booking_creation_triggers_notification(): void
    {
        $guest = Guest::first() ?? Guest::create([
            'first_name' => 'Priya',
            'last_name' => 'Sharma',
            'email' => 'priya-' . uniqid() . '@example.com',
            'phone' => '+91 98765 43210',
            'id_type' => 'AADHAAR',
            'id_number' => '9988-7766-5544',
        ]);

        $room = Room::where('operational_status', 'READY')->firstOrFail();

        $bookingResp = $this->actingAs($this->receptionist, 'sanctum')
            ->postJson('/api/bookings', [
                'guest_id' => $guest->id,
                'check_in_date' => Carbon::today()->addDays(5)->format('Y-m-d'),
                'check_out_date' => Carbon::today()->addDays(7)->format('Y-m-d'),
                'room_ids' => [$room->id],
                'guest_count' => 2,
            ]);

        $bookingResp->assertStatus(201);
        $bookingId = $bookingResp->json('data.id');

        // Verify notification created
        $this->assertDatabaseHas('notifications', [
            'type' => 'NEW_BOOKING',
            'is_read' => false,
        ]);
    }
}

