<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Guest;
use App\Models\HotelService;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\HotelOperationsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RoleBasedAccessControlComprehensiveTest extends TestCase
{
    use DatabaseTransactions;

    protected Role $ownerRole;
    protected Role $adminRole;
    protected Role $receptionistRole;
    protected Role $housekeepingRole;
    protected Role $accountantRole;

    protected User $ownerUser;
    protected User $adminUser;
    protected User $receptionistUser;
    protected User $housekeepingUser;
    protected User $accountantUser;
    protected User $inactiveUser;

    protected string $defaultPassword = 'TestAuth@2026Secure!';

    protected Room $testRoom;
    protected Booking $testBooking;

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

        $prefix = 'rbac_' . uniqid() . '_';

        $this->ownerUser = User::create([
            'role_id' => $this->ownerRole->id,
            'name' => 'RBAC Owner',
            'email' => "{$prefix}owner@hotel.com",
            'password' => Hash::make($this->defaultPassword),
            'is_active' => true,
        ]);

        $this->adminUser = User::create([
            'role_id' => $this->adminRole->id,
            'name' => 'RBAC Admin',
            'email' => "{$prefix}admin@hotel.com",
            'password' => Hash::make($this->defaultPassword),
            'is_active' => true,
        ]);

        $this->receptionistUser = User::create([
            'role_id' => $this->receptionistRole->id,
            'name' => 'RBAC Receptionist',
            'email' => "{$prefix}receptionist@hotel.com",
            'password' => Hash::make($this->defaultPassword),
            'is_active' => true,
        ]);

        $this->housekeepingUser = User::create([
            'role_id' => $this->housekeepingRole->id,
            'name' => 'RBAC Housekeeping',
            'email' => "{$prefix}housekeeping@hotel.com",
            'password' => Hash::make($this->defaultPassword),
            'is_active' => true,
        ]);

        $this->accountantUser = User::create([
            'role_id' => $this->accountantRole->id,
            'name' => 'RBAC Accountant',
            'email' => "{$prefix}accountant@hotel.com",
            'password' => Hash::make($this->defaultPassword),
            'is_active' => true,
        ]);

        $this->inactiveUser = User::create([
            'role_id' => $this->receptionistRole->id,
            'name' => 'RBAC Inactive',
            'email' => "{$prefix}inactive@hotel.com",
            'password' => Hash::make($this->defaultPassword),
            'is_active' => false,
        ]);

        $this->testRoom = Room::firstOrFail();
        $this->testBooking = Booking::firstOrFail();
    }

    /**
     * SECTION 2: LOGIN AND AUTHENTICATION TESTS
     */

    public function test_login_succeeds_for_all_five_roles_with_valid_credentials(): void
    {
        $users = [
            'OWNER' => $this->ownerUser,
            'ADMIN' => $this->adminUser,
            'RECEPTIONIST' => $this->receptionistUser,
            'HOUSEKEEPING' => $this->housekeepingUser,
            'ACCOUNTANT' => $this->accountantUser,
        ];

        foreach ($users as $roleName => $user) {
            $response = $this->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => $this->defaultPassword,
            ]);

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Login successful.',
                    'data' => [
                        'user' => [
                            'id' => $user->id,
                            'email' => $user->email,
                            'role' => [
                                'name' => $roleName,
                            ],
                        ],
                    ],
                ]);
        }
    }

    public function test_login_rejects_invalid_password(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => $this->ownerUser->email,
            'password' => 'WrongPassword123!',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'The provided credentials do not match our records.',
            ]);
    }

    public function test_login_rejects_unknown_email(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'nonexistent.user.999@hotel.com',
            'password' => $this->defaultPassword,
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'The provided credentials do not match our records.',
            ]);
    }

    public function test_login_rejects_inactive_account(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => $this->inactiveUser->email,
            'password' => $this->defaultPassword,
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Your account has been deactivated. Please contact an administrator.',
            ]);
    }

    public function test_unauthenticated_users_cannot_access_protected_apis(): void
    {
        $protectedEndpoints = [
            'GET' => [
                '/api/auth/me',
                '/api/dashboard',
                '/api/rooms',
                '/api/guests',
                '/api/bookings',
                '/api/payments',
                '/api/reports/occupancy',
                '/api/reports/summary',
                '/api/staff',
            ],
            'POST' => [
                '/api/rooms',
                '/api/bookings',
                '/api/staff',
            ],
            'PUT' => [
                '/api/settings',
            ],
        ];

        foreach ($protectedEndpoints['GET'] as $url) {
            $this->getJson($url)->assertStatus(401);
        }

        foreach ($protectedEndpoints['POST'] as $url) {
            $this->postJson($url, [])->assertStatus(401);
        }

        foreach ($protectedEndpoints['PUT'] as $url) {
            $this->putJson($url, [])->assertStatus(401);
        }
    }

    public function test_logout_terminates_authenticated_session(): void
    {
        $loginRes = $this->postJson('/api/auth/login', [
            'email' => $this->ownerUser->email,
            'password' => $this->defaultPassword,
        ]);
        $loginRes->assertStatus(200);

        // Verify authenticated access
        $meRes = $this->getJson('/api/auth/me');
        $meRes->assertStatus(200);

        // Logout
        $logoutRes = $this->postJson('/api/auth/logout');
        $logoutRes->assertStatus(200);

        // Subsequent access must be rejected with 401
        $meAfter = $this->getJson('/api/auth/me');
        $meAfter->assertStatus(401);
    }

    public function test_inactive_account_with_existing_session_is_rejected_on_all_endpoints(): void
    {
        // 1. User logs in while active
        $user = User::create([
            'role_id' => $this->receptionistRole->id,
            'name' => 'Active Then Deactivated',
            'email' => 'active.deactivated.' . uniqid() . '@hotel.com',
            'password' => Hash::make($this->defaultPassword),
            'is_active' => true,
        ]);

        $this->actingAs($user);

        // Access works while active
        $this->getJson('/api/auth/me')->assertStatus(200);
        $this->getJson('/api/dashboard')->assertStatus(200);
        $this->getJson('/api/rooms')->assertStatus(200);

        // 2. Account gets deactivated by an administrator
        $user->update(['is_active' => false]);
        $user->refresh();

        // 3. User attempts to reuse session
        $this->getJson('/api/auth/me')
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Your account has been deactivated. Please contact an administrator.',
            ]);

        $this->getJson('/api/dashboard')
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Your account has been deactivated. Please contact an administrator.',
            ]);

        $this->getJson('/api/rooms')
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Your account has been deactivated. Please contact an administrator.',
            ]);
    }

    /**
     * SECTION 3 & 4: ROLE-BASED ACCESS & PERMISSION MATRIX TESTS
     */

    public function test_owner_role_has_full_access_to_all_modules(): void
    {
        $this->actingAs($this->ownerUser);

        // 1. Dashboard
        $this->getJson('/api/dashboard')->assertStatus(200);

        // 2. Bookings & Rate Calculator
        $this->getJson('/api/bookings')->assertStatus(200);
        $this->getJson("/api/bookings/calculate-rates?room_ids[]={$this->testRoom->id}&check_in_date=2026-12-01&check_out_date=2026-12-03")
            ->assertStatus(200);

        // 3. Rooms & Categories
        $this->getJson('/api/rooms')->assertStatus(200);
        $this->getJson('/api/room-types')->assertStatus(200);

        // 4. Guests
        $this->getJson('/api/guests')->assertStatus(200);

        // 5. Check-In / Check-Out
        $this->getJson('/api/check-in-out/arrivals')->assertStatus(200);
        $this->getJson('/api/check-in-out/departures')->assertStatus(200);

        // 6. Services Catalog
        $this->getJson('/api/services')->assertStatus(200);

        // 7. Billing, Payments, Refunds, Invoices, Cash Register, Expenses
        $this->getJson('/api/payments')->assertStatus(200);
        $this->getJson('/api/refunds')->assertStatus(200);
        $this->getJson('/api/invoices')->assertStatus(200);
        $this->getJson('/api/expenses')->assertStatus(200);
        $this->getJson('/api/cash-register/summary')->assertStatus(200);

        // 8. Reports (both operational and financial)
        $this->getJson('/api/reports/occupancy')->assertStatus(200);
        $this->getJson('/api/reports/summary')->assertStatus(200);
        $this->getJson('/api/reports/payments')->assertStatus(200);
        $this->getJson('/api/reports/expenses')->assertStatus(200);

        // 9. Staff & Settings
        $this->getJson('/api/staff')->assertStatus(200);
        $this->getJson('/api/roles')->assertStatus(200);
        $this->getJson('/api/settings')->assertStatus(200);
    }

    public function test_admin_role_access_and_boundaries(): void
    {
        $this->actingAs($this->adminUser);

        // Authorized operational modules
        $this->getJson('/api/dashboard')->assertStatus(200);
        $this->getJson('/api/bookings')->assertStatus(200);
        $this->getJson('/api/rooms')->assertStatus(200);
        $this->getJson('/api/room-types')->assertStatus(200);
        $this->getJson('/api/guests')->assertStatus(200);
        $this->getJson('/api/check-in-out/arrivals')->assertStatus(200);
        $this->getJson('/api/services')->assertStatus(200);
        $this->getJson('/api/payments')->assertStatus(200);
        $this->getJson('/api/refunds')->assertStatus(200);
        $this->getJson('/api/invoices')->assertStatus(200);
        $this->getJson('/api/expenses')->assertStatus(200);
        $this->getJson('/api/cash-register/summary')->assertStatus(200);
        $this->getJson('/api/reports/summary')->assertStatus(200);
        $this->getJson('/api/staff')->assertStatus(200);
        $this->getJson('/api/roles')->assertStatus(200);

        // Settings modification authorized for ADMIN
        $this->putJson('/api/settings', [
            'hotel_name' => 'Grand Vista Hotel Updated',
        ])->assertStatus(200);
    }

    public function test_receptionist_role_permissions_and_restrictions(): void
    {
        $this->actingAs($this->receptionistUser);

        // Authorized:
        $this->getJson('/api/dashboard')->assertStatus(200);
        $this->getJson('/api/bookings')->assertStatus(200);
        $this->getJson('/api/guests')->assertStatus(200);
        $this->getJson('/api/rooms')->assertStatus(200);
        $this->getJson('/api/rooms/available?check_in_date=2026-12-01&check_out_date=2026-12-03')->assertStatus(200);
        $this->getJson('/api/check-in-out/arrivals')->assertStatus(200);
        $this->getJson('/api/check-in-out/departures')->assertStatus(200);
        $this->getJson('/api/services')->assertStatus(200);
        $this->getJson('/api/payments')->assertStatus(200);
        $this->getJson('/api/invoices')->assertStatus(200);
        $this->getJson('/api/reports/occupancy')->assertStatus(200);
        $this->getJson('/api/reports/bookings')->assertStatus(200);
        $this->getJson('/api/reports/check-in-out')->assertStatus(200);
        $this->getJson('/api/reports/services')->assertStatus(200);

        // Forbidden:
        // Cannot access financial reports
        $this->getJson('/api/reports/summary')->assertStatus(403);
        $this->getJson('/api/reports/payments')->assertStatus(403);
        $this->getJson('/api/reports/expenses')->assertStatus(403);
        $this->getJson('/api/reports/cash-register')->assertStatus(403);
        $this->getJson('/api/reports/export')->assertStatus(403);

        // Cannot access refunds, cash register, expenses
        $this->getJson('/api/refunds')->assertStatus(403);
        $this->getJson('/api/cash-register/summary')->assertStatus(403);
        $this->getJson('/api/expenses')->assertStatus(403);
        $this->getJson('/api/expense-categories')->assertStatus(403);

        // Cannot create/modify room types or rooms
        $this->postJson('/api/room-types', ['name' => 'Forbidden Type', 'base_price_per_night' => 1000])->assertStatus(403);
        $this->postJson('/api/rooms', ['room_number' => 'FORBIDDEN-1', 'room_type_id' => 1])->assertStatus(403);

        // Cannot manage staff or change hotel settings
        $this->getJson('/api/staff')->assertStatus(403);
        $this->postJson('/api/staff', [])->assertStatus(403);
        $this->getJson('/api/roles')->assertStatus(403);
        $this->putJson('/api/settings', ['hotel_name' => 'Hacked Hotel'])->assertStatus(403);
    }

    public function test_housekeeping_role_permissions_and_restrictions(): void
    {
        $this->actingAs($this->housekeepingUser);

        // Authorized:
        $this->getJson('/api/dashboard')->assertStatus(200);
        $this->getJson('/api/rooms')->assertStatus(200);
        $this->getJson('/api/room-maintenance')->assertStatus(200);

        // Can update room status to CLEANING or READY
        $this->patchJson("/api/rooms/{$this->testRoom->id}/status", [
            'operational_status' => 'CLEANING',
        ])->assertStatus(200);

        // Can mark room cleaned via handover endpoint (transitions CLEANING -> READY)
        $this->postJson("/api/check-in-out/rooms/{$this->testRoom->id}/cleaned")->assertStatus(200);

        // Forbidden:
        // Cannot create or modify bookings
        $this->getJson('/api/bookings')->assertStatus(403);
        $this->postJson('/api/bookings', [])->assertStatus(403);
        $this->postJson("/api/bookings/{$this->testBooking->id}/cancel", [])->assertStatus(403);

        // Cannot access guest records
        $this->getJson('/api/guests')->assertStatus(403);
        $this->postJson('/api/guests', [])->assertStatus(403);

        // Cannot perform check-in or check-out
        $this->getJson('/api/check-in-out/arrivals')->assertStatus(403);
        $this->getJson('/api/check-in-out/departures')->assertStatus(403);
        $this->postJson("/api/check-in-out/{$this->testBooking->id}/check-in", [])->assertStatus(403);
        $this->postJson("/api/check-in-out/{$this->testBooking->id}/check-out", [])->assertStatus(403);

        // Cannot access billing, payments, invoices, refunds
        $this->getJson('/api/payments')->assertStatus(403);
        $this->postJson("/api/bookings/{$this->testBooking->id}/payments", [])->assertStatus(403);
        $this->postJson("/api/bookings/{$this->testBooking->id}/razorpay/create-order", ['amount' => 500])->assertStatus(403);
        $this->getJson('/api/invoices')->assertStatus(403);
        $this->getJson('/api/refunds')->assertStatus(403);
        $this->getJson('/api/expenses')->assertStatus(403);
        $this->getJson('/api/cash-register/summary')->assertStatus(403);

        // Cannot access reports, staff, or settings
        $this->getJson('/api/reports/occupancy')->assertStatus(403);
        $this->getJson('/api/reports/summary')->assertStatus(403);
        $this->getJson('/api/staff')->assertStatus(403);
        $this->putJson('/api/settings', [])->assertStatus(403);
    }

    public function test_accountant_role_permissions_and_restrictions(): void
    {
        $this->actingAs($this->accountantUser);

        // Authorized:
        $this->getJson('/api/dashboard')->assertStatus(200);
        $this->getJson('/api/payments')->assertStatus(200);
        $this->getJson('/api/refunds')->assertStatus(200);
        $this->getJson('/api/invoices')->assertStatus(200);
        $this->getJson('/api/expenses')->assertStatus(200);
        $this->getJson('/api/expense-categories')->assertStatus(200);
        $this->getJson('/api/cash-register/summary')->assertStatus(200);

        // Financial reports
        $this->getJson('/api/reports/summary')->assertStatus(200);
        $this->getJson('/api/reports/payments')->assertStatus(200);
        $this->getJson('/api/reports/expenses')->assertStatus(200);
        $this->getJson('/api/reports/cash-register')->assertStatus(200);
        $this->getJson('/api/reports/export?report_type=summary')->assertStatus(200);

        // Forbidden:
        // Cannot manage room inventory or room categories
        $this->postJson('/api/room-types', ['name' => 'Accountant Room', 'base_price_per_night' => 1500])->assertStatus(403);
        $this->postJson('/api/rooms', ['room_number' => 'ACC-101', 'room_type_id' => 1])->assertStatus(403);
        $this->patchJson("/api/rooms/{$this->testRoom->id}/status", ['status' => 'READY'])->assertStatus(403);
        $this->deleteJson("/api/rooms/{$this->testRoom->id}")->assertStatus(403);

        // Cannot create/modify bookings or guests
        $this->getJson('/api/bookings')->assertStatus(403);
        $this->postJson('/api/bookings', [])->assertStatus(403);
        $this->getJson('/api/guests')->assertStatus(403);
        $this->postJson('/api/guests', [])->assertStatus(403);

        // Cannot perform check-in or checkout
        $this->getJson('/api/check-in-out/arrivals')->assertStatus(403);
        $this->postJson("/api/check-in-out/{$this->testBooking->id}/check-in", [])->assertStatus(403);

        // Cannot manage staff or change hotel settings
        $this->getJson('/api/staff')->assertStatus(403);
        $this->getJson('/api/roles')->assertStatus(403);
        $this->putJson('/api/settings', ['hotel_name' => 'Accountant Name'])->assertStatus(403);
    }

    /**
     * SECTION 6: SPECIFIC PRIVILEGE ESCALATION & SECURITY TEST CASES
     */

    /**
     * Security Case 1: ADMIN attempts to create another OWNER.
     */
    public function test_security_case_1_admin_cannot_create_another_owner(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->postJson('/api/staff', [
            'name' => 'Escalated Owner',
            'email' => 'escalated.owner.' . uniqid() . '@hotel.com',
            'password' => 'Password@123',
            'role_id' => $this->ownerRole->id,
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Only hotel owners can assign the OWNER role.',
            ]);
    }

    /**
     * Security Case 2: ADMIN attempts to modify an existing OWNER.
     */
    public function test_security_case_2_admin_cannot_modify_existing_owner(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->putJson("/api/staff/{$this->ownerUser->id}", [
            'name' => 'Compromised Owner Name',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Only hotel owners can modify an owner account.',
            ]);
    }

    /**
     * Security Case 3: ADMIN attempts to deactivate an OWNER.
     */
    public function test_security_case_3_admin_cannot_deactivate_owner(): void
    {
        $this->actingAs($this->adminUser);

        // Direct toggle attempt
        $response = $this->patchJson("/api/staff/{$this->ownerUser->id}/toggle-status");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Administrators cannot change the status of an Owner account.',
            ]);
    }

    /**
     * Security Case 4: RECEPTIONIST attempts to access financial reports directly.
     */
    public function test_security_case_4_receptionist_cannot_access_financial_reports(): void
    {
        $this->actingAs($this->receptionistUser);

        $this->getJson('/api/reports/summary')->assertStatus(403);
        $this->getJson('/api/reports/payments')->assertStatus(403);
        $this->getJson('/api/reports/expenses')->assertStatus(403);
        $this->getJson('/api/reports/cash-register')->assertStatus(403);
        $this->getJson('/api/reports/export')->assertStatus(403);
    }

    /**
     * Security Case 5: HOUSEKEEPING attempts to access billing and payment APIs.
     */
    public function test_security_case_5_housekeeping_cannot_access_billing_and_payment_apis(): void
    {
        $this->actingAs($this->housekeepingUser);

        $this->getJson('/api/payments')->assertStatus(403);
        $this->postJson("/api/bookings/{$this->testBooking->id}/payments", [
            'amount' => 500,
            'payment_method' => 'CASH',
        ])->assertStatus(403);
        $this->postJson("/api/bookings/{$this->testBooking->id}/razorpay/create-order", [
            'amount' => 500,
        ])->assertStatus(403);
        $this->getJson('/api/refunds')->assertStatus(403);
        $this->getJson('/api/invoices')->assertStatus(403);
        $this->getJson('/api/cash-register/summary')->assertStatus(403);
    }

    /**
     * Security Case 6: ACCOUNTANT attempts to modify room inventory.
     */
    public function test_security_case_6_accountant_cannot_modify_room_inventory(): void
    {
        $this->actingAs($this->accountantUser);

        $this->postJson('/api/room-types', [
            'name' => 'Accountant Type',
            'base_price_per_night' => 2000,
        ])->assertStatus(403);

        $this->postJson('/api/rooms', [
            'room_number' => 'ACC-999',
            'room_type_id' => 1,
        ])->assertStatus(403);

        $this->patchJson("/api/rooms/{$this->testRoom->id}/status", [
            'operational_status' => 'READY',
        ])->assertStatus(403);

        $this->deleteJson("/api/rooms/{$this->testRoom->id}")->assertStatus(403);
    }

    /**
     * Security Case 7: Non-OWNER attempts to access staff role assignment.
     */
    public function test_security_case_7_non_owner_cannot_access_staff_role_assignment(): void
    {
        $nonOwners = [
            $this->receptionistUser,
            $this->housekeepingUser,
            $this->accountantUser,
        ];

        foreach ($nonOwners as $user) {
            $this->actingAs($user);
            $this->getJson('/api/roles')->assertStatus(403);
            $this->getJson('/api/staff')->assertStatus(403);
            $this->postJson('/api/staff', [
                'name' => 'Hacked Staff',
                'email' => 'hacked.' . uniqid() . '@hotel.com',
                'password' => 'Password@123',
                'role_id' => $this->receptionistRole->id,
            ])->assertStatus(403);
        }
    }

    /**
     * Security Case 8: Any user attempts to change their own role through manipulated payload.
     */
    public function test_security_case_8_user_cannot_elevate_own_role_via_api(): void
    {
        // Receptionist attempts to call PUT /staff/{id} to elevate themselves to OWNER
        $this->actingAs($this->receptionistUser);
        $response = $this->putJson("/api/staff/{$this->receptionistUser->id}", [
            'role_id' => $this->ownerRole->id,
        ]);
        $response->assertStatus(403);

        // Admin attempts to promote themselves to OWNER
        $this->actingAs($this->adminUser);
        $adminElevate = $this->putJson("/api/staff/{$this->adminUser->id}", [
            'role_id' => $this->ownerRole->id,
        ]);
        $adminElevate->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Only hotel owners can assign the OWNER role.',
            ]);
    }

    /**
     * Security Case 9: User attempts to access another user's restricted data by changing record IDs.
     */
    public function test_security_case_9_cannot_access_restricted_data_by_manipulating_record_ids(): void
    {
        // Receptionist attempts to view confidential staff profile with ID=1 (Owner)
        $this->actingAs($this->receptionistUser);
        $this->getJson("/api/staff/{$this->ownerUser->id}")->assertStatus(403);

        // Housekeeping attempts to view booking folio by ID
        $this->actingAs($this->housekeepingUser);
        $this->getJson("/api/bookings/{$this->testBooking->id}")->assertStatus(403);

        // Housekeeping attempts to view guest ID card details
        $guest = Guest::firstOrFail();
        $this->getJson("/api/guests/{$guest->id}")->assertStatus(403);
    }

    /**
     * Security Case 10: Inactive account attempts to reuse an existing session.
     */
    public function test_security_case_10_inactive_account_blocked_from_reusing_session(): void
    {
        $this->actingAs($this->inactiveUser);

        $this->getJson('/api/auth/me')->assertStatus(403);
        $this->getJson('/api/dashboard')->assertStatus(403);
        $this->getJson('/api/rooms')->assertStatus(403);
        $this->getJson('/api/bookings')->assertStatus(403);
        $this->getJson('/api/payments')->assertStatus(403);
    }

    /**
     * Last active owner protection regression test.
     */
    public function test_last_active_owner_cannot_be_deactivated_or_demoted(): void
    {
        $this->actingAs($this->ownerUser);

        // Ensure there is only 1 active owner in the scope of this test
        User::whereHas('role', fn ($q) => $q->where('name', 'OWNER'))
            ->where('id', '!=', $this->ownerUser->id)
            ->update(['is_active' => false]);

        // Attempt to deactivate the last active owner
        $deactivateRes = $this->patchJson("/api/staff/{$this->ownerUser->id}/toggle-status");
        $deactivateRes->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Operation prohibited: Last active owner protection.',
            ]);

        // Attempt to demote the last active owner
        $demoteRes = $this->putJson("/api/staff/{$this->ownerUser->id}", [
            'role_id' => $this->adminRole->id,
        ]);
        $demoteRes->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Operation prohibited: Last active owner protection.',
            ]);
    }
}
