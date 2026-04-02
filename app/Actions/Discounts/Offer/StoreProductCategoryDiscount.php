<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 17 Dec 2025 12:02:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\Offer;

use App\Actions\Helpers\Translations\Translate;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithStoreOffer;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceClass;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceTargetTypeEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceType;
use App\Enums\Discounts\OfferCampaign\OfferCampaignTypeEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferCampaign;
use App\Models\Helpers\Language;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreProductCategoryDiscount extends OrgAction
{
    use WithNoStrictRules;
    use WithStoreOffer;

    /**
     * @throws \Throwable
     */
    public function handle(array $modelData): ?Offer
    {
        $productCategory = ProductCategory::find(Arr::pull($modelData, 'product_category_id'));

        $percentageOff = Arr::pull($modelData, 'percentage_off');
        $itemQuantity  = (int)Arr::pull($modelData, 'trigger_data_item_quantity');
        $itemAmount    = (float)Arr::pull($modelData, 'trigger_data_item_amount');

        $offerCampaign = OfferCampaign::where('shop_id', $productCategory->shop_id)->where('type', OfferCampaignTypeEnum::CATEGORY_OFFERS)->first();
        if (!$offerCampaign) {
            return null;
        }

        $type = Arr::pull($modelData, 'type');
        if ($type == 'quantity') {
            if ($itemQuantity == 1) {
                data_set($modelData, 'type', 'Category Ordered');
            } else {
                data_set($modelData, 'type', 'Category Quantity Ordered');
            }
        } elseif ($itemAmount == 0) {
            data_set($modelData, 'type', 'Category Ordered');
        } else {
            data_set($modelData, 'type', 'Category Amount Ordered');
        }


        $code = Str::lower($offerCampaign->code.'-'.$productCategory->code);
        data_set($modelData, 'code', $code, false);

        $english = Language::where('code', 'en')->first();
        data_set(
            $modelData,
            'name',
            Translate::run('Category Discount', $english, $productCategory->shop->language).' '.$productCategory->code,
            false
        );

        data_set($modelData, 'trigger_type', 'ProductCategory');
        data_set($modelData, 'trigger_id', $productCategory->id);

        if ($type == 'quantity') {
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

        data_set(
            $modelData,
            'allowances',
            [
                [
                    'class'       => OfferAllowanceClass::DISCOUNT->value,
                    'target_type' => OfferAllowanceTargetTypeEnum::ALL_PRODUCTS_IN_PRODUCT_CATEGORY->value,
                    'target_id'   => $productCategory->id,
                    'type'        => OfferAllowanceType::PERCENTAGE_OFF->value,
                    'data'        => [
                        'percentage_off' => $percentageOff,
                        'category_type'  => $productCategory->type,
                        'category_id'    => $productCategory->id
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
            'product_category_id'        => ['required', 'integer', 'exists:product_categories,id'],
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
    public function action(ProductCategory $family, array $modelData): ?Offer
    {
        $this->asAction = true;
        data_set($modelData, 'product_category_id', $family->id);
        $this->initialisationFromShop($family->shop, $modelData);

        return $this->handle($this->validatedData);
    }

    public function getCommandSignature(): string
    {
        return 'offer:create_category_discount {category} {item_quantity} {discount} {end_at?}';
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        $category = ProductCategory::where('slug', $command->argument('category'))->firstOrFail();


        $modelData      = [
            'start_at'                   => Carbon::now(),
            'end_at'                     => $command->argument('end_at') ? Carbon::parse($command->argument('end_at')) : null,
            'trigger_data_item_quantity' => $command->argument('item_quantity'),
            'percentage_off'             => $command->argument('discount'),
            'type'                       => 'quantity',
            'product_category_id'        => $category->id,

        ];
        $this->asAction = true;
        $this->initialisationFromShop($category->shop, $modelData);

        $offer = $this->handle($this->validatedData);

        if ($offer) {
            $command->info('Offer created: '.$offer->name.' ('.$offer->code.')');
        } else {
            $command->error('Offer could not be created');
        }

        return 0;
    }

}
