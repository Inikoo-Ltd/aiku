<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Mar 2026 17:54:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\Offer\VolGr;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOffersData;
use App\Actions\Discounts\Offer\ActivateOffer;
use App\Actions\Discounts\Offer\StoreOffer;
use App\Actions\OrgAction;
use App\Enums\Discounts\Offer\OfferDurationEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceClass;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceTargetTypeEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceType;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferCampaign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreVolGrGift extends OrgAction
{
    use AsAction;

    public function handle(OfferCampaign $offerCampaign, $modelData): Offer
    {


        $offerData = [];
        data_set($offerData, 'duration', OfferDurationEnum::PERMANENT);
        data_set($offerData, 'start_at', now());
        data_set($offerData, 'type', 'VolGr Gift');
        data_set($offerData, 'code', 'vol-gr-gift-'.$offerCampaign->shop->slug);
        data_set($offerData, 'name', 'GR gift');
        data_set($offerData, 'trigger_type', 'Customer');

        data_set(
            $offerData,
            'trigger_data',
            [
                'min_amount' => Arr::pull($modelData, 'amount'),
            ]
        );

        $products = [];
        foreach ($modelData['products'] as $product) {
            $products[] = [
                'id'      => $product['id'],
                'default' => (bool)Arr::get($product, 'default', false)
            ];
        }


        $allowanceData = [
            'products' => $products,
        ];

        data_set(
            $offerData,
            'allowances',
            [
                [
                    'class'       => OfferAllowanceClass::GIFT,
                    'target_type' => OfferAllowanceTargetTypeEnum::ORDER->value,
                    'type'        => OfferAllowanceType::GIFT->value,
                    'data'        => $allowanceData
                ]
            ]
        );
        $offer = StoreOffer::run($offerCampaign, $offerData);
        $data = $offerCampaign->data;
        data_set($data, 'vol_gr_gift_offer_id', $offer->id);
        $offerCampaign->update(['data' => $data]);
        ActivateOffer::run($offer);

        ShopHydrateOffersData::run($offer->shop_id);

        return $offer;
    }

    public function rules(): array
    {
        return [
            'amount'             => ['numeric', 'required'],
            'products'           => ['required', 'array'],
            'products.*.id'      => ['required', 'integer', Rule::exists('products', 'id')->where('shop_id', $this->shop->id)],
            'products.*.default' => ['sometimes', 'boolean'],
        ];
    }

    public function asController(OfferCampaign $offerCampaign, ActionRequest $request): Offer
    {
        $this->initialisationFromShop($offerCampaign->shop, $request);

        return $this->handle($offerCampaign, $this->validatedData);
    }

    public function htmlResponse(Offer $offer): RedirectResponse
    {
        return Redirect::route('grp.org.shops.show.discounts.campaigns.gift.show', [
            'organisation'  => $this->organisation,
            'shop'          => $this->shop,
            'offerCampaign' => $offer->offerCampaign->slug,
            'offer'         => $offer->slug
        ]);
    }
}
