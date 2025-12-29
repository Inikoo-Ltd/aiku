<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 04 Nov 2025 09:58:38 Malaysia Time, Kuala Lumpur, Malaysia
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
use App\Models\Catalogue\Shop;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferCampaign;
use App\Models\Helpers\Language;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class StoreFirstOrderBonus extends OrgAction
{
    use WithNoStrictRules;
    use WithStoreOffer;
    use WithStoreOfferRules;

    /**
     * @throws \Throwable
     */
    public function handle(Shop $shop, array $modelData): ?Offer
    {
        $percentageOff = Arr::pull($modelData, 'percentage_off');

        if (Arr::get($modelData, 'end_at')) {
            data_set($modelData, 'duration', OfferDurationEnum::INTERVAL);
        } else {
            data_set($modelData, 'duration', OfferDurationEnum::PERMANENT);
        }


        $english = Language::where('code', 'en')->first();

        $offerCampaign = OfferCampaign::where('shop_id', $shop->id)->where('type', OfferCampaignTypeEnum::FIRST_ORDER)->first();
        if (!$offerCampaign) {
            return null;
        }

        $code = Str::lower($offerCampaign->code.'-'.$shop->code);

        data_set($modelData, 'type', 'Amount AND Order Number');
        data_set($modelData, 'code', $code, false);
        data_set(
            $modelData,
            'name',
            Translate::run('First Order Bonus', $english, $shop->language),
            false
        );

        data_set($modelData, 'code', $code, false);

        data_set($modelData, 'trigger_type', 'Customer');

        data_set(
            $modelData,
            'trigger_data',
            [
                'order_number' => 1,
                'min_amount'   => Arr::pull($modelData, 'trigger_data_min_amount'),
            ]
        );

        data_set(
            $modelData,
            'allowances',
            [
                [
                    'class'       => OfferAllowanceClass::DISCOUNT,
                    'type'        => OfferAllowanceType::PERCENTAGE_OFF,
                    'target_type' => OfferAllowanceTargetTypeEnum::ALL_PRODUCTS_IN_ORDER,
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
            'end_at'                  => ['nullable', 'date', 'after:today'],
            'trigger_data_min_amount' => ['required', 'numeric', 'min:0'],
            'percentage_off'          => ['required', 'numeric', 'gt:0', 'lt:1'],
        ];
    }

    public function getCommandSignature(): string
    {
        return 'offer:create_first_order_bonus {shop} {amount} {discount} {end_at?}';
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        $shop = Shop::where('slug', $command->argument('shop'))->firstOrFail();


        $modelData = [
            'end_at'                  => $command->argument('end_at') ? Carbon::parse($command->argument('end_at')) : null,
            'trigger_data_min_amount' => $command->argument('amount'),
            'percentage_off'          => $command->argument('discount'),

        ];

        $this->asAction = true;
        $this->initialisationFromShop($shop, $modelData);


        $offer = $this->handle($shop, $this->validatedData);

        if ($offer) {
            $command->info('FOB Offer created: '.$offer->name.' ('.$offer->code.')');
        } else {
            $command->error('FOB Offer could not be created');
        }

        return 0;
    }

    /**
     * @throws \Throwable
     */
    public function action(Shop $shop, array $modelData): ?Offer
    {
        $this->asAction = true;
        $this->initialisationFromShop($shop, $modelData);

        return $this->handle($this->shop, $modelData);
    }

}
