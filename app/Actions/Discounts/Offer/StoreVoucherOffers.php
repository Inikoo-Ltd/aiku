<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 09 Jun 2026 16:05:27 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\Offer;

use App\Actions\OrgAction;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Discounts\Offer\OfferTypeEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceClass;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceTargetTypeEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceType;
use App\Enums\Discounts\OfferCampaign\OfferCampaignTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferCampaign;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreVoucherOffers extends OrgAction
{
    use AsAction;

    public function handle(Shop $shop, array $modelData)
    {
        $offerCampaign = OfferCampaign::where('shop_id', $shop->id)->where('type', OfferCampaignTypeEnum::VOUCHERS)->first();
        if (!$offerCampaign) {
            abort(404);
        }

        $percentageOff = Arr::pull($modelData, 'percentage_off');


        $code = Str::lower($offerCampaign->code.'-'.Arr::get($modelData, 'voucher'));
        data_set($modelData, 'code', $code, false);

        data_set($modelData, 'voucher', Str::lower(Arr::get($modelData, 'voucher')));

        if (Arr::get($modelData, 'can_customer_reuse', false)) {
            if (Arr::get($modelData, 'offer_amount', 0) == 0) {
                $type = OfferTypeEnum::REUSABLE_VOUCHER_ANY_ORDER;
            } else {
                $type = OfferTypeEnum::REUSABLE_VOUCHER_AMOUNT_ORDERED;
            }
        } elseif (Arr::get($modelData, 'offer_amount', 0) == 0) {
            $type = OfferTypeEnum::VOUCHER_ANY_ORDER;
        } else {
            $type = OfferTypeEnum::VOUCHER_AMOUNT_ORDERED;
        }


        data_set($modelData, 'type', $type);

        data_set(
            $modelData,
            'settings',
            [
                'can_customer_reuse' => Arr::pull($modelData, 'can_customer_reuse', false)
            ]
        );


        data_set(
            $modelData,
            'trigger_data',
            [
                'item_amount' => Arr::pull($modelData, 'offer_amount')
            ]
        );
        data_set($modelData, 'trigger_type', 'Shop');
        data_set($modelData, 'trigger_id', $shop->id);
        data_set($modelData, 'duration', 'interval');

        $targetId = Arr::pull($modelData, 'target_id');

        $targetType = match (Arr::pull($modelData, 'target_type')) {
            'shop' => OfferAllowanceTargetTypeEnum::ALL_PRODUCTS_IN_ORDER->value,
            'department' => OfferAllowanceTargetTypeEnum::ALL_PRODUCTS_IN_DEPARTMENT->value,
            'sub_department' => OfferAllowanceTargetTypeEnum::ALL_PRODUCTS_IN_SUB_DEPARTMENT->value,
            'family' => OfferAllowanceTargetTypeEnum::ALL_PRODUCTS_IN_PRODUCT_CATEGORY->value,
            'collection' => OfferAllowanceTargetTypeEnum::ALL_PRODUCTS_IN_COLLECTION->value,
            default => OfferAllowanceTargetTypeEnum::PRODUCT->value
        };

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
            'voucher'            => [
                'required',
                'string',
                'max:16',
                Rule::unique('offers', 'voucher')
                    ->where('shop_id', $this->shop->id)
                    ->where(fn($query) => $query->whereRaw('LOWER(voucher) = ?', [Str::lower($this->get('voucher'))]))
            ],
            'name'               => ['required', 'string', 'max:255'],
            'offer_amount'       => ['nullable', 'required', 'numeric', 'min:0'],
            'can_customer_reuse' => ['required', 'boolean'],
            'start_at'           => [
                'required',
                'date',
                'before_or_equal:end_at'
            ],
            'end_at'             => ['required', 'date'],
            'percentage_off'     => ['required', 'numeric', 'gt:0', 'lt:100'],
            'target_type'        => ['required', 'string', 'in:shop,department,sub_department,family,collection,product'],
            'target_id'          => ['required', 'integer'],
        ];
    }

    public function asController(Shop $shop, ActionRequest $request): Offer
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $this->validatedData);
    }
}
