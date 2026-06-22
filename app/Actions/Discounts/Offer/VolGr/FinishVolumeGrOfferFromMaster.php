<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 Jun 2026 11:58:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\Offer\VolGr;

use App\Actions\Discounts\Offer\FinishOffer;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Models\Discounts\Offer;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class FinishVolumeGrOfferFromMaster implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'urgent';

    public function getJobUniqueId(MasterProductCategory $masterProductCategory): string
    {
        return $masterProductCategory->id;
    }

    private int $updatedOffersCount = 0;
    private int $updatedAllowancesCount = 0;

    /**
     * @throws \Throwable
     */
    public function handle(MasterProductCategory $masterProductCategory): void
    {
        $masterProductCategory->refresh();

        $masterShopEnableGR = $masterProductCategory->masterShop->gold_reward_eligible;

        if ($masterProductCategory->type != MasterProductCategoryTypeEnum::FAMILY || !$masterShopEnableGR) {
            return;
        }

        DB::transaction(function () use ($masterProductCategory) {
            foreach ($masterProductCategory->productCategories as $productCategory) {
                if (!$productCategory->follow_master_gr) {
                    continue;
                }

                $offer = Offer::where('shop_id', $productCategory->shop_id)->where('type', 'Category Quantity Ordered Order Interval')->where('trigger_id', $productCategory->id)->first();

                if ($offer) {
                    FinishOffer::run($offer);
                    $productCategory->updateQuietly(['has_gr_vol_discount' => false]);
                }
            }
        });
    }

}
