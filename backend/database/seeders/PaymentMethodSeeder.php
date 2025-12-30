<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $methods = [
            [
                'name' => 'Cash',
                'code' => 'cash',
                'type' => PaymentMethod::TYPE_CASH,
                'description' => 'Cash payment',
                'is_active' => true,
                'requires_reference' => false,
                'sort_order' => 1,
            ],
            [
                'name' => 'Credit Card',
                'code' => 'credit_card',
                'type' => PaymentMethod::TYPE_CARD,
                'description' => 'Credit card payment',
                'is_active' => true,
                'requires_reference' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Debit Card',
                'code' => 'debit_card',
                'type' => PaymentMethod::TYPE_CARD,
                'description' => 'Debit card payment',
                'is_active' => true,
                'requires_reference' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Mobile Payment',
                'code' => 'mobile',
                'type' => PaymentMethod::TYPE_DIGITAL,
                'description' => 'Mobile wallet payment',
                'is_active' => false, // Not active by default for Phase 3
                'requires_reference' => true,
                'sort_order' => 4,
            ],
        ];

        foreach ($methods as $method) {
            PaymentMethod::updateOrCreate(
                ['code' => $method['code']],
                $method
            );
        }
    }
}
