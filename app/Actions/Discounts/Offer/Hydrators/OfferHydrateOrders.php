<?php

/*
 * author Arya Permana - Kirin
 * created on 18-11-2024-13h-19m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Discounts\Offer\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Discounts\Offer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OfferHydrateOrders implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Offer $offer): string
    {
        return $offer->id;
    }

    public function handle(Offer $offer): void
    {
        $stats = [
            'number_orders' => $offer->transactions()->distinct()->count('transaction_has_offer_allowances.order_id'),
        ];

        $offer->stats()->update($stats);
    }


}
