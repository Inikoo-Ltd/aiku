<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Dec 2024 19:31:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\Seeders;

use App\Actions\Discounts\Offer\StoreOffer;
use App\Actions\Discounts\OfferCampaign\StoreOfferCampaign;
use App\Actions\Discounts\OfferAllowance\StoreOfferAllowance;
use App\Actions\GrpAction;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Enums\Discounts\OfferCampaign\OfferCampaignTypeEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceStateEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class SeedShopOfferCampaigns extends GrpAction
{
    use AsAction;


    /**
     * @throws \Throwable
     */
    public function handle(Shop $shop): void
    {
        if (!in_array($shop->type, [ShopTypeEnum::B2B, ShopTypeEnum::DROPSHIPPING])) {
            return;
        }


        foreach (OfferCampaignTypeEnum::cases() as $case) {
            $code = $case->codes()[$case->value];

            if ($shop->offerCampaigns()->where('code', $code)->exists()) {
                continue;
            }

            $offerCampaign = StoreOfferCampaign::make()->action(
                $shop,
                [
                    'code' => $code,
                    'name' => $case->labels()[$case->value],
                    'type' => $case
                ]
            );


            if ($offerCampaign->type == OfferCampaignTypeEnum::DISCRETIONARY) {
                $discretionaryOffer = StoreOffer::make()->action(
                    $offerCampaign,
                    null,
                    [
                        'state'            => OfferStateEnum::ACTIVE,
                        'code'             => 'di-'.$shop->slug,
                        'name'             => 'Discretionary Discount',
                        'type'             => 'Discretionary',
                        'start_at'         => $shop->created_at,
                        'is_discretionary' => true
                    ],
                    strict: false
                );

                StoreOfferAllowance::make()->action(
                    $discretionaryOffer,
                    [
                        'code'             => 'di-'.$shop->slug,
                        'state'            => OfferAllowanceStateEnum::ACTIVE,
                        'start_at'         => $shop->created_at,
                        'trigger_scope'    => 'NA',
                        'is_discretionary' => true
                    ],
                    strict: false
                );
            }
        }
    }

    public string $commandSignature = 'shop:seed_offer_campaigns';

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        $command->info("Seeding shop offer campaigns");
        foreach (Shop::all() as $shop) {
            $this->handle($shop);
        }

        return 0;
    }
}
