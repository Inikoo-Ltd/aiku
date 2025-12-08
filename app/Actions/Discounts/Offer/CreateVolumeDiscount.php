<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 19 Nov 2025 12:13:02 Central Indonesia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\Offer;

use App\Actions\Helpers\Translations\Translate;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\Rules\WithStoreOfferRules;
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
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class CreateVolumeDiscount extends OrgAction
{
    use WithNoStrictRules;
    use WithStoreOffer;
    use WithStoreOfferRules { rules as storeOfferBaseRules; }

    /**
     * @throws \Throwable
     */
    public function handle(ProductCategory $family, array $modelData): ?Offer
    {
        $english = Language::where('code', 'en')->first();

        $offerCampaign = OfferCampaign::where('shop_id', $family->shop_id)->where('type', OfferCampaignTypeEnum::VOLUME_DISCOUNT)->first();
        if (!$offerCampaign) {
            return null;
        }

        $code = Str::lower($offerCampaign->code.'-'.$family->code);

        data_set($modelData, 'type', 'Category Quantity Ordered');
        data_set($modelData, 'code', $code, false);
        data_set(
            $modelData,
            'name',
            Translate::run('Volume Discount', $english, $family->shop->language).' '.$family->code,
            false
        );

        data_set($modelData, 'trigger_type', 'ProductCategory');
        data_set($modelData, 'trigger_id', $family->id);


        $offer = StoreOffer::run($offerCampaign, $modelData);
        ActivatePermanentOffer::run($offer);

        return $offer;
    }

    public function rules(): array
    {
        // Start from the generic Offer store rules and tailor them to this action
        $rules = $this->storeOfferBaseRules();

        // This action always uses ProductCategory as a trigger and requires a duration
        $rules['trigger_type'] = ['required', Rule::in(['ProductCategory'])];
        $rules['duration']     = ['required', Rule::enum(OfferDurationEnum::class)];

        // Validate trigger payload
        $rules['trigger_data'] = ['required', 'array'];
        $rules['trigger_data.item_quantity'] = ['required', 'integer', 'min:1'];

        // Validate allowance structure (only one is expected but allow an array)
        $rules['allowances'] = ['required', 'array', 'min:1'];
        $rules['allowances.*.class'] = ['required', Rule::in([OfferAllowanceClass::DISCOUNT])];
        $rules['allowances.*.target_type'] = ['required', Rule::in([OfferAllowanceTargetTypeEnum::ALL_PRODUCTS_IN_PRODUCT_CATEGORY])];
        $rules['allowances.*.target_id'] = ['required', 'integer'];
        $rules['allowances.*.type'] = ['required', Rule::in([OfferAllowanceType::PERCENTAGE_OFF])];
        $rules['allowances.*.data'] = ['required', 'array'];
        $rules['allowances.*.data.percentage_off'] = ['required', 'numeric', 'gt:0', 'lt:1'];

        return $rules;
    }


    public function getCommandSignature(): string
    {
        return 'offer:create_volume_discount {family} {item_quantity} {discount} ';
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        $family = ProductCategory::where('slug', $command->argument('family'))->firstOrFail();

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

        $modelData = [
            'duration'     => OfferDurationEnum::PERMANENT,
            'trigger_data' => [
                'item_quantity'       => $command->argument('item_quantity')
            ],
            'allowances'   => [
                [
                    'class'       => OfferAllowanceClass::DISCOUNT,
                    'target_type' => OfferAllowanceTargetTypeEnum::ALL_PRODUCTS_IN_PRODUCT_CATEGORY,
                    'target_id'   => $family->id,
                    'type'        => OfferAllowanceType::PERCENTAGE_OFF,
                    'data'        => [
                        'percentage_off' => $discount,
                    ]
                ]
            ]
        ];
        $offer = $this->handle($family, $modelData);

        if ($offer) {
            $command->info('Offer created: '.$offer->name.' ('.$offer->code.')');
        } else {
            $command->error('Offer could not be created');
        }

        return 0;
    }

}
