<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 16 Jun 2026 10:24:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Masters\MasterProductCategories;

use App\Models\Catalogue\ProductCategory;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferAllowance;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncFollowMasterGoldReward
{
    use asAction;

    private bool $dryRun = false;

    public function handle(MasterProductCategory $masterProductCategory, ?Command $command): void
    {
        foreach ($masterProductCategory->productCategories as $productCategory) {
            if ($productCategory->shop->is_aiku) {
                $isSameGrVolDiscount = $this->isSameGrVolDiscount($masterProductCategory, $productCategory);

                if (!$this->dryRun) {

                    $productCategory->updateQuietly(['follow_master_gr' => $isSameGrVolDiscount]);

                }


            }
        }
    }

    public function isSameGrVolDiscount(MasterProductCategory $masterProductCategory, ProductCategory $productCategory): bool
    {
        $isSameGoldRewardDiscount = false;

        if ($masterProductCategory->has_gr_vol_discount === $productCategory->has_gr_vol_discount) {

            $offer = Offer::where('trigger_id', $productCategory->id)
                ->where('offer_type', 'Category Quantity Ordered Order Interval')
                ->where('status', true)
                ->first();
            if ($offer) {
                $allowance = OfferAllowance::where('offer_id', $offer->id)->where('status', true)->first();
                if ($allowance) {
                    $grDiscountQuantity = Arr::get($offer->trigger_data, 'item_quantity');
                    $grDiscountPercentage = Arr::get($allowance->data, 'percentage_off');

                    if ($grDiscountQuantity == $masterProductCategory->gr_vol_discount_quantity && $grDiscountPercentage == $masterProductCategory->gr_vol_discount_percentage) {
                        $isSameGoldRewardDiscount = true;
                    }

                }

            }


        }

        return $isSameGoldRewardDiscount;

    }

}
