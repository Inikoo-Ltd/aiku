<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Mar 2026 17:54:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\Offer\VolGr;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOffersData;
use App\Actions\Discounts\OfferAllowance\UpdateOfferAllowance;
use App\Actions\OrgAction;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferAllowance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateVolGrGift extends OrgAction
{
    use AsAction;

    public function handle(Offer $offer, $modelData): Offer
    {
        if (Arr::has($modelData, 'amount')) {
            data_set(
                $offerData,
                'trigger_data',
                [
                    'min_amount' => Arr::pull($modelData, 'amount'),
                ]
            );
            $offer->update($offerData);
        }


        if (Arr::has($modelData, 'products')) {
            /** @var OfferAllowance $offerAllowance */
            $offerAllowance = $offer->offerAllowances()->first();

            $allowanceData = $offerAllowance->data;

            $oldProducts    = Arr::get($allowanceData, 'products', []);
            $oldProductsIds = Arr::pluck($oldProducts, 'id');
            $newProductsIds = [];
            if (Arr::has($allowanceData, 'products')) {
                $allowanceData['products'] = Arr::get($modelData, 'products');
                $newProductsIds            = Arr::pluck($allowanceData['products'], 'id');
            }

            UpdateOfferAllowance::run($offerAllowance, [
                'data' => $allowanceData,
            ]);

            $deletedProductsIds = array_diff($oldProductsIds, $newProductsIds);
            foreach ($deletedProductsIds as $deletedProductId) {
                $offer->shop->orders()->where(function ($query) use ($deletedProductId) {
                    $query->whereJsonContains('data->gr->selected_gift', $deletedProductId);
                })->update([
                    'data->gr->selected_gift' => null
                ]);
            }
        }


        ShopHydrateOffersData::run($offer->shop_id);

        return $offer;
    }

    public function rules(): array
    {
        return [
            'amount'             => ['sometimes', 'numeric', 'required'],
            'products'           => ['sometimes', 'array'],
            'products.*.id'      => ['required', 'integer', Rule::exists('products', 'id')->where('shop_id', $this->shop->id)],
            'products.*.default' => ['sometimes', 'boolean'],
        ];
    }

    public function asController(Offer $offer, ActionRequest $request): Offer
    {
        $this->initialisationFromShop($offer->shop, $request);

        return $this->handle($offer, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
