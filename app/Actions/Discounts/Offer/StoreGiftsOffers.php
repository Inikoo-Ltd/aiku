<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 May 2026 13:25:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\Offer;

use App\Actions\Helpers\Translations\Translate;
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
use App\Models\Helpers\Language;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreGiftsOffers extends OrgAction
{
    public function handle(Shop $shop, array $modelData): ?Offer
    {

        $product = Product::find(Arr::pull($modelData, 'product_id'));
        if(!$product){
            abort(404);
        }

        $offerCampaign = OfferCampaign::where('shop_id', $shop->id)->where('type', OfferCampaignTypeEnum::GIFT)->first();
        if (!$offerCampaign) {
            return null;
        }

        data_set(
            $modelData,
            'type',
            OfferTypeEnum::GIFT
        );

        $code = Str::lower($offerCampaign->code.'-'.$shop->code);
        data_set($modelData, 'code', $code, false);

        $english = Language::where('code', 'en')->first();
        data_set(
            $modelData,
            'name',
            Translate::run('Gift', $english, $shop->language).' '.$product->code,
            false
        );

        data_set($modelData, 'trigger_type', 'Customer');//todo: after migration, you can change to Shop , after all aurora type=Shop are terminated
       // data_set($modelData, 'trigger_id', $shop->id);

        $targetType = OfferAllowanceTargetTypeEnum::ORDER;

        data_set(
            $modelData,
            'allowances',
            [
                [
                    'class'       => OfferAllowanceClass::DISCOUNT->value,
                    'target_type' => $targetType,
                    'type'        => OfferAllowanceType::PERCENTAGE_OFF->value,
                    'data'        => [
                        'percentage_off' => $percentageOff,
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
            'name'             => ['sometimes', 'string', 'max:255'],
            'duration'         => ['required', 'string', 'in:interval,permanent'],
            'start_at'         => [
                'required',
                'date',
                Rule::when(
                    request('duration') === 'interval',
                    ['before_or_equal:end_at']
                )
            ],
            'end_at'           => ['nullable', 'required_if:duration,interval', 'date'],
            'product_id'       => ['required', 'integer'],
            'quantity'         => ['required', 'integer', 'min:1'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],


        ];
    }


    public function asController(Shop $shop, ActionRequest $request): Offer
    {
        $this->initialisationFromShop($shop, $request);
        return $this->handle($shop, $this->validatedData);
    }

    public function jsonResponse(Offer $offer): array
    {
        return [
            'slug' => $offer->slug,
        ];
    }
}
