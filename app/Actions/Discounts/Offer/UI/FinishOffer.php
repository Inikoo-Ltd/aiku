<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 07 May 2026 21:34:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\Offer\UI;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOffersData;
use App\Actions\Discounts\Offer\UpdateProductCategoryOffersData;
use App\Actions\Ordering\Order\RecalculateShopTotalsOrdersInBasket;
use App\Actions\OrgAction;
use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceStateEnum;
use App\Models\Discounts\Offer;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class FinishOffer extends OrgAction
{
    use AsAction;

    public function handle(Offer $offer): Offer
    {
        $currentStatus = $offer->status;
        $offer->update(
            [
                'state'  => OfferStateEnum::FINISHED,
                'status' => false,
                'end_at' => now()
            ]
        );
        foreach ($offer->offerAllowances as $offerAllowance) {
            $offerAllowance->update([
                'state'  => OfferAllowanceStateEnum::FINISHED,
                'status' => false,
                'end_at' => now()
            ]);
        }
        ShopHydrateOffersData::run($offer->shop_id);
        if ($currentStatus != $offer->status) {
            if ($offer->trigger_type == 'ProductCategory') {
                UpdateProductCategoryOffersData::run($offer);
            }
            RecalculateShopTotalsOrdersInBasket::dispatch($offer->shop_id)->delay(now()->addSeconds(10));
        }

        return $offer;
    }

    public function asController(Offer $offer, ActionRequest $request): void
    {
        $this->initialisationFromShop($offer->shop, $request);
        $this->handle($offer);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
