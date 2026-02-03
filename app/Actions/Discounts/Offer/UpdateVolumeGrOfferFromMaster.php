<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Feb 2026 14:01:37 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\Offer;

use App\Actions\OrgAction;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferAllowance;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateVolumeGrOfferFromMaster extends OrgAction
{
    use AsAction;

    private int $updatedOffersCount = 0;
    private int $updatedAllowancesCount = 0;

    /**
     * @throws \Throwable
     */
    public function handle(MasterProductCategory $masterProductCategory, ?array $volumeDiscount): array
    {
        $this->updatedOffersCount = 0;
        $this->updatedAllowancesCount = 0;

        DB::transaction(function () use ($masterProductCategory, $volumeDiscount) {

            foreach(ProductCategory::where('type', ProductCategoryTypeEnum::FAMILY)
                ->where('master_product_category_id', $masterProductCategory->id)
                ->get() as $family) {

                $offers = Offer::where('type', 'Category Quantity Ordered Order Interval')
                    ->where('trigger_type', 'ProductCategory')
                    ->where('trigger_id', $family->id)
                    ->get();

                foreach ($offers as $offer) {
                    $this->updateOffer($offer, $volumeDiscount);
                    $this->updateOfferAllowances($offer, $volumeDiscount);
                    UpdateProductCategoryOffersData::run($offer);
                }
            }



        });

        return [
            'success' => true,
            'updated_offers' => $this->updatedOffersCount,
            'updated_allowances' => $this->updatedAllowancesCount,
        ];
    }

    private function updateOffer(Offer $offer, ?array $volumeDiscount): void
    {
        $triggerData = $offer->trigger_data ?? [];

        if ($volumeDiscount !== null && isset($volumeDiscount['item_quantity'])) {
            // Update item_quantity in trigger_data
            $triggerData['item_quantity'] = (int) $volumeDiscount['item_quantity'];
        } else {
            // Remove item_quantity from trigger_data if volume_discount is null
            # unset($triggerData['item_quantity']);

            $triggerData['item_quantity'] = 0;
        }

        $offer->update([
            'trigger_data' => $triggerData
        ]);

        $this->updatedOffersCount++;
    }

    private function updateOfferAllowances(Offer $offer, ?array $volumeDiscount): void
    {
        $offerAllowances = OfferAllowance::where('offer_id', $offer->id)->get();

        foreach ($offerAllowances as $allowance) {
            $data = $allowance->data ?? [];

            if ($volumeDiscount !== null && isset($volumeDiscount['percentage_off'])) {
                // Update percentage_off in data
                // Convert from decimal to percentage if needed (0.1 stays as 0.1)
                $data['percentage_off'] = (string) $volumeDiscount['percentage_off'];
            } else {
                // Remove percentage_off from data if volume_discount is null
                # unset($data['percentage_off']);

                $data['percentage_off'] = 0;
            }

            $allowance->update([
                'data' => $data
            ]);

            $this->updatedAllowancesCount++;
        }
    }

    /**
     * @throws \Throwable
     */
    public function action(MasterProductCategory $masterProductCategory, ?array $volumeDiscount): array
    {
        $this->asAction = true;
        $this->initialisationFromGroup($masterProductCategory->group, []);

        return $this->handle($masterProductCategory, $volumeDiscount);
    }
}
