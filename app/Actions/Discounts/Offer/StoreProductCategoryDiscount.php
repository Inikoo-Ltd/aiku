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
use App\Enums\Discounts\Offer\OfferDurationEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceClass;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceTargetTypeEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceType;
use App\Enums\Discounts\OfferCampaign\OfferCampaignTypeEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferCampaign;
use App\Models\Helpers\Language;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class StoreProductCategoryDiscount extends OrgAction
{
    use WithNoStrictRules;
    use WithStoreOffer;

    /**
     * @throws \Throwable
     */
    public function handle(ProductCategory $productCategory, array $modelData): ?Offer
    {
        $percentageOff = Arr::pull($modelData, 'percentage_off');

        $english = Language::where('code', 'en')->first();

        if (Arr::get($modelData, 'end_at')) {
            data_set($modelData, 'duration', OfferDurationEnum::INTERVAL);
        } else {
            data_set($modelData, 'duration', OfferDurationEnum::PERMANENT);
        }


        $offerCampaign = OfferCampaign::where('shop_id', $productCategory->shop_id)->where('type', OfferCampaignTypeEnum::CATEGORY_OFFERS)->first();
        if (!$offerCampaign) {
            return null;
        }

        $code = Str::lower($offerCampaign->code.'-'.$productCategory->code);

        data_set($modelData, 'type', 'Category Quantity Ordered');
        data_set($modelData, 'code', $code, false);
        data_set(
            $modelData,
            'name',
            Translate::run('Category Discount', $english, $productCategory->shop->language).' '.$productCategory->code,
            false
        );

        data_set($modelData, 'trigger_type', 'ProductCategory');
        data_set($modelData, 'trigger_id', $productCategory->id);

        data_set(
            $modelData,
            'trigger_data',
            [
                'item_quantity' => Arr::pull($modelData, 'trigger_data_item_quantity')
            ]
        );

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
                    ]
                ]
            ]
        );


        $offer = StoreOffer::run($offerCampaign, $modelData);

        ActivateOffer::run($offer);

        return $offer;
    }

    public function rules(): array
    {

        return [
            'end_at' => ['nullable', 'date'],
            'trigger_data_item_quantity' => ['required','integer','min:1'],
            'percentage_off' => ['required','numeric','gt:0','lt:1']
        ];


    }

    /**
     * @throws \Throwable
     */
    public function action(ProductCategory $family, array $modelData): ?Offer
    {
        $this->asAction = true;
        $this->initialisationFromShop($family->shop, $modelData);

        return $this->handle($family, $this->validatedData);
    }

    public function getCommandSignature(): string
    {
        return 'offer:create_category_discount {category} {item_quantity} {discount} {end_at}';
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        $category = ProductCategory::where('slug', $command->argument('category'))->firstOrFail();

        // Validate discount argument: must be numeric strictly between 0 and 1 (exclusive)
        $discountArg = $command->argument('discount');
        if (!is_numeric($discountArg)) {
            $command->error('Invalid discount: must be numeric between 0 and 1 (e.g., 0.20 for 20%).');

            return 1;
        }
        $discount = (float)$discountArg;
        if (!($discount > 0 && $discount < 1)) {
            $command->error('Invalid discount: must be strictly between 0 and 1 (e.g., 0.20 for 20%).');

            return 1;
        }

        $modelData      = [
            'end_at'                     => Carbon::parse($command->argument('end_at')),
            'trigger_data_item_quantity' => $command->argument('item_quantity'),
            'percentage_off'             => $discount,

        ];
        $this->asAction = true;
        $this->initialisationFromShop($category->shop, $modelData);

        $offer = $this->handle($category, $this->validatedData);

        if ($offer) {
            $command->info('Offer created: '.$offer->name.' ('.$offer->code.')');
        } else {
            $command->error('Offer could not be created');
        }

        return 0;
    }

}
