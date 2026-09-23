<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = Str::slug(fake()->unique()->words(2, true));
        $price = fake()->numberBetween(500, 5000);

        return [
            'slug' => $slug,
            'name' => ucfirst(str_replace('-', ' ', $slug)),
            'description' => fake()->sentence(),
            'price' => $price,
            'instances' => fake()->numberBetween(1, 10),
            'messages_per_month' => fake()->numberBetween(100, 10000),
            'popular' => false,
            'cashfree_plan_id' => Plan::cashfreePlanId($slug, 1),
            'price_version' => 1,
        ];
    }

    public function free(): static
    {
        return $this->state(fn () => [
            'price' => 0,
            'cashfree_plan_id' => null,
        ]);
    }
}
