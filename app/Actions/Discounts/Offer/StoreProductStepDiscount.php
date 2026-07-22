<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 18 Jul 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
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

class StoreProductStepDiscount extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(array $modelData): ?Offer
    {
        $product = Product::find(Arr::pull($modelData, 'product_id'));

        $steps = collect(Arr::pull($modelData, 'steps'))
            ->sortBy('min_quantity')
            ->map(fn (array $step) => [
                'min_quantity'   => (int)$step['min_quantity'],
                'percentage_off' => (float)$step['percentage_off'],
            ])
            ->values()
            ->all();

        $offerCampaign = OfferCampaign::where('shop_id', $product->shop_id)->where('type', OfferCampaignTypeEnum::PRODUCT_OFFERS)->first();
        if (!$offerCampaign) {
            return null;
        }

        data_set($modelData, 'type', OfferTypeEnum::PRODUCT_QUANTITY_ORDERED->value);

        $code = Str::lower($offerCampaign->code.'-step-'.$product->code);
        data_set($modelData, 'code', $code, false);

        $english = Language::where('code', 'en')->first();
        data_set(
            $modelData,
            'name',
            Translate::run('Step Discount', $english, $product->shop->language, 'gpt-5-nano').' '.$product->code,
            false
        );

        data_set($modelData, 'trigger_type', 'Product');
        data_set($modelData, 'trigger_id', $product->id);
        data_set(
            $modelData,
            'trigger_data',
            [
                'item_quantity' => Arr::get($steps, '0.min_quantity', 1)
            ]
        );

        data_set(
            $modelData,
            'allowances',
            [
                [
                    'class'       => OfferAllowanceClass::DISCOUNT->value,
                    'target_type' => OfferAllowanceTargetTypeEnum::PRODUCT->value,
                    'target_id'   => $product->id,
                    'type'        => OfferAllowanceType::PERCENTAGE_OFF->value,
                    'data'        => [
                        'product_id' => $product->id,
                        'steps'      => $steps,
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
            'name'                   => ['sometimes', 'string', 'max:255'],
            'duration'               => ['required', 'string', 'in:interval,permanent'],
            'steps'                  => ['required', 'array', 'min:1'],
            'steps.*.min_quantity'   => ['required', 'integer', 'min:1', 'distinct'],
            'steps.*.percentage_off' => ['required', 'numeric', 'gt:0', 'lte:1'],
            'start_at'               => [
                'required',
                'date',
                Rule::when(
                    request('duration') === 'interval',
                    ['before_or_equal:end_at']
                )
            ],
            'end_at'                 => ['nullable', 'required_if:duration,interval', 'date'],
            'product_id'             => ['required', 'integer', Rule::exists('products', 'id')->where('shop_id', $this->shop->id)],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(Shop $shop, ActionRequest $request): Offer
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($this->validatedData);
    }

    public function jsonResponse(Offer $offer): array
    {
        return [
            'slug' => $offer->slug,
        ];
    }

    /**
     * @throws \Throwable
     */
    public function action(Product $product, array $modelData): ?Offer
    {
        $this->asAction = true;
        data_set($modelData, 'product_id', $product->id);
        $this->initialisationFromShop($product->shop, $modelData);

        return $this->handle($this->validatedData);
    }
}
