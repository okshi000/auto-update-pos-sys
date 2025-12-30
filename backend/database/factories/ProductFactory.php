<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);
        
        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'sku' => fake()->unique()->regexify('SKU-[A-Z]{3}[0-9]{4}'),
            'barcode' => fake()->optional(0.7)->ean13(),
            'description' => fake()->optional()->paragraph(),
            'category_id' => null,
            'tax_class_id' => null,
            'cost_price' => fake()->randomFloat(2, 1, 100),
            'price' => fake()->randomFloat(2, 5, 200),
            'stock_tracked' => true,
            'min_stock_level' => fake()->numberBetween(5, 20),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the product is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Set the product's category.
     */
    public function forCategory(int $categoryId): static
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => $categoryId,
        ]);
    }

    /**
     * Set the product's tax class.
     */
    public function forTaxClass(int $taxClassId): static
    {
        return $this->state(fn (array $attributes) => [
            'tax_class_id' => $taxClassId,
        ]);
    }

    /**
     * Create a product with no stock tracking.
     */
    public function untracked(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock_tracked' => false,
        ]);
    }
}
