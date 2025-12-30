<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Admin user
        $admin = User::updateOrCreate(
            ['email' => 'admin@pos.local'],
            [
                'name' => 'System Administrator',
                'phone' => null,
                'password' => Hash::make('password'),
                'locale' => 'en',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole) {
            $admin->roles()->syncWithoutDetaching([$adminRole->id]);
        }

        // Create Cashier user
        $cashier = User::updateOrCreate(
            ['email' => 'cashier@pos.local'],
            [
                'name' => 'Default Cashier',
                'phone' => null,
                'password' => Hash::make('password'),
                'locale' => 'en',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $cashierRole = Role::where('name', 'cashier')->first();
        if ($cashierRole) {
            $cashier->roles()->syncWithoutDetaching([$cashierRole->id]);
        }

        // Create Manager user
        $manager = User::updateOrCreate(
            ['email' => 'manager@pos.local'],
            [
                'name' => 'Store Manager',
                'phone' => null,
                'password' => Hash::make('password'),
                'locale' => 'en',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $managerRole = Role::where('name', 'manager')->first();
        if ($managerRole) {
            $manager->roles()->syncWithoutDetaching([$managerRole->id]);
        }
    }
}
