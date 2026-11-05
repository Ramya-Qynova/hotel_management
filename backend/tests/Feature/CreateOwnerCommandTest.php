<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CreateOwnerCommandTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_can_create_owner_via_artisan_command_with_force_flag(): void
    {
        $uniqueEmail = 'cmd-owner-' . uniqid() . '@hotel.com';

        $this->artisan('hotel:create-owner', [
            '--name' => 'Initial Owner',
            '--email' => $uniqueEmail,
            '--password' => 'SecurePass123!',
            '--force' => true,
        ])
            ->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'email' => $uniqueEmail,
            'name' => 'Initial Owner',
        ]);

        $user = User::where('email', $uniqueEmail)->firstOrFail();
        $this->assertTrue($user->isOwner());
        $this->assertEquals('OWNER', $user->role->name);
    }

    public function test_warns_when_owner_already_exists_unless_forced(): void
    {
        $uniqueEmail = 'second-owner-' . uniqid() . '@hotel.com';

        // Second owner attempt without --force when an owner already exists in database
        $this->artisan('hotel:create-owner', [
            '--name' => 'Second Owner',
            '--email' => $uniqueEmail,
            '--password' => 'SecurePass123!',
        ])
            ->expectsOutputToContain('An OWNER account already exists')
            ->assertSuccessful();

        // Second user was not created
        $this->assertDatabaseMissing('users', [
            'email' => $uniqueEmail,
        ]);
    }

    public function test_fails_when_password_is_shorter_than_eight_characters(): void
    {
        $uniqueEmail = 'shortpass-' . uniqid() . '@hotel.com';

        $this->artisan('hotel:create-owner', [
            '--name' => 'Short Pass Owner',
            '--email' => $uniqueEmail,
            '--password' => 'short',
            '--force' => true,
        ])
            ->assertFailed();

        $this->assertDatabaseMissing('users', [
            'email' => $uniqueEmail,
        ]);
    }
}
