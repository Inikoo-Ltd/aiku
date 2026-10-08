<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 17 Dec 2025 12:02:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\Offer;

use App\Actions\Catalogue\Product\Json\GetDiscontinuingProductsInFamily;
use App\Actions\Helpers\Translations\Translate;
use App\Actions\OrgAction;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Enums\Discounts\Offer\OfferTypeEnum;
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
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class StoreProductCategoryDiscount extends OrgAction
{
    /**
     * @throws \Throwable
     *
     * @return array{offers: array<int, Offer>, skipped: int}
     */
    public function handleMultiple(array $modelData): array
    {
        $productCategoryIds = array_unique(
            Arr::wrap(Arr::pull($modelData, 'product_category_ids') ?? Arr::pull($modelData, 'product_category_id'))
        );

        if (Arr::pull($modelData, 'combine') && count($productCategoryIds) > 1) {
            if (ProductCategory::whereIn('id', $productCategoryIds)->distinct()->count('type') > 1) {
                throw ValidationException::withMessages([
                    'product_category_ids' => __('Categories counted together must all be departments, all sub-departments or all families'),
                ]);
            }

            $offer = $this->handle(array_merge($modelData, [
                'product_category_id' => Arr::first($productCategoryIds),
                'category_ids'        => array_values($productCategoryIds),
            ]));

            return ['offers' => array_filter([$offer]), 'skipped' => $offer ? 0 : 1];
        }

        $offers  = [];
        $skipped = 0;

        foreach ($productCategoryIds as $productCategoryId) {
            try {
                $offer = $this->handle(array_merge($modelData, ['product_category_id' => $productCategoryId]));
            } catch (ValidationException $e) {
                if (Arr::has($e->errors(), 'code')) {
                    $skipped++;
                    continue;
                }
                throw $e;
            }

            if ($offer) {
                $offers[] = $offer;
            } else {
                $skipped++;
            }
        }

        return ['offers' => $offers, 'skipped' => $skipped];
    }

    /**
     * @throws \Throwable
     */
    public function handle(array $modelData): ?Offer
    {
        $productCategory = ProductCategory::find(Arr::pull($modelData, 'product_category_id'));

        if ($freeQuantity = (int)Arr::pull($modelData, 'free_quantity')) {
            return $this->handleClearanceGift($productCategory, $modelData, $freeQuantity);
        }

        $categoryIds     = Arr::pull($modelData, 'category_ids', []);
        $categoryCodes   = $categoryIds ? ProductCategory::whereIn('id', $categoryIds)->pluck('code')->all() : [$productCategory->code];

        $targetCategory = $productCategory;
        if ($targetCategoryId = Arr::pull($modelData, 'target_product_category_id')) {
            if ($targetCategoryId != $productCategory->id) {
                $targetCategory = ProductCategory::find($targetCategoryId);
            }
        }

        $percentageOff = Arr::pull($modelData, 'percentage_off');
        $itemQuantity  = (int)Arr::pull($modelData, 'trigger_data_item_quantity');
        $itemAmount    = (float)Arr::pull($modelData, 'trigger_data_item_amount');

        $offerCampaign = OfferCampaign::where('shop_id', $productCategory->shop_id)->where('type', OfferCampaignTypeEnum::CATEGORY_OFFERS)->first();
        if (!$offerCampaign) {
            return null;
        }

        $type = Arr::pull($modelData, 'type');

        if ($type == 'quantity' && $itemQuantity <= 1) {
            $type = 'any';
        }
        if ($type == 'amount' && $itemAmount <= 0) {
            $type = 'any';
        }


        data_set(
            $modelData,
            'type',
            $this->getProductCategoryOfferType($productCategory, $type)->value
        );


        $code = Str::lower($offerCampaign->code.'-'.implode('-', $categoryCodes));
        data_set($modelData, 'code', $code, false);

        if (!Arr::has($modelData, 'name')) {
            $english = Language::where('code', 'en')->first();
            data_set(
                $modelData,
                'name',
                Translate::run('Category Discount', $english, $productCategory->shop->language, 'catalogue').' '.implode(', ', $categoryCodes)
            );
        }

        data_set($modelData, 'trigger_type', 'ProductCategory');
        data_set($modelData, 'trigger_id', $productCategory->id);

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

        $allowanceData = [
            'percentage_off' => $percentageOff,
            'category_type'  => $targetCategory->type,
            'category_id'    => $targetCategory->id
        ];

        if ($categoryIds) {
            data_set($modelData, 'trigger_data.category_ids', $categoryIds);
            if ($targetCategory->is($productCategory)) {
                $allowanceData['category_ids'] = $categoryIds;
            }
        }

        $targetType = match ($targetCategory->type) {
            ProductCategoryTypeEnum::DEPARTMENT => OfferAllowanceTargetTypeEnum::ALL_PRODUCTS_IN_DEPARTMENT->value,
            ProductCategoryTypeEnum::SUB_DEPARTMENT => OfferAllowanceTargetTypeEnum::ALL_PRODUCTS_IN_SUB_DEPARTMENT->value,
            default => OfferAllowanceTargetTypeEnum::ALL_PRODUCTS_IN_PRODUCT_CATEGORY->value
        };

        data_set(
            $modelData,
            'allowances',
            [
                [
                    'class'       => OfferAllowanceClass::DISCOUNT->value,
                    'target_type' => $targetType,
                    'target_id'   => $targetCategory->id,
                    'type'        => OfferAllowanceType::PERCENTAGE_OFF->value,
                    'data'        => $allowanceData
                ]
            ]
        );


        $offer = StoreOffer::run($offerCampaign, $modelData);
        ActivateOffer::run($offer, 30);

        return $offer;
    }

    /**
     * Buy X from the family, get Y of its discontinued stock free: a chosen product, or the cheapest left.
     * The offer finishes at submit once there is nothing left to give (HELP-3794).
     *
     * @throws \Throwable
     */
    private function handleClearanceGift(ProductCategory $family, array $modelData, int $freeQuantity): ?Offer
    {
        if ($family->type != ProductCategoryTypeEnum::FAMILY || Arr::get($modelData, 'type') != 'quantity' || count(Arr::get($modelData, 'category_ids', [])) > 1) {
            throw ValidationException::withMessages([
                'free_quantity' => __('Free stock offers need one family and a minimum quantity'),
            ]);
        }

        $freeProduct = null;
        if ($freeProductId = Arr::pull($modelData, 'free_product_id')) {
            $freeProduct = GetDiscontinuingProductsInFamily::run($family)->firstWhere('id', $freeProductId);
            if (!$freeProduct) {
                throw ValidationException::withMessages([
                    'free_product_id' => __('The free product must be a discontinued product of this family with stock'),
                ]);
            }
        } elseif (GetDiscontinuingProductsInFamily::run($family)->isEmpty()) {
            throw ValidationException::withMessages([
                'free_quantity' => __('This family has no discontinued products with stock'),
            ]);
        }

        $offerCampaign = OfferCampaign::where('shop_id', $family->shop_id)->where('type', OfferCampaignTypeEnum::GIFT)->first();
        if (!$offerCampaign) {
            return null;
        }

        $itemQuantity = (int)Arr::pull($modelData, 'trigger_data_item_quantity');
        foreach (['type', 'trigger_data_item_amount', 'percentage_off', 'target_product_category_id', 'category_ids'] as $unusedField) {
            data_forget($modelData, $unusedField);
        }

        data_set($modelData, 'name', 'Buy '.$itemQuantity.' get '.$freeQuantity.' free '.$family->code, false);
        data_set($modelData, 'type', OfferTypeEnum::GIFT->value);
        $code = Str::lower($offerCampaign->code.'-clearance-'.$family->code);
        if (Offer::where('shop_id', $family->shop_id)->where('code', $code)->whereIn('state', [OfferStateEnum::IN_PROCESS, OfferStateEnum::ACTIVE, OfferStateEnum::SUSPENDED])->exists()) {
            throw ValidationException::withMessages([
                'code' => __('This family already has a free stock offer'),
            ]);
        }
        data_set($modelData, 'code', $code, false);
        data_set($modelData, 'trigger_type', 'ProductCategory');
        data_set($modelData, 'trigger_id', $family->id);
        data_set($modelData, 'trigger_data', ['item_quantity' => $itemQuantity]);
        data_set(
            $modelData,
            'allowances',
            [
                [
                    'class'       => OfferAllowanceClass::GIFT->value,
                    'target_type' => OfferAllowanceTargetTypeEnum::ORDER->value,
                    'type'        => OfferAllowanceType::GIFT->value,
                    'data'        => [
                        'product_id'                => $freeProduct?->id,
                        'quantity'                  => $freeQuantity,
                        'discontinuing_in_family_id' => $family->id,
                    ]
                ]
            ]
        );

        $offer = StoreOffer::run($offerCampaign, $modelData);
        ActivateOffer::run($offer, 30);

        return $offer;
    }

    private function getProductCategoryOfferType(ProductCategory $productCategory, string $type): OfferTypeEnum
    {
        if ($type == 'quantity') {
            return match ($productCategory->type) {
                ProductCategoryTypeEnum::DEPARTMENT => OfferTypeEnum::DEPARTMENT_QUANTITY_ORDERED,
                ProductCategoryTypeEnum::SUB_DEPARTMENT => OfferTypeEnum::SUB_DEPARTMENT_QUANTITY_ORDERED,
                default => OfferTypeEnum::CATEGORY_QUANTITY_ORDERED,
            };
        } elseif ($type == 'amount') {
            return match ($productCategory->type) {
                ProductCategoryTypeEnum::DEPARTMENT => OfferTypeEnum::DEPARTMENT_AMOUNT_ORDERED,
                ProductCategoryTypeEnum::SUB_DEPARTMENT => OfferTypeEnum::SUB_DEPARTMENT_AMOUNT_ORDERED,
                default => OfferTypeEnum::CATEGORY_AMOUNT_ORDERED,
            };
        } else {
            return match ($productCategory->type) {
                ProductCategoryTypeEnum::DEPARTMENT => OfferTypeEnum::DEPARTMENT_ORDERED,
                ProductCategoryTypeEnum::SUB_DEPARTMENT => OfferTypeEnum::SUB_DEPARTMENT_ORDERED,
                default => OfferTypeEnum::CATEGORY_ORDERED,
            };
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
            'percentage_off'             => ['nullable', 'required_without:free_quantity', 'numeric', 'gt:0', 'lt:100'],
            'free_quantity'              => ['sometimes', 'nullable', 'integer', 'min:1'],
            'free_product_id'            => ['sometimes', 'nullable', 'integer', Rule::exists('products', 'id')->where('shop_id', $this->shop->id)],
            'product_category_id'        => ['required_without:product_category_ids', 'integer', 'exists:product_categories,id'],
            'product_category_ids'       => ['required_without:product_category_id', 'array', 'min:1'],
            'product_category_ids.*'     => ['integer', 'exists:product_categories,id'],
            'combine'                    => ['sometimes', 'boolean'],
            'target_product_category_id' => ['sometimes', 'nullable', 'integer', Rule::exists('product_categories', 'id')->where('shop_id', $this->shop->id)],
        ];
    }


    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo([
            "discounts.{$this->shop->id}.edit",
            "supervisor-discounts.{$this->shop->id}",
        ]);
    }

    /**
     * @throws \Throwable
     *
     * @return array{offers: array<int, Offer>, skipped: int}
     */
    public function asController(Shop $shop, ActionRequest $request): array
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handleMultiple($this->validatedData);
    }

    /**
     * @param array{offers: array<int, Offer>, skipped: int} $result
     */
    public function jsonResponse(array $result): array
    {
        $offers = $result['offers'];

        /** @var Offer|null $offer */
        $offer = Arr::first($offers);

        if (!$offer) {
            $url = null;
        } elseif (count($offers) == 1) {
            $url = route('grp.org.shops.show.discounts.campaigns.offer.show', [
                'organisation'  => $offer->organisation->slug,
                'shop'          => $offer->shop->slug,
                'offerCampaign' => $offer->offerCampaign->slug,
                'offer'         => $offer->slug,
            ]);
        } else {
            $url = route('grp.org.shops.show.discounts.campaigns.show', [
                'organisation'  => $offer->organisation->slug,
                'shop'          => $offer->shop->slug,
                'offerCampaign' => $offer->offerCampaign->slug,
            ]);
        }

        return [
            'url'     => $url,
            'created' => count($offers),
            'skipped' => $result['skipped'],
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
            'duration'                   => 'interval',
            'start_at'                   => Carbon::now()->format('Y-m-d'),
            'end_at'                     => $command->argument('end_at') ? Carbon::parse($command->argument('end_at'))->format('Y-m-d') : null,
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
