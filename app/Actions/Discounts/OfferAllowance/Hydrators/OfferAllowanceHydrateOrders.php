<?php

/*
 * author Arya Permana - Kirin
 * created on 18-11-2024-13h-19m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Discounts\OfferAllowance\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Discounts\OfferAllowance;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OfferAllowanceHydrateOrders implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(OfferAllowance $offerAllowance): string
    {
        return $offerAllowance->id;
    }

    public function handle(OfferAllowance $offerAllowance): void
    {
        $stats = [
            'number_orders' => $offerAllowance->transactions()->distinct()->count('transaction_has_offer_allowances.order_id'),
        ];

        $offerAllowance->stats()->update($stats);
    }


}
