<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 27 Apr 2023 16:51:52 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace Database\Factories\Dispatch;

use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeliveryNoteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reference' => fake()->numberBetween(100, 999),
            'state' => DeliveryNoteStateEnum::HANDLING,
            'email' => fake()->email,
            'date' => fake()->date,
        ];
    }
}
