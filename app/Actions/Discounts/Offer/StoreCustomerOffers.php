<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Jun 2026 10:27:35 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\Offer;

use App\Actions\OrgAction;
use App\Enums\Discounts\Offer\OfferTypeEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceClass;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceTargetTypeEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceType;
use App\Enums\Discounts\OfferCampaign\OfferCampaignTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferCampaign;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreCustomerOffers extends OrgAction
{
    public function handle(Shop $shop, array $modelData): Offer
    {
        $customer = Customer::where('shop_id', $shop->id)->where('id', Arr::pull($modelData, 'customer_id'))->first();
        if (!$customer) {
            abort(404);
        }

        $offerCampaign = OfferCampaign::where('shop_id', $shop->id)->where('type', OfferCampaignTypeEnum::CUSTOMER_OFFERS)->first();
        if (!$offerCampaign) {
            abort(404);
        }

        $code = Str::lower($offerCampaign->code.'-'.$customer->reference);
        data_set($modelData, 'code', $code, false);

        data_set(
            $modelData,
            'name',
            __('Your Exclusive Deal'),
            false
        );

        data_set($modelData, 'trigger_type', 'Customer');
        data_set($modelData, 'trigger_id', $customer->id);
        data_set($modelData, 'customer_id', $customer->id);


        if (Arr::get($modelData, 'min_order_amount', 0) == 0) {
            $type = OfferTypeEnum::CUSTOMER_ANY_ORDER;
        } else {
            $type = OfferTypeEnum::CUSTOMER_AMOUNT_ORDERED;
        }
        data_set($modelData, 'type', $type);

        data_set(
            $modelData,
            'trigger_data',
            [
                'min_order_amount' => Arr::pull($modelData, 'min_order_amount')
            ]
        );


        $targetId = Arr::pull($modelData, 'target_id');


        $targetType = match (Arr::pull($modelData, 'target_type')) {
            'shop' => OfferAllowanceTargetTypeEnum::ALL_PRODUCTS_IN_ORDER->value,
            'department' => OfferAllowanceTargetTypeEnum::ALL_PRODUCTS_IN_DEPARTMENT->value,
            'sub_department' => OfferAllowanceTargetTypeEnum::ALL_PRODUCTS_IN_SUB_DEPARTMENT->value,
            'family' => OfferAllowanceTargetTypeEnum::ALL_PRODUCTS_IN_PRODUCT_CATEGORY->value,
            'collection' => OfferAllowanceTargetTypeEnum::ALL_PRODUCTS_IN_COLLECTION->value,
            default => OfferAllowanceTargetTypeEnum::PRODUCT->value
        };


        $percentageOff = Arr::pull($modelData, 'percentage_off');
        $percentageOff = $percentageOff / 100;

        data_set(
            $modelData,
            'allowances',
            [
                [
                    'class'       => OfferAllowanceClass::DISCOUNT->value,
                    'target_type' => $targetType,
                    'target_id'   => $targetId,
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
            'customer_id'      => [
                'required',
                'integer',
                Rule::exists('customers', 'id')->where('shop_id', $this->shop->id)
            ],
            'min_order_amount' => ['required', 'numeric', 'min:0'],
            'percentage_off'   => ['required', 'numeric', 'min:0', 'max:100'],
            'name'             => ['sometimes', 'required', 'string', 'max:255'],
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
            'target_type'      => ['required', 'string', 'in:shop,department,sub_department,family,collection,product'],
            'target_id'        => ['required', 'integer'],

        ];
    }

    public function asController(Shop $shop, ActionRequest $request): Offer
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $this->validatedData);
    }
}
