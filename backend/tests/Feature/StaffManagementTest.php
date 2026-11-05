<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected Role $ownerRole;
    protected Role $adminRole;
    protected Role $receptionistRole;
    protected Role $housekeepingRole;
    protected User $owner;
    protected User $admin;
    protected User $receptionist;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->ownerRole = Role::where('name', 'OWNER')->firstOrFail();
        $this->adminRole = Role::where('name', 'ADMIN')->firstOrFail();
        $this->receptionistRole = Role::where('name', 'RECEPTIONIST')->firstOrFail();
        $this->housekeepingRole = Role::where('name', 'HOUSEKEEPING')->firstOrFail();

        $this->owner = User::create([
            'role_id' => $this->ownerRole->id,
            'name' => 'Principal Owner',
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
            'name' => 'Front Desk Receptionist',
            'email' => 'recep-' . uniqid() . '@hotel.com',
            'password' => Hash::make('Password@123'),
            'is_active' => true,
        ]);
    }

    public function test_owner_can_list_staff_members_with_filters(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/staff?role=RECEPTIONIST');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertNotEmpty($response->json('data.data'));
    }

    public function test_owner_can_create_new_staff_account(): void
    {
        $email = 'newstaff-' . uniqid() . '@hotel.com';
        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/staff', [
                'name' => 'Anil Kumar',
                'email' => $email,
                'password' => 'SecurePass@123',
                'role_id' => $this->receptionistRole->id,
                'is_active' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Anil Kumar')
            ->assertJsonPath('data.email', $email);

        $this->assertDatabaseHas('users', ['email' => $email, 'is_active' => true]);

        // Audit log verified
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'STAFF_CREATED',
            'user_id' => $this->owner->id,
        ]);
    }

    public function test_admin_cannot_create_an_owner_account(): void
    {
        $email = 'fakeowner-' . uniqid() . '@hotel.com';
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/staff', [
                'name' => 'Sneaky Admin',
                'email' => $email,
                'password' => 'SecurePass@123',
                'role_id' => $this->ownerRole->id,
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('users', ['email' => $email]);
    }

    public function test_receptionist_cannot_access_staff_endpoints(): void
    {
        $response = $this->actingAs($this->receptionist, 'sanctum')
            ->getJson('/api/staff');

        $response->assertStatus(403);
    }

    public function test_admin_cannot_modify_or_demote_an_owner(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/staff/{$this->owner->id}", [
                'name' => 'Demoted Owner',
                'role_id' => $this->receptionistRole->id,
            ]);

        $response->assertStatus(403);
    }

    public function test_cannot_demote_or_deactivate_the_last_active_owner(): void
    {
        // Deactivate any other owners so only $this->owner is the single active owner
        User::whereHas('role', fn ($q) => $q->where('name', 'OWNER'))
            ->where('id', '!=', $this->owner->id)
            ->update(['is_active' => false]);

        // Attempt to demote the last active owner to Admin
        $demoteResponse = $this->actingAs($this->owner, 'sanctum')
            ->putJson("/api/staff/{$this->owner->id}", [
                'role_id' => $this->adminRole->id,
            ]);

        $demoteResponse->assertStatus(422)
            ->assertJsonValidationErrors(['role_id']);

        // Attempt to deactivate the last active owner
        $deactivateResponse = $this->actingAs($this->owner, 'sanctum')
            ->patchJson("/api/staff/{$this->owner->id}/toggle-status");

        $deactivateResponse->assertStatus(422);
        $this->assertTrue($this->owner->fresh()->is_active);
    }

    public function test_deactivating_staff_account_blocks_login_and_sanctum_access(): void
    {
        $targetEmail = 'target-' . uniqid() . '@hotel.com';
        $targetUser = User::create([
            'role_id' => $this->receptionistRole->id,
            'name' => 'Target Staff',
            'email' => $targetEmail,
            'password' => Hash::make('Password@123'),
            'is_active' => true,
        ]);

        // Deactivate account
        $toggleResp = $this->actingAs($this->owner, 'sanctum')
            ->patchJson("/api/staff/{$targetUser->id}/toggle-status");

        $toggleResp->assertStatus(200);
        $this->assertFalse($targetUser->fresh()->is_active);

        // Attempt to login with valid password must fail with 403 Forbidden
        $loginResp = $this->postJson('/api/auth/login', [
            'email' => $targetEmail,
            'password' => 'Password@123',
        ]);

        $loginResp->assertStatus(403)
            ->assertJsonPath('success', false);

        // Accessing role-protected route as deactivated user must fail with 403
        $routeResp = $this->actingAs($targetUser->fresh(), 'sanctum')
            ->getJson('/api/test/receptionist-only');

        $routeResp->assertStatus(403);
    }
}

