<?php

/*
 * author Louis Perez
 * created on 15-05-2026-16h-48m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Masters\MasterProductCategory;

use App\Actions\Discounts\Offer\VolGr\StoreVolumeGRDiscount;
use App\Actions\GrpAction;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Models\Discounts\Offer;
use App\Models\Masters\MasterProductCategory;
use Lorisleiva\Actions\Concerns\AsAction;

class UpsertGoldRewardFromMasterProductCategory extends GrpAction
{
    use AsAction;

    public function getJobUniqueId(MasterProductCategory $masterProductCategory): string
    {
        return $masterProductCategory->id . '_gr_vol_reward';
    }

    public function handle(MasterProductCategory $masterProductCategory): void
    {
        if ($masterProductCategory->type != MasterProductCategoryTypeEnum::FAMILY || !$masterProductCategory->masterShop->gold_reward_eligible) {
            return;
        }

        foreach ($masterProductCategory->productCategories as $productCategory) {
            $offer = Offer::where('shop_id', $productCategory->shop_id)->where('type', 'Category Quantity Ordered Order Interval')->where('trigger_id', $productCategory->id)->first();

            if (!$offer) {

                StoreVolumeGRDiscount::make()->action(
                    $productCategory,
                    [
                            'trigger_data_item_quantity' => $masterProductCategory->gr_vol_discount_quantity,
                            'percentage_off'             => (float) ($masterProductCategory->gr_vol_discount_percentage / 100),
                            'interval'                   => 30
                        ]
                );

            } else {
                // TODO: Raul. To hydrate the quantity & percentage according from master to the child. I don't know how this process is done
                $offer->update([
                    'state'  => OfferStateEnum::ACTIVE,
                    'status' => true,
                ]);

                foreach ($offer->offerAllowances as $offerAllowance) {
                    $offerAllowance->update([
                        'state'  => $offer->state->value,
                        'status' => $offer->status,
                        'end_at' => null
                    ]);
                }
            }
        }
    }
}
