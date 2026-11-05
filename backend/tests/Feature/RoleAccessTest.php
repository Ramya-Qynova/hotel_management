<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use DatabaseTransactions;

    protected User $owner;
    protected User $admin;
    protected User $receptionist;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $ownerRole = Role::where('name', 'OWNER')->firstOrFail();
        $adminRole = Role::where('name', 'ADMIN')->firstOrFail();
        $receptionistRole = Role::where('name', 'RECEPTIONIST')->firstOrFail();

        $id = uniqid();

        $this->owner = User::create([
            'role_id' => $ownerRole->id,
            'name' => 'Owner User',
            'email' => "owner-{$id}@hotel.com",
            'password' => Hash::make('Password@123'),
        ]);

        $this->admin = User::create([
            'role_id' => $adminRole->id,
            'name' => 'Admin User',
            'email' => "admin-{$id}@hotel.com",
            'password' => Hash::make('Password@123'),
        ]);

        $this->receptionist = User::create([
            'role_id' => $receptionistRole->id,
            'name' => 'Receptionist User',
            'email' => "receptionist-{$id}@hotel.com",
            'password' => Hash::make('Password@123'),
        ]);
    }

    public function test_owner_can_access_owner_only_route(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson('/api/test/owner-only');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Authorized: OWNER role verified.',
            ]);
    }

    public function test_receptionist_cannot_access_owner_only_route(): void
    {
        $response = $this->actingAs($this->receptionist)
            ->getJson('/api/test/owner-only');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Forbidden. This action requires one of the following roles: OWNER.',
            ]);
    }

    public function test_both_owner_and_admin_can_access_multi_role_route(): void
    {
        // Owner access
        $ownerResponse = $this->actingAs($this->owner)
            ->getJson('/api/test/admin-or-owner');

        $ownerResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        // Admin access
        $adminResponse = $this->actingAs($this->admin)
            ->getJson('/api/test/admin-or-owner');

        $adminResponse->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_receptionist_cannot_access_admin_or_owner_route(): void
    {
        $response = $this->actingAs($this->receptionist)
            ->getJson('/api/test/admin-or-owner');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_receptionist_can_access_receptionist_only_route(): void
    {
        $response = $this->actingAs($this->receptionist)
            ->getJson('/api/test/receptionist-only');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Authorized: RECEPTIONIST role verified.',
            ]);
    }

    public function test_unauthenticated_user_accessing_role_route_receives_401(): void
    {
        $response = $this->getJson('/api/test/owner-only');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);
    }
}
