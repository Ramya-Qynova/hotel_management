<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateOwnerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hotel:create-owner
                            {--name= : The full name of the owner}
                            {--email= : The email address of the owner}
                            {--password= : The account password (min 8 characters)}
                            {--force : Allow creating an owner even if an owner account already exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Securely create the initial OWNER account for the Hotel Management System without hardcoding credentials';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->components->info('Hotel Management System: Secure Owner Account Setup');

        // Ensure roles are seeded
        $ownerRole = Role::where('name', 'OWNER')->first();
        if (! $ownerRole) {
            $this->components->info('Roles not detected. Running RoleSeeder...');
            $seeder = new RoleSeeder();
            $seeder->run();
            $ownerRole = Role::where('name', 'OWNER')->first();
        }

        if (! $ownerRole) {
            $this->components->error('Unable to locate or initialize the OWNER role.');
            return self::FAILURE;
        }

        // Check if an OWNER account already exists
        $existingOwner = User::where('role_id', $ownerRole->id)->first();
        if ($existingOwner && ! $this->option('force')) {
            $this->components->warn("An OWNER account already exists: {$existingOwner->name} ({$existingOwner->email})");
            $this->line('If you intentionally wish to create an additional owner account, re-run with --force.');
            return self::SUCCESS;
        }

        // 1. Gather Name
        $name = $this->option('name') ?: $this->ask('Owner Full Name');
        $nameValidator = Validator::make(['name' => $name], [
            'name' => ['required', 'string', 'max:255'],
        ]);
        if ($nameValidator->fails()) {
            foreach ($nameValidator->errors()->all() as $error) {
                $this->components->error($error);
            }
            return self::FAILURE;
        }

        // 2. Gather Email
        $email = $this->option('email') ?: $this->ask('Owner Email Address');
        $emailValidator = Validator::make(['email' => $email], [
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
        ]);
        if ($emailValidator->fails()) {
            foreach ($emailValidator->errors()->all() as $error) {
                $this->components->error($error);
            }
            return self::FAILURE;
        }

        // 3. Gather Password
        $password = $this->option('password');
        if (! $password) {
            $password = $this->secret('Enter Password (minimum 8 characters)');
            $confirmPassword = $this->secret('Confirm Password');

            if ($password !== $confirmPassword) {
                $this->components->error('The entered passwords do not match.');
                return self::FAILURE;
            }
        }

        $passwordValidator = Validator::make(['password' => $password], [
            'password' => ['required', 'string', 'min:8'],
        ]);
        if ($passwordValidator->fails()) {
            foreach ($passwordValidator->errors()->all() as $error) {
                $this->components->error($error);
            }
            return self::FAILURE;
        }

        // 4. Create the User securely
        $user = User::create([
            'role_id' => $ownerRole->id,
            'name' => trim((string) $name),
            'email' => strtolower(trim((string) $email)),
            'password' => Hash::make($password),
        ]);

        $this->components->info('Owner account created successfully!');
        $this->table(
            ['ID', 'Name', 'Email', 'Role', 'Created At'],
            [
                [$user->id, $user->name, $user->email, $ownerRole->display_name, $user->created_at->toDateTimeString()],
            ]
        );
        $this->components->twoColumnDetail('Security Note', 'Password was securely hashed and not logged or displayed.');

        return self::SUCCESS;
    }
}
