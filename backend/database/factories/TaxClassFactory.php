<?php

namespace Database\Factories;

use App\Models\TaxClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TaxClass>
 */
class TaxClassFactory extends Factory
{
    protected $model = TaxClass::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true) . ' Tax',
            'code' => fake()->unique()->regexify('[A-Z]{3}[0-9]{2}'),
            'rate' => fake()->randomFloat(2, 0, 25),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
            'is_default' => false,
        ];
    }

    /**
     * Indicate that the tax class is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the tax class is the default.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    /**
     * Set a specific rate.
     */
    public function withRate(float $rate): static
    {
        return $this->state(fn (array $attributes) => [
            'rate' => $rate,
        ]);
    }
}
