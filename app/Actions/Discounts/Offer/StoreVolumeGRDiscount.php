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
use App\Actions\Traits\WithDiscountArgumentValidation;
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

class StoreVolumeGRDiscount extends OrgAction
{
    use WithNoStrictRules;
    use WithStoreOffer;
    use WithDiscountArgumentValidation;
    use WithStoreOfferRules {
        rules as storeOfferBaseRules;
    }

    /**
     * @throws \Throwable
     */
    public function handle(ProductCategory $family, array $modelData): ?Offer
    {
        $percentageOff = Arr::pull($modelData, 'percentage_off');
        $itemQuantity  = (int)Arr::pull($modelData, 'trigger_data_item_quantity');
        $interval      = null;
        if (Arr::has($modelData, 'interval')) {
            $interval = Arr::pull($modelData, 'interval');
        }

        if (Arr::get($modelData, 'end_at')) {
            data_set($modelData, 'duration', OfferDurationEnum::INTERVAL);
        } else {
            data_set($modelData, 'duration', OfferDurationEnum::PERMANENT);
        }


        $offerCampaign = OfferCampaign::where('shop_id', $family->shop_id)->where('type', OfferCampaignTypeEnum::VOLUME_DISCOUNT)->first();
        if (!$offerCampaign) {
            return null;
        }


        if ($itemQuantity == 1) {
            $type = 'Category Ordered';
        } else {
            $type = 'Category Quantity Ordered';
        }
        if ($interval) {
            $type = $type.' Order Interval';
        }
        data_set($modelData, 'type', $type);


        $code = Str::lower($offerCampaign->code.'-'.$family->code);
        data_set($modelData, 'code', $code, false);
        $english = Language::where('code', 'en')->first();

        $label = 'Volume Discount';
        if ($interval) {
            $label .= '/GR';
        }

        data_set(
            $modelData,
            'name',
            Translate::run($label, $english, $family->shop->language).' '.$family->code,
            false
        );


        $triggerData = [
            'item_quantity' => $itemQuantity
        ];
        if ($interval) {
            $triggerData['interval'] = $interval;
        }

        data_set($modelData, 'trigger_type', 'ProductCategory');
        data_set($modelData, 'trigger_id', $family->id);
        data_set(
            $modelData,
            'trigger_data',
            $triggerData
        );

        $allowanceData = [
            'percentage_off' => $percentageOff,
            'category_type'  => $family->type,
            'category_id'    => $family->id,
        ];
        if ($interval) {
            $allowanceData['interval'] = $interval;
        }
        data_set(
            $modelData,
            'allowances',
            [
                [
                    'class'       => OfferAllowanceClass::DISCOUNT->value,
                    'target_type' => OfferAllowanceTargetTypeEnum::ALL_PRODUCTS_IN_PRODUCT_CATEGORY->value,
                    'target_id'   => $family->id,
                    'type'        => OfferAllowanceType::PERCENTAGE_OFF->value,
                    'data'        => $allowanceData
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
            'end_at'                     => ['nullable', 'date', 'after:today'],
            'trigger_data_item_quantity' => ['required', 'integer', 'min:1'],
            'percentage_off'             => ['required', 'numeric', 'gt:0', 'lt:1'],
            'interval'                   => ['nullable', 'integer', 'min:0'],
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
        return 'offer:create_volume_gr_discount {family} {item_quantity} {days} {discount} {end_at?}';
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        $family = ProductCategory::where('slug', $command->argument('family'))->firstOrFail();

        if (!$discount = $this->validateDiscountArgument($command)) {
            return 1;
        }

        $modelData      = [
            'end_at'                     => $command->argument('end_at') ? Carbon::parse($command->argument('end_at')) : null,
            'trigger_data_item_quantity' => $command->argument('item_quantity'),
            'percentage_off'             => $discount,
            'interval'                   => $command->argument('days'),


        ];
        $this->asAction = true;
        $this->initialisationFromShop($family->shop, $modelData);

        $offer = $this->handle($family, $this->validatedData);

        if ($offer) {
            $command->info('Vol/GR Offer created: '.$offer->name.' ('.$offer->code.')');
        } else {
            $command->error('Offer could not be created');
        }

        return 0;
    }

}
