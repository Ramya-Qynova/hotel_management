<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\HotelSetting;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\HotelOperationsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HotelSettingsTest extends TestCase
{
    use DatabaseTransactions;

    protected Role $ownerRole;
    protected Role $adminRole;
    protected Role $receptionistRole;
    protected User $owner;
    protected User $admin;
    protected User $receptionist;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(HotelOperationsSeeder::class);

        $this->ownerRole = Role::where('name', 'OWNER')->firstOrFail();
        $this->adminRole = Role::where('name', 'ADMIN')->firstOrFail();
        $this->receptionistRole = Role::where('name', 'RECEPTIONIST')->firstOrFail();

        $this->owner = User::create([
            'role_id' => $this->ownerRole->id,
            'name' => 'Hotel Owner',
            'email' => 'owner-' . uniqid() . '@hotel.com',
            'password' => Hash::make('Password@123'),
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'role_id' => $this->adminRole->id,
            'name' => 'General Manager',
            'email' => 'admin-' . uniqid() . '@hotel.com',
            'password' => Hash::make('Password@123'),
            'is_active' => true,
        ]);

        $this->receptionist = User::create([
            'role_id' => $this->receptionistRole->id,
            'name' => 'Front Desk Staff',
            'email' => 'recep-' . uniqid() . '@hotel.com',
            'password' => Hash::make('Password@123'),
            'is_active' => true,
        ]);
    }

    public function test_authenticated_user_can_retrieve_hotel_settings(): void
    {
        $response = $this->actingAs($this->receptionist, 'sanctum')
            ->getJson('/api/settings');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'settings' => [
                        'hotel_name',
                        'check_in_time',
                        'check_out_time',
                        'tax_rate_percent',
                    ],
                ],
            ]);
    }

    public function test_owner_can_update_hotel_settings(): void
    {
        $payload = [
            'hotel_name' => 'Grand Vista Luxury Palace',
            'check_in_time' => '15:00',
            'check_out_time' => '12:00',
            'tax_rate_percent' => 18.00,
            'advance_payment_percent' => 60.00,
            'hold_expiration_minutes' => 45,
            'cancellation_policy' => 'Strict 48-hour cancellation policy.',
        ];

        $response = $this->actingAs($this->owner, 'sanctum')
            ->putJson('/api/settings', ['settings' => $payload]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.hotel_name', 'Grand Vista Luxury Palace')
            ->assertJsonPath('data.check_in_time', '15:00');

        $this->assertEquals(18.00, (float) $response->json('data.tax_rate_percent'));

        $this->assertEquals('Grand Vista Luxury Palace', HotelSetting::getValue('hotel_name'));
        $this->assertEquals('15:00', HotelSetting::getValue('check_in_time'));
        $this->assertEquals(18.00, HotelSetting::getValue('tax_rate_percent'));

        // Verify audit log recorded
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'SETTINGS_UPDATED',
            'user_id' => $this->owner->id,
        ]);
    }

    public function test_settings_validation_rejects_invalid_timings_and_percentages(): void
    {
        $invalidPayload = [
            'check_in_time' => '99:99', // Invalid time format
            'tax_rate_percent' => 150.00, // Exceeds 100%
            'hold_expiration_minutes' => 1, // Below minimum 5 minutes
        ];

        $response = $this->actingAs($this->owner, 'sanctum')
            ->putJson('/api/settings', ['settings' => $invalidPayload]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['check_in_time', 'tax_rate_percent', 'hold_expiration_minutes']);
    }

    public function test_receptionist_cannot_modify_hotel_settings(): void
    {
        $response = $this->actingAs($this->receptionist, 'sanctum')
            ->putJson('/api/settings', [
                'settings' => ['hotel_name' => 'Unauthorized Change'],
            ]);

        $response->assertStatus(403);
    }

    public function test_dashboard_reflects_updated_hotel_settings_dynamically(): void
    {
        // Update check-in time and hotel name
        $this->actingAs($this->owner, 'sanctum')
            ->putJson('/api/settings', [
                'settings' => [
                    'hotel_name' => 'The Imperial Vista',
                    'check_in_time' => '15:00',
                    'check_out_time' => '12:00',
                ],
            ]);

        $dashResponse = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/dashboard');

        $dashResponse->assertStatus(200)
            ->assertJsonPath('data.hotel_info.name', 'The Imperial Vista')
            ->assertJsonPath('data.hotel_info.check_in_time', '15:00')
            ->assertJsonPath('data.hotel_info.check_out_time', '12:00');
    }
}
