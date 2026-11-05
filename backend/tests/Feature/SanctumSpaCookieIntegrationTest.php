<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SanctumSpaCookieIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    protected User $owner;
    protected string $ownerEmail;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $ownerRole = Role::where('name', 'OWNER')->firstOrFail();
        $this->ownerEmail = 'spa-owner-' . uniqid() . '@hotel.com';

        $this->owner = User::create([
            'role_id' => $ownerRole->id,
            'name' => 'SPA Owner',
            'email' => $this->ownerEmail,
            'password' => Hash::make('Secret@123'),
        ]);
    }

    /**
     * Test the full SPA cookie lifecycle mimicking React Axios with credentials.
     */
    public function test_full_sanctum_spa_cookie_flow(): void
    {
        $frontendOrigin = 'http://localhost:5173';

        // 1. Initial CSRF Cookie retrieval (React initCsrf())
        $csrfResponse = $this->withHeaders([
            'Origin' => $frontendOrigin,
            'Accept' => 'application/json',
        ])->get('/sanctum/csrf-cookie');

        $csrfResponse->assertStatus(204);
        $csrfResponse->assertCookie('XSRF-TOKEN');

        // Extract cookies
        $xsrfCookie = $csrfResponse->getCookie('XSRF-TOKEN');
        $this->assertNotNull($xsrfCookie);
        $xsrfToken = $xsrfCookie->getValue();

        // 2. Login through SPA with CSRF token and Origin
        $loginResponse = $this->withHeaders([
            'Origin' => $frontendOrigin,
            'Referer' => $frontendOrigin,
            'X-XSRF-TOKEN' => $xsrfToken,
            'Accept' => 'application/json',
        ])->postJson('/api/auth/login', [
            'email' => $this->ownerEmail,
            'password' => 'Secret@123',
        ]);

        $loginResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Login successful.',
                'data' => [
                    'user' => [
                        'email' => $this->ownerEmail,
                        'role' => [
                            'name' => 'OWNER',
                        ],
                    ],
                ],
            ]);

        // 3. Current User Profile request (authenticated via session)
        $meResponse = $this->withHeaders([
            'Origin' => $frontendOrigin,
            'Referer' => $frontendOrigin,
            'Accept' => 'application/json',
        ])->getJson('/api/auth/me');

        $meResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'email' => $this->ownerEmail,
                    ],
                ],
            ]);

        // 4. Role-based endpoint authorization check
        $roleResponse = $this->withHeaders([
            'Origin' => $frontendOrigin,
            'Referer' => $frontendOrigin,
            'Accept' => 'application/json',
        ])->getJson('/api/test/owner-only');

        $roleResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Authorized: OWNER role verified.',
            ]);

        // 5. Change Password flow
        $changePasswordResponse = $this->withHeaders([
            'Origin' => $frontendOrigin,
            'Referer' => $frontendOrigin,
            'X-XSRF-TOKEN' => $xsrfToken,
            'Accept' => 'application/json',
        ])->postJson('/api/auth/change-password', [
            'current_password' => 'Secret@123',
            'password' => 'NewSecr3t@456',
            'password_confirmation' => 'NewSecr3t@456',
        ]);

        $changePasswordResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Password changed successfully.',
            ]);

        // 6. Logout
        $logoutResponse = $this->withHeaders([
            'Origin' => $frontendOrigin,
            'Referer' => $frontendOrigin,
            'X-XSRF-TOKEN' => $xsrfToken,
            'Accept' => 'application/json',
        ])->postJson('/api/auth/logout');

        $logoutResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logged out successfully.',
            ]);

        // 7. Verify access after logout is 401
        $afterLogoutResponse = $this->withHeaders([
            'Origin' => $frontendOrigin,
            'Referer' => $frontendOrigin,
            'Accept' => 'application/json',
        ])->getJson('/api/auth/me');

        $afterLogoutResponse->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);
    }
}
