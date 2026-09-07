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

    /**
     * Customer facing text on a master is not a quiet edit: it is rewritten into every
     * shop that follows the master, each one machine translated into its own language.
     * The count is what makes that concrete before the button is pressed.
     *
     * @return array{title: string, description: string, yesLabel: string}|null
     */
    public function getCascadeAndTranslateConfirmation(MasterAsset $masterAsset, string $what): ?array
    {
        $followers = $this->countMasterFollowers($masterAsset);

        if (!$followers) {
            return null;
        }

        return [
            'title'       => __('This rewrites the :what of :count shop products', ['what' => $what, 'count' => $followers]),
            'description' => __('The new :what is copied to every shop product following this master and machine translated into each shop language, replacing what is there now. Translations are marked unreviewed so they can be checked afterwards.', ['what' => $what]),
            'yesLabel'    => __('Yes, update and translate them'),
        ];
    }

    /** The note the editor reads while typing, before any confirmation dialog appears. */
    public function getCascadeAndTranslateNote(MasterAsset $masterAsset): ?string
    {
        $followers = $this->countMasterFollowers($masterAsset);

        if (!$followers) {
            return null;
        }

        return trans_choice(
            '{1} Saving updates 1 shop product and translates it with AI into that shop language.|[2,*] Saving updates all :count shop products and translates them with AI into each shop language.',
            $followers
        );
    }

    private function countMasterFollowers(MasterAsset $masterAsset): int
    {
        return $masterAsset->products()
            ->join('shops', 'shops.id', '=', 'products.shop_id')
            ->where('shops.settings->catalog->product_follow_master', true)
            ->count();
    }
}
