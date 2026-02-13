<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Models\Catalogue\Shop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydrateAverageClv implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(Shop $shop): string
    {
        return $shop->id;
    }

    public function handle(Shop $shop): void
    {
        $clvData = DB::table('customers as c')
            ->join('customer_stats as cs', 'c.id', '=', 'cs.customer_id')
            ->where('c.shop_id', $shop->id)
            ->selectRaw('
                AVG(CASE WHEN cs.total_clv_amount > 0 THEN cs.total_clv_amount ELSE 0 END) as avg_clv,
                AVG(CASE WHEN cs.historic_clv_amount > 0 THEN cs.historic_clv_amount ELSE 0 END) as avg_historic_clv
            ')
            ->first();

        $stats = [
            'average_clv'          => $clvData->avg_clv ?? 0,
            'average_historic_clv' => $clvData->avg_historic_clv ?? 0,
        ];

        $shop->stats()->update($stats);
    }
}
