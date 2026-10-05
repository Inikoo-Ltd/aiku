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

    /**
     * The toggle asks which way it is going, so each direction says what the warehouse
     * and the refund will do from then on. On a master it also says how many shop
     * products change with it: the ones that follow the master trade units.
     *
     * @return array{warnOn: array{title: string, text: string, confirmLabel: string}, warnOff: array{title: string, text: string, confirmLabel: string}}
     */
    public function getIndivisibleToggleConfirmations(Product|MasterAsset $model): array
    {
        $cascade = '';
        if ($model instanceof MasterAsset) {
            $followers = $model->products()->whereNot('products.not_follow_master_trade_units', true)->count();
            if ($followers) {
                $cascade = ' '.trans_choice(
                    '{1} The 1 shop product following this master changes with it.|[2,*] The :count shop products following this master change with it.',
                    $followers
                );
            }
        }

        return [
            'warnOn'  => [
                'title'        => __('Sell :code only as a complete set?', ['code' => $model->code]),
                'text'         => __('If one part can not be picked, the warehouse puts the other parts back and the customer is refunded the whole product. Orders not picked yet follow this too.').$cascade,
                'confirmLabel' => __('Yes, only complete sets'),
            ],
            'warnOff' => [
                'title'        => __('Let :code be sent with parts missing?', ['code' => $model->code]),
                'text'         => __('If one part can not be picked, the parts found are sent and the customer is refunded only the value of the missing ones. Orders not picked yet follow this too.').$cascade,
                'confirmLabel' => __('Yes, allow missing parts'),
            ],
        ];
    }

    private function countMasterFollowers(MasterAsset $masterAsset): int
    {
        return $masterAsset->products()
            ->join('shops', 'shops.id', '=', 'products.shop_id')
            ->where('shops.settings->catalog->product_follow_master', true)
            ->count();
    }
}
