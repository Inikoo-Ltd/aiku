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

    public function getJobUniqueId(int $customerId): int
    {
        return $customerId;
    }

    public function handle(int|null $customerId): void
    {
        if ($customerId === null) {
            return;
        }

        $customer = Customer::find($customerId);

        if (!$customer) {
            return;
        }

        $stats = [
            'number_favourites' => $customer->favourites()->whereNull('unfavourited_at')->count(),
            'number_unfavourited' => $customer->favourites()->whereNotNull('unfavourited_at')->count(),
        ];

        $customer->stats()->update($stats);
    }

}
