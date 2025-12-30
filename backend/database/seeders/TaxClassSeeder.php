<?php

namespace Database\Seeders;

use App\Models\TaxClass;
use Illuminate\Database\Seeder;

class TaxClassSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $taxClasses = [
            [
                'name' => 'Standard Rate',
                'code' => 'STD',
                'rate' => 10.00,
                'description' => 'Standard VAT rate',
                'is_active' => true,
                'is_default' => true,
            ],
            [
                'name' => 'Reduced Rate',
                'code' => 'RED',
                'rate' => 5.00,
                'description' => 'Reduced VAT rate for essential items',
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'name' => 'Zero Rate',
                'code' => 'ZERO',
                'rate' => 0.00,
                'description' => 'Zero-rated items',
                'is_active' => true,
                'is_default' => false,
            ],
            [
                'name' => 'Exempt',
                'code' => 'EXEMPT',
                'rate' => 0.00,
                'description' => 'Tax exempt items',
                'is_active' => true,
                'is_default' => false,
            ],
        ];

        foreach ($taxClasses as $taxClass) {
            TaxClass::updateOrCreate(
                ['code' => $taxClass['code']],
                $taxClass
            );
        }
    }
}
