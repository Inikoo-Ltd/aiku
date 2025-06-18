<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 14 Jun 2025 15:46:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnit\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Models\Goods\TradeUnit;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class TradeUnitsHydrateCustomerExclusiveProducts implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(TradeUnit $tradeUnit): string
    {
        return $tradeUnit->id;
    }

    public function handle(TradeUnit $tradeUnit): void
    {

        $stats = [
            'number_customer_exclusive_products' => $tradeUnit->products()->where('is_main', true)->whereNotNull('exclusive_for_customer_id')->count()
        ];

        $count = DB::table('model_has_trade_units')
            ->leftJoin('products', 'products.id', '=', 'model_has_trade_units.model_id')
            ->where('trade_unit_id', $tradeUnit->id)
            ->where('model_type', 'Product')
            ->where('is_main', true)
            ->whereNotNull('exclusive_for_customer_id')
            ->selectRaw("products.state as state, count(*) as total")
            ->groupBy('products.state')
            ->pluck('total', 'state')->all();
        foreach (ProductStateEnum::cases() as $case) {
            $stats["number_customer_exclusive_products_state_".$case->snake()] = Arr::get($count, $case->value, 0);
        }


        $stats['number_customer_exclusive_current_products'] = Arr::get($stats, 'number_products_state_active', 0) +
            Arr::get($stats, 'number_products_state_discontinuing', 0);


        $tradeUnit->stats()->update($stats);
    }

}
