<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'OWNER',
                'display_name' => 'Owner',
                'description' => 'Property owner with full access to all hotel operations, financial records, and configuration settings.',
                'permissions' => null,
                'is_system' => true,
            ],
            [
                'name' => 'ADMIN',
                'display_name' => 'Administrator',
                'description' => 'Hotel administrator managing day-to-day operations, staff users, and general system settings.',
                'permissions' => null,
                'is_system' => true,
            ],
            [
                'name' => 'RECEPTIONIST',
                'display_name' => 'Receptionist',
                'description' => 'Front desk staff handling reservations, guest check-in/check-out, and billing reception.',
                'permissions' => null,
                'is_system' => true,
            ],
            [
                'name' => 'HOUSEKEEPING',
                'display_name' => 'Housekeeping',
                'description' => 'Housekeeping staff managing room cleaning statuses and maintenance issues.',
                'permissions' => null,
                'is_system' => true,
            ],
            [
                'name' => 'ACCOUNTANT',
                'display_name' => 'Accountant',
                'description' => 'Finance and accounting staff managing expenses, cash transactions, and invoices.',
                'permissions' => null,
                'is_system' => true,
            ],
        ];

        foreach ($roles as $roleData) {
            Role::updateOrCreate(
                ['name' => $roleData['name']],
                $roleData
            );
        }
    }
}

