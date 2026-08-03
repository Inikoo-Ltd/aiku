<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 18 Jul 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
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

class StoreBuyXGetCheapestFree extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(array $modelData): ?Offer
    {
        $product = null;
        $family  = null;
        if ($productId = Arr::pull($modelData, 'product_id')) {
            $product = Product::find($productId);
            $shop    = $product->shop;
        } else {
            $family = ProductCategory::find(Arr::pull($modelData, 'product_category_id'));
            $shop   = $family->shop;
        }

        $itemQuantity = (int)Arr::pull($modelData, 'trigger_data_item_quantity');
        $freeQuantity = (int)Arr::pull($modelData, 'free_quantity');

        $freeProduct = null;
        if ($freeProductId = Arr::pull($modelData, 'free_product_id')) {
            if ($product && $freeProductId != $product->id) {
                $freeProduct = Product::where('shop_id', $product->shop_id)->find($freeProductId);
            }
        }

        if ($freeProduct) {
            return $this->handleGiftOffer($modelData, $product, $freeProduct, $itemQuantity, $freeQuantity);
        }

        $campaignType  = $product ? OfferCampaignTypeEnum::PRODUCT_OFFERS : OfferCampaignTypeEnum::CATEGORY_OFFERS;
        $offerCampaign = OfferCampaign::where('shop_id', $shop->id)->where('type', $campaignType)->first();
        if (!$offerCampaign) {
            return null;
        }

        $triggerModel = $product ?? $family;

        data_set(
            $modelData,
            'type',
            $product
                ? OfferTypeEnum::PRODUCT_FOR_EVERY_QUANTITY_ORDERED->value
                : OfferTypeEnum::CATEGORY_FOR_EVERY_QUANTITY_ORDERED->value
        );

        $code = Str::lower($offerCampaign->code.'-free-'.$triggerModel->code);
        data_set($modelData, 'code', $code, false);

        $english = Language::where('code', 'en')->first();
        data_set(
            $modelData,
            'name',
            Translate::run('Buy '.$itemQuantity.' get '.$freeQuantity.' free', $english, $shop->language, 'gpt-5-nano').' '.$triggerModel->code,
            false
        );

        data_set($modelData, 'trigger_type', $product ? 'Product' : 'ProductCategory');
        data_set($modelData, 'trigger_id', $triggerModel->id);
        data_set(
            $modelData,
            'trigger_data',
            [
                'item_quantity' => $itemQuantity
            ]
        );

        if ($product) {
            $allowanceTargetType = OfferAllowanceTargetTypeEnum::PRODUCT->value;
            $allowanceData       = [
                'product_id'    => $product->id,
                'item_quantity' => $itemQuantity,
                'free_quantity' => $freeQuantity,
            ];
        } else {
            $allowanceTargetType = OfferAllowanceTargetTypeEnum::CHEAPEST_PRODUCTS_IN_PRODUCT_CATEGORY->value;
            $allowanceData       = [
                'category_type' => $family->type,
                'category_id'   => $family->id,
                'item_quantity' => $itemQuantity,
                'free_quantity' => $freeQuantity,
            ];
        }

        data_set(
            $modelData,
            'allowances',
            [
                [
                    'class'       => OfferAllowanceClass::DISCOUNT->value,
                    'target_type' => $allowanceTargetType,
                    'target_id'   => $triggerModel->id,
                    'type'        => OfferAllowanceType::FREE_ITEMS->value,
                    'data'        => $allowanceData
                ]
            ]
        );

        $offer = StoreOffer::run($offerCampaign, $modelData);
        ActivateOffer::run($offer, 30);

        return $offer;
    }

    /**
     * Buy X of the trigger product, get Y of a different product free.
     * Materialised as a gift-style bonus transaction when the order is submitted.
     *
     * @throws \Throwable
     */
    private function handleGiftOffer(array $modelData, Product $product, Product $freeProduct, int $itemQuantity, int $freeQuantity): ?Offer
    {
        $offerCampaign = OfferCampaign::where('shop_id', $product->shop_id)->where('type', OfferCampaignTypeEnum::GIFT)->first();
        if (!$offerCampaign) {
            return null;
        }

        data_set($modelData, 'type', OfferTypeEnum::GIFT->value);

        $code = Str::lower($offerCampaign->code.'-'.$product->code.'-'.$freeProduct->code);
        data_set($modelData, 'code', $code, false);

        $english = Language::where('code', 'en')->first();
        data_set(
            $modelData,
            'name',
            Translate::run('Buy '.$itemQuantity.' get '.$freeQuantity.' free', $english, $product->shop->language, 'gpt-5-nano').' '.$product->code.' → '.$freeProduct->code,
            false
        );

        data_set($modelData, 'trigger_type', 'Product');
        data_set($modelData, 'trigger_id', $product->id);
        data_set(
            $modelData,
            'trigger_data',
            [
                'item_quantity' => $itemQuantity
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
                        'product_id' => $freeProduct->id,
                        'quantity'   => $freeQuantity,
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
            'duration'                   => ['required', 'string', 'in:interval,permanent'],
            'trigger_data_item_quantity' => ['required', 'integer', 'min:2'],
            'free_quantity'              => ['required', 'integer', 'min:1', 'lt:trigger_data_item_quantity'],
            'start_at'                   => [
                'required',
                'date',
                Rule::when(
                    request('duration') === 'interval',
                    ['before_or_equal:end_at']
                )
            ],
            'end_at'                     => ['nullable', 'required_if:duration,interval', 'date'],
            'product_category_id'        => ['required_without:product_id', 'integer', 'exists:product_categories,id'],
            'product_id'                 => ['required_without:product_category_id', 'integer', Rule::exists('products', 'id')->where('shop_id', $this->shop->id)],
            'free_product_id'            => ['sometimes', 'nullable', 'integer', Rule::exists('products', 'id')->where('shop_id', $this->shop->id)],
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

    /**
     * @throws \Throwable
     */
    public function actionForProduct(Product $product, array $modelData): ?Offer
    {
        $this->asAction = true;
        data_set($modelData, 'product_id', $product->id);
        $this->initialisationFromShop($product->shop, $modelData);

        return $this->handle($this->validatedData);
    }

    public function getCommandSignature(): string
    {
        return 'offer:create_buy_x_get_cheapest_free {family} {item_quantity} {free_quantity} {end_at?}';
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        $family = ProductCategory::where('slug', $command->argument('family'))->firstOrFail();

        $modelData      = [
            'duration'                   => 'interval',
            'start_at'                   => Carbon::now()->format('Y-m-d'),
            'end_at'                     => $command->argument('end_at') ? Carbon::parse($command->argument('end_at'))->format('Y-m-d') : null,
            'trigger_data_item_quantity' => $command->argument('item_quantity'),
            'free_quantity'              => $command->argument('free_quantity'),
            'product_category_id'        => $family->id,
        ];
        $this->asAction = true;
        $this->initialisationFromShop($family->shop, $modelData);

        $offer = $this->handle($this->validatedData);

        if ($offer) {
            $command->info('Offer created: '.$offer->name.' ('.$offer->code.')');
        } else {
            $command->error('Offer could not be created');
        }

        return 0;
    }

}
