<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use DatabaseTransactions;

    protected Role $ownerRole;
    protected User $ownerUser;
    protected string $ownerEmail;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->ownerRole = Role::where('name', 'OWNER')->firstOrFail();
        $this->ownerEmail = 'auth-test-' . uniqid() . '@hotel.com';

        $this->ownerUser = User::create([
            'role_id' => $this->ownerRole->id,
            'name' => 'Hotel Owner',
            'email' => $this->ownerEmail,
            'password' => Hash::make('Password@123'),
        ]);
    }

    public function test_login_with_valid_credentials_succeeds(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => $this->ownerEmail,
            'password' => 'Password@123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Login successful.',
                'data' => [
                    'user' => [
                        'id' => $this->ownerUser->id,
                        'email' => $this->ownerEmail,
                        'name' => 'Hotel Owner',
                        'role' => [
                            'name' => 'OWNER',
                        ],
                    ],
                ],
            ]);

        // Sensitive fields must never be exposed
        $response->assertJsonMissing(['password' => 'Password@123']);
        $this->assertArrayNotHasKey('password', $response->json('data.user'));
        $this->assertArrayNotHasKey('remember_token', $response->json('data.user'));

        $this->assertAuthenticatedAs($this->ownerUser);
    }

    public function test_login_with_invalid_credentials_returns_401(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => $this->ownerEmail,
            'password' => 'WrongPassword!',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'The provided credentials do not match our records.',
            ]);

        $this->assertGuest();
    }

    public function test_login_validation_fails_with_missing_fields(): void
    {
        $response = $this->postJson('/api/auth/login', []);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validation failed.',
            ])
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_current_user_profile_returns_authenticated_user_with_role(): void
    {
        $response = $this->actingAs($this->ownerUser)
            ->getJson('/api/auth/me');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Current user profile retrieved successfully.',
                'data' => [
                    'user' => [
                        'id' => $this->ownerUser->id,
                        'email' => $this->ownerEmail,
                        'role' => [
                            'name' => 'OWNER',
                            'display_name' => 'Owner',
                        ],
                    ],
                ],
            ]);
    }

    public function test_unauthenticated_api_access_returns_401(): void
    {
        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_logout_successfully_invalidates_session(): void
    {
        $response = $this->actingAs($this->ownerUser)
            ->postJson('/api/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logged out successfully.',
            ]);

        $this->assertGuest();
    }

    public function test_access_after_logout_returns_401(): void
    {
        // 1. Log out
        $this->actingAs($this->ownerUser)
            ->postJson('/api/auth/logout')
            ->assertStatus(200);

        // 2. Subsequent access to protected endpoint returns 401 Unauthenticated
        $this->getJson('/api/auth/me')
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_change_password_with_valid_current_password_succeeds(): void
    {
        $response = $this->actingAs($this->ownerUser)
            ->postJson('/api/auth/change-password', [
                'current_password' => 'Password@123',
                'password' => 'NewSecurePassword@456',
                'password_confirmation' => 'NewSecurePassword@456',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Password changed successfully.',
            ]);

        // Verify that the new password works for subsequent login
        $this->ownerUser->refresh();
        $this->assertTrue(Hash::check('NewSecurePassword@456', $this->ownerUser->password));
    }

    public function test_change_password_fails_with_incorrect_current_password(): void
    {
        $response = $this->actingAs($this->ownerUser)
            ->postJson('/api/auth/change-password', [
                'current_password' => 'IncorrectCurrentPassword',
                'password' => 'NewSecurePassword@456',
                'password_confirmation' => 'NewSecurePassword@456',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Current password verification failed.',
            ])
            ->assertJsonValidationErrors(['current_password']);

        // Old password remains valid
        $this->ownerUser->refresh();
        $this->assertTrue(Hash::check('Password@123', $this->ownerUser->password));
    }

    public function test_change_password_fails_if_new_password_is_same_as_current(): void
    {
        $response = $this->actingAs($this->ownerUser)
            ->postJson('/api/auth/change-password', [
                'current_password' => 'Password@123',
                'password' => 'Password@123',
                'password_confirmation' => 'Password@123',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }
}
