<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Feb 2026 14:01:37 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\Offer\VolGr;

use App\Actions\Catalogue\ProductCategory\UpdateProductCategory;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class UpdateVolumeGrOfferFromMaster
{
    use AsObject;

    private int $updatedOffersCount = 0;
    private int $updatedAllowancesCount = 0;

    /**
     * @throws \Throwable
     */
    public function handle(MasterProductCategory $masterProductCategory): array
    {
        $masterProductCategory->refresh();
        $this->updatedOffersCount     = 0;
        $this->updatedAllowancesCount = 0;
        $masterShopEnableGR           = $masterProductCategory->masterShop->gold_reward_eligible;

        if ($masterProductCategory->type != MasterProductCategoryTypeEnum::FAMILY || !$masterShopEnableGR) {
            return [
                'success'            => false,
                'updated_offers'     => $this->updatedOffersCount,
                'updated_allowances' => $this->updatedAllowancesCount,
                'error_message'      => $masterShopEnableGR ? __('Unable to update GR. Only master family is able to be edited') : __('Unable to update GR, master shop disabled Master Level offer update')
            ];
        }

        DB::transaction(function () use ($masterProductCategory) {

            foreach ($masterProductCategory->productCategories as $productCategory) {
                if (!$productCategory->follow_master_gr) {
                    continue;
                }

                UpdateProductCategory::make()->updateFamilyGrOffer($productCategory, [
                    'item_quantity'     => $masterProductCategory->gr_vol_discount_quantity,
                    'percentage_off'    => $masterProductCategory->gr_vol_discount_percentage,
                ]);

                $this->updatedOffersCount++;
            }
        });

        return [
            'success'            => true,
            'updated_offers'     => $this->updatedOffersCount,
            'updated_allowances' => $this->updatedAllowancesCount,
        ];
    }

}
