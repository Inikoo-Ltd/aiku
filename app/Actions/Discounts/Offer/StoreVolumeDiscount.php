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
use App\Rules\IUnique;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreVolumeDiscount extends OrgAction
{
    use WithNoStrictRules;
    use WithStoreOffer;

    /**
     * @throws \Throwable
     */
    public function handle(ProductCategory $family, array $modelData): ?Offer
    {
        $english = Language::where('code', 'en')->first();

        $offerCampaign = OfferCampaign::where('shop_id', $family->id)->where('type', OfferCampaignTypeEnum::VOLUME_DISCOUNT)->first();
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
        return [
            'code'         => [
                'required',
                new IUnique(
                    table: 'offers',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],
                    ]
                ),

                'max:64',
                'alpha_dash'
            ],
            'name'         => ['required', 'max:250', 'string'],
            'data'         => ['sometimes', 'required'],
            'settings'     => ['sometimes', 'required'],
            'trigger_data' => ['sometimes', 'required'],
            'start_at'     => ['sometimes', 'date'],
            'end_at'       => ['sometimes', 'nullable', 'date'],
            'type'         => ['required', 'string'],
            'trigger_type' => ['sometimes', Rule::in(['Order'])],
        ];
    }

    public function getCommandSignature(): string
    {
        return 'offer:create_master_volume_discount {family} {item_quantity} {discount} ';
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        $family = ProductCategory::where('slug', $command->argument('family'))->firstOrFail();

        $modelData = [
            'duration'     => OfferDurationEnum::PERMANENT,
            'trigger_data' => [
                'item_quantity'       => $command->argument('item_quantity'),
                'product_category_id' => $family->id,
            ],
            'allowances'   => [
                [
                    'class'       => OfferAllowanceClass::DISCOUNT,
                    'target_type' => OfferAllowanceTargetTypeEnum::ALL_PRODUCTS_IN_PRODUCT_CATEGORY,
                    'type'        => OfferAllowanceType::PERCENTAGE_OFF,
                    'data'        => [
                        'percentage_off' => $command->argument('discount'),
                    ]
                ]
            ]
        ];

        $this->handle($family, $modelData);

        return 0;
    }

}
