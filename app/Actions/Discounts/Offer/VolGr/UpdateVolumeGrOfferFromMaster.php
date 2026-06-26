<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Feb 2026 14:01:37 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\Offer\VolGr;

use App\Actions\Discounts\Offer\UpdateOfferAllowanceSignature;
use App\Actions\Discounts\Offer\UpdateProductCategoryOffersData;
use App\Actions\Web\Webpage\BreakWebpageCache;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Models\Discounts\Offer;
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
            $percentageOff = (float)($masterProductCategory->gr_vol_discount_percentage / 100);

            foreach ($masterProductCategory->productCategories as $productCategory) {
                if (!$productCategory->follow_master_gr) {
                    continue;
                }

                $offer = Offer::where('shop_id', $productCategory->shop_id)->where('type', 'Category Quantity Ordered Order Interval')->where('trigger_id', $productCategory->id)->first();

                if (!$offer) {
                    $offer = StoreVolumeGRDiscount::make()->action(
                        $productCategory,
                        [
                            'trigger_data_item_quantity' => $masterProductCategory->gr_vol_discount_quantity,
                            'percentage_off'             => $percentageOff,
                            'interval'                   => 30
                        ]
                    );
                } else {
                    $triggerData = $offer->trigger_data;
                    data_set($triggerData, 'item_quantity', $masterProductCategory->gr_vol_discount_quantity);

                    $offer->update([
                        'state'        => OfferStateEnum::ACTIVE,
                        'status'       => true,
                        'trigger_data' => $triggerData,
                    ]);

                    foreach ($offer->offerAllowances as $offerAllowance) {
                        $allowanceData = $offerAllowance->data;
                        data_set($allowanceData, 'percentage_off', $percentageOff);

                        $offerAllowance->update([
                            'state'  => $offer->state->value,
                            'status' => $offer->status,
                            'data'   => $allowanceData,
                            'end_at' => null,
                        ]);

                        $this->updatedAllowancesCount++;
                    }

                    UpdateOfferAllowanceSignature::run($offer);
                }

                $productCategory->updateQuietly(['has_gr_vol_discount' => true]);

                if ($offer) {
                    $offer->refresh();
                    UpdateProductCategoryOffersData::run($offer);
                    if ($productCategory->webpage) {
                        BreakWebpageCache::dispatch($productCategory->webpage, true);
                    }
                }

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
