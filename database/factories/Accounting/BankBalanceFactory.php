<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 15 Nov 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace Database\Factories\Accounting;

use App\Models\Accounting\BankConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

class BankBalanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'date'            => now()->toDateString(),
            'amount'          => fake()->randomFloat(2, 0, 50000),
            'reserved_amount' => 0,
            'taken_at'        => now(),
        ];
    }

    public function of(BankConnection $bankConnection): static
    {
        return $this->state([
            'group_id'           => $bankConnection->group_id,
            'organisation_id'    => $bankConnection->organisation_id,
            'bank_connection_id' => $bankConnection->id,
        ]);
    }
}
