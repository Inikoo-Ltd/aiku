<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product;

use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Masters\MasterAsset;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * An order line keeps a quantity and the historic asset snapshot taken when it was added, so
 * changing units afterwards silently re-means every line not yet dispatched: a "1" placed
 * against a single candle becomes one outer of six, at the single candle's price.
 */
class CountOpenOrdersAffectedByUnitsChange
{
    use AsObject;

    public function handle(Product|MasterAsset $model): int
    {
        $assetIds = $model instanceof Product
            ? [$model->asset_id]
            : $model->products()->pluck('asset_id')->all();

        $assetIds = array_filter($assetIds);

        if (!$assetIds) {
            return 0;
        }

        return DB::table('transactions')
            ->join('orders', 'orders.id', '=', 'transactions.order_id')
            ->whereIn('transactions.asset_id', $assetIds)
            ->whereNotIn('transactions.state', [
                TransactionStateEnum::DISPATCHED->value,
                TransactionStateEnum::CANCELLED->value,
            ])
            ->whereNotIn('orders.state', [
                OrderStateEnum::DISPATCHED->value,
                OrderStateEnum::CANCELLED->value,
            ])
            ->distinct()
            ->count('orders.id');
    }
}
