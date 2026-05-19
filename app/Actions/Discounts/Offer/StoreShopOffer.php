<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 11 May 2026 16:44:46 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\Offer;

use App\Actions\Helpers\Translations\Translate;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithStoreOfferRules;
use App\Actions\Traits\WithStoreOffer;
use App\Enums\Discounts\Offer\OfferTypeEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceClass;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceTargetTypeEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceType;
use App\Enums\Discounts\OfferCampaign\OfferCampaignTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferCampaign;
use App\Models\Helpers\Language;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreShopOffer extends OrgAction
{
    use WithStoreOffer;
    use WithStoreOfferRules;

    /**
     * @throws \Throwable
     */
    public function handle(Shop $shop, array $modelData): ?Offer
    {
        $percentageOff = Arr::pull($modelData, 'percentage_off');
        $itemQuantity  = (int)Arr::pull($modelData, 'trigger_data_item_quantity');
        $itemAmount    = (float)Arr::pull($modelData, 'trigger_data_item_amount');

        $offerCampaign = OfferCampaign::where('shop_id', $shop->id)->where('type', OfferCampaignTypeEnum::SHOP_OFFERS)->first();
        if (!$offerCampaign) {
            return null;
        }


        $type = Arr::pull($modelData, 'type');

        if ($type == 'quantity') {
            $type = 'any';
        }
        if ($type == 'amount' && $itemAmount <= 0) {
            $type = 'any';
        }

        data_set(
            $modelData,
            'type',
            $this->getShopOfferType($type)->value
        );

        $code = Str::lower($offerCampaign->code.'-'.$shop->code);
        data_set($modelData, 'code', $code, false);

        $english = Language::where('code', 'en')->first();
        data_set(
            $modelData,
            'name',
            Translate::run('Category Discount', $english, $shop->language).' '.$shop->code,
            false
        );

        data_set($modelData, 'trigger_type', 'Customer');//todo: after migration, you can change to Shop , after all aurora type=Shop are terminated
      //  data_set($modelData, 'trigger_id', $shop->id);

        if ($type == 'quantity' || $type == 'any') {
            data_set(
                $modelData,
                'trigger_data',
                [
                    'item_quantity' => $itemQuantity
                ]
            );
        } else {
            data_set(
                $modelData,
                'trigger_data',
                [
                    'item_amount' => $itemAmount
                ]
            );
        }

        $targetType = OfferAllowanceTargetTypeEnum::ALL_PRODUCTS_IN_ORDER;

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

    private function getShopOfferType(bool $type): OfferTypeEnum
    {
        if ($type == 'amount') {
            return OfferTypeEnum::SHOP_AMOUNT_ORDERED;
        } else {
            return OfferTypeEnum::SHOP_ORDERED;
        }
    }


    public function rules(): array
    {
        return [
            'name'                       => ['sometimes', 'string', 'max:255'],
            'type'                       => ['required', 'string', 'in:quantity,amount'],
            'duration'                   => ['required', 'string', 'in:interval,permanent'],
            'trigger_data_item_quantity' => ['nullable', 'required_if:type,quantity', 'integer', 'min:1'],
            'trigger_data_item_amount'   => ['nullable', 'required_if:type,amount', 'numeric', 'min:0'],
            'start_at'                   => [
                'required',
                'date',
                Rule::when(
                    request('duration') === 'interval',
                    ['before_or_equal:end_at']
                )
            ],
            'end_at'                     => ['nullable', 'required_if:duration,interval', 'date'],
            'percentage_off'             => ['required', 'numeric', 'gt:0', 'lt:100'],
        ];
    }


    /**
     * @throws \Throwable
     */
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
