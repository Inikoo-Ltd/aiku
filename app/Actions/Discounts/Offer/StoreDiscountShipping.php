<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 22 Jun 2026 20:59:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\Offer;

use App\Actions\Helpers\Translations\Translate;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithStoreOfferRules;
use App\Actions\Traits\WithStoreOffer;
use App\Enums\Discounts\Offer\OfferDurationEnum;
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

class StoreDiscountShipping extends OrgAction
{
    use WithStoreOffer;
    use WithStoreOfferRules;

    /**
     * @throws \Throwable
     */
    public function handle(Shop $shop, array $modelData): ?Offer
    {
        $minOrderAmount = (int)Arr::pull($modelData, 'min_order_amount');
        $targetType     = Arr::pull($modelData, 'target_type');
        $targetId       = Arr::pull($modelData, 'target_id');

        $offerCampaign = OfferCampaign::where('shop_id', $shop->id)->where('type', OfferCampaignTypeEnum::SHIPPING)->first();
        if (!$offerCampaign) {
            return null;
        }

        $triggerData = ['min_order_amount' => $minOrderAmount];
        $code        = Str::lower($offerCampaign->code.'-'.$shop->code);
        if ($targetType && $targetType != 'shop' && $targetId) {
            $triggerData['target_type'] = $targetType;
            $triggerData['target_id']   = (int)$targetId;
            $code                       .= '-'.$targetType.'-'.$targetId;
        }
        data_set($modelData, 'code', $code, false);

        $english = Language::where('code', 'en')->first();
        data_set(
            $modelData,
            'name',
            Translate::run('Category Discount', $english, $shop->language, 'gpt-5-nano').' '.$shop->code,
            false
        );

        data_set($modelData, 'duration', OfferDurationEnum::INTERVAL);

        data_set($modelData, 'trigger_type', 'Customer');//todo: after migration, you can change to Shop , after all aurora type=Shop are terminated
        //  data_set($modelData, 'trigger_id', $shop->id);

        data_set($modelData, 'trigger_data', $triggerData);

        data_set(
            $modelData,
            'type',
            OfferTypeEnum::DISCOUNTED_SHIPPING
        );


        data_set(
            $modelData,
            'allowances',
            [
                [
                    'class'       => OfferAllowanceClass::SHIPPING->value,
                    'target_type' => OfferAllowanceTargetTypeEnum::ORDER->value,
                    'type'        => OfferAllowanceType::SHIPPING->value,
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
            'min_order_amount' => ['nullable', 'required_if:type,amount', 'numeric', 'min:0'],
            'target_type'      => ['sometimes', 'nullable', 'string', 'in:shop,department,sub_department,family,collection,product'],
            'target_id'        => ['sometimes', 'nullable', 'integer'],
            'start_at'         => [
                'required',
                'date',
                Rule::when(
                    request('duration') === 'interval',
                    ['before_or_equal:end_at']
                )
            ],
            'end_at'           => ['required', 'date'],
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
