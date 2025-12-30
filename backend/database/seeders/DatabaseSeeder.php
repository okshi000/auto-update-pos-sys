<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // Phase 1: Core & Authentication
            PermissionSeeder::class,
            RoleSeeder::class,
            UserSeeder::class,

            // Phase 2: Products & Inventory
            CategorySeeder::class,
            TaxClassSeeder::class,
            WarehouseSeeder::class,
            ProductSeeder::class,

            // Phase 3: POS & Sales
            PaymentMethodSeeder::class,
        ]);
    }
}
