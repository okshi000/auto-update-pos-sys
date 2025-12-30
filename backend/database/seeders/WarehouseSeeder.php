<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $warehouses = [
            [
                'name' => 'Main Store',
                'code' => 'MAIN',
                'location' => 'Main Floor',
                'address' => '123 Main Street',
                'phone' => '555-0100',
                'is_active' => true,
                'is_default' => true,
            ],
            [
                'name' => 'Back Storage',
                'code' => 'BACK',
                'location' => 'Back Room',
                'address' => '123 Main Street',
                'phone' => '555-0101',
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'name' => 'Warehouse',
                'code' => 'WH01',
                'location' => 'Off-site',
                'address' => '456 Industrial Blvd',
                'phone' => '555-0102',
                'is_active' => true,
                'is_default' => false,
            ],
        ];

        foreach ($warehouses as $warehouse) {
            Warehouse::updateOrCreate(
                ['code' => $warehouse['code']],
                $warehouse
            );
        }
    }
}
