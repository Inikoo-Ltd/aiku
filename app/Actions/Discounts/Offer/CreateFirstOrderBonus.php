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
use Illuminate\Support\Str;

class CreateFirstOrderBonus extends OrgAction
{
    use WithNoStrictRules;
    use WithStoreOffer;
    use WithStoreOfferRules;

    /**
     * @throws \Throwable
     */
    public function handle(Shop $shop, array $modelData): ?Offer
    {
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

        data_set($modelData, 'trigger_type', 'Customer');

        $offer = StoreOffer::run($offerCampaign, $modelData);
        ActivatePermanentOffer::run($offer);

        return $offer;
    }


    public function getCommandSignature(): string
    {
        return 'offer:create_first_order_bonus {shop} {amount} {discount} ';
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        $shop = Shop::where('slug', $command->argument('shop'))->firstOrFail();

        $modelData = [
            'duration'     => OfferDurationEnum::PERMANENT,
            'trigger_data' => [
                'order_number' => 1,
                'min_amount'   => $command->argument('amount'),
            ],
            'allowances'   => [
                [
                    'class'         => OfferAllowanceClass::DISCOUNT,
                    'type'          => OfferAllowanceType::PERCENTAGE_OFF,
                    'target_type'   => OfferAllowanceTargetTypeEnum::ALL_PRODUCTS_IN_ORDER,
                    'data'          => [
                        'percentage_off' => $command->argument('discount'),
                    ]
                ]
            ]
        ];

        $offer = $this->handle($shop, $modelData);

        if ($offer) {
            $command->info('Offer created: '.$offer->name.' ('.$offer->code.')');
        } else {
            $command->error('Offer could not be created');
        }

        return 0;
    }

}
