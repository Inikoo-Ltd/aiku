<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 10 Jun 2026 12:09:33 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\Offer;

use App\Actions\OrgAction;
use App\Enums\Discounts\Offer\OfferTypeEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceClass;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceTargetTypeEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceType;
use App\Enums\Discounts\OfferCampaign\OfferCampaignTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferCampaign;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreGiftsOffers extends OrgAction
{
    public function handle(Shop $shop, array $modelData)
    {
        $gift = Product::where('shop_id', $shop->id)->where('id', $modelData['product_id'])->first();
        if (!$gift) {
            return null;
        }
        data_forget($modelData, 'product_id');

        $offerCampaign = OfferCampaign::where('shop_id', $shop->id)->where('type', OfferCampaignTypeEnum::GIFT)->first();
        if (!$offerCampaign) {
            return null;
        }

        $code = Str::lower($offerCampaign->code.'-'.$gift->code);
        data_set($modelData, 'code', $code, false);

        data_set($modelData, 'trigger_type', 'Shop');
        data_set($modelData, 'trigger_id', $shop->id);

        data_set($modelData, 'type', OfferTypeEnum::GIFT->value);

        data_set(
            $modelData,
            'trigger_data',
            [
                'min_order_amount' => Arr::pull($modelData, 'min_order_amount')
            ]
        );
        data_set(
            $modelData,
            'allowances',
            [
                [
                    'class'       => OfferAllowanceClass::GIFT->value,
                    'target_type' => OfferAllowanceTargetTypeEnum::ORDER->value,
                    'type'        => OfferAllowanceType::GIFT->value,
                    'data'        => [
                        'product_id' => $gift->id,
                        'quantity'   => Arr::pull($modelData, 'quantity')
                    ]
                ]
            ]
        );

        $offer = StoreOffer::run($offerCampaign, $modelData);
        ActivateOffer::run($offer, 30);

        return $offer;
    }

    public function rules(): array
    {
        return [

            'name'             => ['required', 'string', 'max:255'],
            'product_id'       => ['required', 'integer'],
            'duration'         => ['required', 'string', 'in:interval,permanent'],
            'min_order_amount' => ['required', 'numeric', 'min:0'],
            'quantity'         => ['required', 'integer', 'min:0'],
            'start_at'         => [
                'required',
                'date',
                Rule::when(
                    request('duration') === 'interval',
                    ['before_or_equal:end_at']
                )
            ],
            'end_at'           => ['nullable', 'required_if:duration,interval', 'date'],

        ];
    }

    public function asController(Shop $shop, ActionRequest $request): Offer
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $this->validatedData);
    }
}
