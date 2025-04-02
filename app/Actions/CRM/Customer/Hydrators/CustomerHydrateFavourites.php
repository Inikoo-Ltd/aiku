<?php

/*
 * author Arya Permana - Kirin
 * created on 14-10-2024-08h-46m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\CRM\Customer\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\CRM\Customer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class CustomerHydrateFavourites implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Customer $customer): string
    {
        return $customer->id;
    }

    public function handle(Customer $customer): void
    {
        $stats = [
            'number_favourites' => $customer->favourites()->whereNull('unfavourited_at')->count(),
            'number_unfavourited' => $customer->favourites()->whereNotNull('unfavourited_at')->count(),
        ];

        $customer->stats()->update($stats);
    }

}
