<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Dec 2025 15:41:37 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Discounts\OfferCampaign\OfferCampaignStateEnum;
use App\Enums\Discounts\OfferCampaign\OfferCampaignTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\OfferCampaign;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydrateOffersData implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(int|null $shopId): string
    {
        return $shopId ?? 'empty';
    }

    public function handle(int|null $shopId): void
    {
        if (!$shopId) {
            return;
        }
        $shop = Shop::find($shopId);
        if (!$shop) {
            return;
        }

        $offersData = $shop->offers_data;

        data_set($offersData, 'gr.active', false);


        $offerCampaign = OfferCampaign::where('shop_id', $shop->id)
            ->where('state', OfferCampaignStateEnum::ACTIVE)
            ->where('type', OfferCampaignTypeEnum::VOLUME_DISCOUNT)->first();
        if ($offerCampaign) {
            data_set($offersData, 'gr.active', true);
            data_set($offersData, 'gr.interval', Arr::get($offerCampaign, 'settings.interval', 30));
        }

        $shop->updateQuietly(['offers_data' => $offersData]);
    }


    public function getCommandSignature(): string
    {
        return 'shop:hydrate_offers_data {shop?}';
    }

    public function asCommand(Command $command): int
    {
        if ($command->argument('shop')) {
            $shop = Shop::findOrFail($command->argument('shop'));
            $this->handle($shop->id);
            $command->info("Hydrated shop offers data for shop $shop->code");

            return 0;
        }

        foreach (Shop::all() as $shop) {
            $this->handle($shop->id);
            $command->info("Hydrated shop offers data for shop $shop->code");
        }

        return 0;
    }


}
