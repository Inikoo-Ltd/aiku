<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Actions\Catalogue\Product\CountOpenOrdersAffectedByUnitsChange;
use App\Models\Catalogue\Product;
use App\Models\Masters\MasterAsset;

trait WithUnitsChangeConfirmation
{
    /**
     * @return array{title: string, description: string, yesLabel: string}|null
     */
    public function getUnitsChangeConfirmation(Product|MasterAsset $model): ?array
    {
        $openOrders = CountOpenOrdersAffectedByUnitsChange::run($model);

        if (!$openOrders) {
            return null;
        }

        return [
            'title'       => trans_choice(
                '{1} This will change what 1 open order means|[2,*] This will change what :count open orders mean',
                $openOrders
            ),
            'description' => trans_choice(
                '{1} :count order has not been dispatched and was placed at the current pack size. Its lines keep the quantity and the price already agreed, so the warehouse would ship the new pack size at the old price. Check the order before saving.|[2,*] :count orders have not been dispatched and were placed at the current pack size. Their lines keep the quantity and the price already agreed, so the warehouse would ship the new pack size at the old price. Check them before saving.',
                $openOrders
            ),
            'yesLabel'    => __('Yes, change the units'),
        ];
    }
}
