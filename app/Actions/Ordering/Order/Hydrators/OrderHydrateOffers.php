<?php

/*
 * author Arya Permana - Kirin
 * created on 18-11-2024-14h-13m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Ordering\Order\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Discounts\TransactionHasOfferAllowance;
use App\Models\Ordering\Order;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrderHydrateOffers implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Order $order): string
    {
        return $order->id;
    }

    public function handle(Order $order): void
    {
        $stats = [
            'number_offers' => TransactionHasOfferAllowance::where('order_id', $order->id)->distinct()->count('transaction_has_offer_allowances.offer_id'),
        ];


        $order->stats()->update($stats);
    }


}
