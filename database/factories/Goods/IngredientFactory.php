<?php

namespace Database\Factories\Goods;

use App\Models\Goods\Ingredient;
use Illuminate\Database\Eloquent\Factories\Factory;

class IngredientFactory extends Factory
{
    protected $model = Ingredient::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word(),
            'data' => [],
        ];
    }
}
