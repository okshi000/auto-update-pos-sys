<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\TaxClass;
use App\Models\Warehouse;
use App\Services\SkuService;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $skuService = app(SkuService::class);
        
        // Get references
        $stdTax = TaxClass::where('code', 'STD')->first();
        $zeroTax = TaxClass::where('code', 'ZERO')->first();
        $mainWarehouse = Warehouse::where('code', 'MAIN')->first();

        $products = [
            // Beverages - Soft Drinks
            [
                'name' => 'Coca-Cola 330ml',
                'category_slug' => 'soft-drinks',
                'barcode' => '5449000000996',
                'cost_price' => 0.50,
                'price' => 1.50,
                'min_stock_level' => 20,
                'initial_stock' => 100,
            ],
            [
                'name' => 'Pepsi 330ml',
                'category_slug' => 'soft-drinks',
                'barcode' => '4060800001238',
                'cost_price' => 0.48,
                'price' => 1.50,
                'min_stock_level' => 20,
                'initial_stock' => 80,
            ],
            [
                'name' => 'Sprite 500ml',
                'category_slug' => 'soft-drinks',
                'barcode' => '5449000131836',
                'cost_price' => 0.60,
                'price' => 1.80,
                'min_stock_level' => 15,
                'initial_stock' => 60,
            ],
            // Beverages - Water
            [
                'name' => 'Evian Water 500ml',
                'category_slug' => 'water',
                'barcode' => '3068320053035',
                'cost_price' => 0.40,
                'price' => 1.20,
                'min_stock_level' => 30,
                'initial_stock' => 150,
                'tax_code' => 'ZERO',
            ],
            // Food - Snacks
            [
                'name' => 'Lay\'s Classic Chips 150g',
                'category_slug' => 'snacks',
                'barcode' => '0028400443265',
                'cost_price' => 1.20,
                'price' => 2.99,
                'min_stock_level' => 15,
                'initial_stock' => 50,
            ],
            [
                'name' => 'Doritos Nacho Cheese 180g',
                'category_slug' => 'snacks',
                'barcode' => '0028400443289',
                'cost_price' => 1.50,
                'price' => 3.49,
                'min_stock_level' => 10,
                'initial_stock' => 40,
            ],
            // Food - Dairy
            [
                'name' => 'Whole Milk 1L',
                'category_slug' => 'dairy',
                'barcode' => '5000128000345',
                'cost_price' => 0.80,
                'price' => 1.49,
                'min_stock_level' => 20,
                'initial_stock' => 30,
                'tax_code' => 'ZERO',
            ],
            [
                'name' => 'Cheddar Cheese 200g',
                'category_slug' => 'dairy',
                'barcode' => '5000128000352',
                'cost_price' => 2.00,
                'price' => 3.99,
                'min_stock_level' => 10,
                'initial_stock' => 25,
            ],
            // Personal Care
            [
                'name' => 'Colgate Toothpaste 100ml',
                'category_slug' => 'oral-care',
                'barcode' => '8714789556789',
                'cost_price' => 1.50,
                'price' => 2.99,
                'min_stock_level' => 15,
                'initial_stock' => 40,
            ],
            [
                'name' => 'Head & Shoulders Shampoo 400ml',
                'category_slug' => 'hair-care',
                'barcode' => '8001090076557',
                'cost_price' => 3.00,
                'price' => 5.99,
                'min_stock_level' => 10,
                'initial_stock' => 35,
            ],
            // Household
            [
                'name' => 'Paper Towels 6 Pack',
                'category_slug' => 'paper-products',
                'barcode' => '7622210438576',
                'cost_price' => 2.50,
                'price' => 4.99,
                'min_stock_level' => 15,
                'initial_stock' => 45,
            ],
            [
                'name' => 'Multi-Surface Cleaner 750ml',
                'category_slug' => 'cleaning',
                'barcode' => '5000204871234',
                'cost_price' => 1.80,
                'price' => 3.49,
                'min_stock_level' => 10,
                'initial_stock' => 30,
            ],
            // Electronics
            [
                'name' => 'AA Batteries 4 Pack',
                'category_slug' => 'batteries',
                'barcode' => '5000394014121',
                'cost_price' => 2.00,
                'price' => 4.99,
                'min_stock_level' => 20,
                'initial_stock' => 60,
            ],
            [
                'name' => 'USB-C Charging Cable 1m',
                'category_slug' => 'accessories',
                'barcode' => '4054318975432',
                'cost_price' => 3.00,
                'price' => 9.99,
                'min_stock_level' => 10,
                'initial_stock' => 25,
            ],
        ];

        foreach ($products as $productData) {
            $category = Category::where('slug', $productData['category_slug'])->first();
            $taxClass = isset($productData['tax_code']) 
                ? TaxClass::where('code', $productData['tax_code'])->first() 
                : $stdTax;

            $initialStock = $productData['initial_stock'] ?? 0;
            unset($productData['category_slug'], $productData['initial_stock'], $productData['tax_code']);

            $productData['category_id'] = $category?->id;
            $productData['tax_class_id'] = $taxClass?->id;
            $productData['sku'] = $skuService->generate($category?->slug, $productData['name']);
            $productData['stock_tracked'] = true;
            $productData['is_active'] = true;

            $product = Product::updateOrCreate(
                ['barcode' => $productData['barcode']],
                $productData
            );

            // Set initial stock at main warehouse
            if ($mainWarehouse && $initialStock > 0) {
                StockLevel::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'warehouse_id' => $mainWarehouse->id,
                    ],
                    [
                        'quantity' => $initialStock,
                        'reserved_quantity' => 0,
                    ]
                );
            }
        }
    }
}
