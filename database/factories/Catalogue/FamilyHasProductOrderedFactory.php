<?php

namespace Database\Factories\Catalogue;

use App\Models\Catalogue\FamilyHasProductOrdered;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Catalogue\FamilyHasProductOrdered>
 */
class FamilyHasProductOrderedFactory extends Factory
{
    protected $model = FamilyHasProductOrdered::class;

    public function definition(): array
    {
        return [
            'family_id' => ProductCategory::factory(),
            'product' => [
                'ordered_date' => $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
                'products' => $this->faker->randomElements([
                    ['id' => $this->faker->randomNumber(3), 'name' => $this->faker->word, 'quantity' => $this->faker->numberBetween(1, 10)],
                    ['id' => $this->faker->randomNumber(3), 'name' => $this->faker->word, 'quantity' => $this->faker->numberBetween(1, 10)],
                ], $this->faker->numberBetween(1, 3)),
            ],
        ];
    }
}
