<?php

/*
 * author Arya Permana - Kirin
 * created on 18-11-2024-13h-19m
 * GitHub: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Discounts\Offer\Hydrators;

use App\Models\Discounts\Offer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class OfferHydrateOrders implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'hydrators-slave';

    public function getJobUniqueId(?int $offerId): string
    {
        if (!$offerId) {
            $offerId = 'empty';
        }

        return $offerId;
    }

    public function handle(?int $offerId): void
    {
        if (!$offerId) {
            return;
        }

        $offer = Offer::find($offerId);
        if (!$offer) {
            return;
        }

        $numberOrders = DB::connection('aiku_no_sticky')->table('transaction_has_offer_allowances')
            ->where('offer_id', $offer->id)
            ->distinct('order_id')
            ->count('order_id');

        $stats = [
            'number_orders' => $numberOrders
        ];

        $offer->stats()->update($stats);
    }


}
