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
use App\Actions\Traits\WithStoreOffer;
use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceClass;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceTargetFilter;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceType;
use App\Enums\Discounts\OfferCampaign\OfferCampaignTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferCampaign;
use App\Models\Helpers\Language;
use App\Rules\IUnique;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreFirstOrderBonus extends OrgAction
{
    use WithNoStrictRules;
    use WithStoreOffer;

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
        data_set($modelData, 'start_at', now(), false);
        data_set(
            $modelData,
            'name',
            Translate::run('First Order Bonus', $english, $shop->language),
            false
        );


        return StoreOffer::run($offerCampaign, $shop, $modelData);
    }

    public function rules(): array
    {
        $rules = [
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
        if (!$this->strict) {
            $rules['start_at']         = ['sometimes', 'nullable', 'date'];
            $rules['finish_at']        = ['sometimes', 'nullable', 'date'];
            $rules['state']            = ['sometimes', Rule::enum(OfferStateEnum::class)];
            $rules['is_discretionary'] = ['sometimes', 'boolean'];
            $rules['is_locked']        = ['sometimes', 'boolean'];
            $rules['source_data']      = ['sometimes', 'array'];

            $rules = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }

    public function getCommandSignature(): string
    {
        return 'offer:create_first_order_bonus {shop} {amount} {discount} ';
    }

    public function asCommand(Command $command): int
    {
        $shop = Shop::where('slug', $command->argument('shop'))->firstOrFail();

        $modelData = [
            'trigger_data' => [
                'order_number' => 1,
                'min_amount'   => $command->argument('amount'),
            ],
            'allowances'   => [
                [
                    'class'          => OfferAllowanceClass::DISCOUNT,
                    'target_filter'    => OfferAllowanceTargetFilter::ALL_PRODUCTS_IN_ORDER,
                    'allowance_type' => OfferAllowanceType::PERCENTAGE_OFF,
                    'target_type'    => 'Order',
                    'data'           => [
                        'percentage_off' => $command->argument('discount'),
                    ]
                ]
            ]
        ];

        $this->handle($shop, $modelData);

        return 0;
    }

}
