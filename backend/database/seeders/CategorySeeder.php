<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Beverages',
                'slug' => 'beverages',
                'description' => 'Drinks and refreshments',
                'is_active' => true,
                'sort_order' => 1,
                'children' => [
                    ['name' => 'Soft Drinks', 'slug' => 'soft-drinks', 'description' => 'Carbonated and non-carbonated soft drinks'],
                    ['name' => 'Juices', 'slug' => 'juices', 'description' => 'Fruit and vegetable juices'],
                    ['name' => 'Water', 'slug' => 'water', 'description' => 'Bottled water'],
                    ['name' => 'Energy Drinks', 'slug' => 'energy-drinks', 'description' => 'Energy and sports drinks'],
                ],
            ],
            [
                'name' => 'Food',
                'slug' => 'food',
                'description' => 'Food items and groceries',
                'is_active' => true,
                'sort_order' => 2,
                'children' => [
                    ['name' => 'Snacks', 'slug' => 'snacks', 'description' => 'Chips, crackers, and snack items'],
                    ['name' => 'Dairy', 'slug' => 'dairy', 'description' => 'Milk, cheese, and dairy products'],
                    ['name' => 'Bakery', 'slug' => 'bakery', 'description' => 'Bread and baked goods'],
                    ['name' => 'Frozen Foods', 'slug' => 'frozen-foods', 'description' => 'Frozen meals and ice cream'],
                ],
            ],
            [
                'name' => 'Personal Care',
                'slug' => 'personal-care',
                'description' => 'Personal hygiene and care products',
                'is_active' => true,
                'sort_order' => 3,
                'children' => [
                    ['name' => 'Skincare', 'slug' => 'skincare', 'description' => 'Skin care products'],
                    ['name' => 'Hair Care', 'slug' => 'hair-care', 'description' => 'Shampoos and hair products'],
                    ['name' => 'Oral Care', 'slug' => 'oral-care', 'description' => 'Toothpaste and oral hygiene'],
                ],
            ],
            [
                'name' => 'Household',
                'slug' => 'household',
                'description' => 'Household items and supplies',
                'is_active' => true,
                'sort_order' => 4,
                'children' => [
                    ['name' => 'Cleaning', 'slug' => 'cleaning', 'description' => 'Cleaning supplies'],
                    ['name' => 'Paper Products', 'slug' => 'paper-products', 'description' => 'Tissues, paper towels, etc.'],
                ],
            ],
            [
                'name' => 'Electronics',
                'slug' => 'electronics',
                'description' => 'Electronic devices and accessories',
                'is_active' => true,
                'sort_order' => 5,
                'children' => [
                    ['name' => 'Accessories', 'slug' => 'accessories', 'description' => 'Phone cases, chargers, etc.'],
                    ['name' => 'Batteries', 'slug' => 'batteries', 'description' => 'All types of batteries'],
                ],
            ],
        ];

        foreach ($categories as $categoryData) {
            $children = $categoryData['children'] ?? [];
            unset($categoryData['children']);

            $parent = Category::updateOrCreate(
                ['slug' => $categoryData['slug']],
                $categoryData
            );

            foreach ($children as $index => $childData) {
                $childData['parent_id'] = $parent->id;
                $childData['is_active'] = true;
                $childData['sort_order'] = $index + 1;

                Category::updateOrCreate(
                    ['slug' => $childData['slug']],
                    $childData
                );
            }
        }
    }
}
