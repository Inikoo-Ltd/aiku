<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 27 Nov 2024 18:19:13 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Discounts\TransactionHasOfferAllowance;
use App\Models\Ordering\Order;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrderHydrateOfferCampaigns implements ShouldBeUnique
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
            'number_offer_campaigns' => TransactionHasOfferAllowance::where('order_id', $order->id)->distinct()->count('transaction_has_offer_allowances.offer_campaign_id'),
        ];


        $order->stats()->update($stats);
    }


}
