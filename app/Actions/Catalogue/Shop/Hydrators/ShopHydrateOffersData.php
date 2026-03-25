<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Dec 2025 15:41:37 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Discounts\OfferCampaign\OfferCampaignTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferAllowance;
use App\Models\Discounts\OfferCampaign;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
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
        data_set($offersData, 'gr.interval', null);
        data_set($offersData, 'gr.amnesty', false);
        data_set($offersData, 'gr.amnesty_offer_id', null);
        data_set($offersData, 'gr.amnesty_until', null);
        data_set($offersData, 'gr.gifts', false);
        data_set($offersData, 'gr.gifts_min_amount', 0);
        data_set($offersData, 'gr.gifts_products', []);
        data_set($offersData, 'gr.gifts_offer_id', null);


        $volGrCampaign = OfferCampaign::where('shop_id', $shop->id)
            ->where('status', true)
            ->where('type', OfferCampaignTypeEnum::VOLUME_DISCOUNT)->first();
        if ($volGrCampaign and $shop->type == ShopTypeEnum::B2B) {
            data_set($offersData, 'gr.active', true);
            data_set($offersData, 'gr.interval', Arr::get($volGrCampaign, 'settings.interval', 30));


            $amnestyOfferId = Arr::get($volGrCampaign->data, 'gr_amnesty_offer_id');

            if ($amnestyOfferId) {
                $amnestyOffer = Offer::find($amnestyOfferId);

                if ($amnestyOffer->status) {
                    data_set($offersData, 'gr.amnesty', true);
                    data_set($offersData, 'gr.amnesty_offer_id', $amnestyOffer->id);
                    data_set($offersData, 'gr.amnesty_until', $amnestyOffer->end_at);
                }
            }


            $grGiftOfferId = Arr::get($volGrCampaign->data, 'vol_gr_gift_offer_id');
            if ($grGiftOfferId) {
                $grGiftOffer = Offer::find($grGiftOfferId);


                if ($grGiftOffer->status) {
                    /** @var OfferAllowance $giftAllowance */
                    $giftAllowance  = $grGiftOffer->offerAllowances()->first();
                    $productOptions = [];
                    foreach (Arr::get($giftAllowance->data, 'products', []) as $productData) {
                        $product = Product::find($productData['id']);
                        if ($product) {
                            $productOptions[] = [
                                'id'      => $product->id,
                                'code'    => $product->code,
                                'name'    => $product->name,
                                'default' => Arr::get(
                                    $productData,
                                    'default',
                                    false
                                ),
                            ];
                        }
                    }


                    data_set($offersData, 'gr.gifts', true);
                    data_set($offersData, 'gr.gifts_offer_id', $grGiftOffer->id);
                    data_set($offersData, 'gr.gifts_min_amount', Arr::get($grGiftOffer->trigger_data, 'min_amount', 0));
                    data_set($offersData, 'gr.gifts_products', $productOptions);
                }
            }
        }

        Cache::put("gr_amnesty_offer_id_$shop->id", Arr::get($offersData, "gr.amnesty_offer_id"), now()->addHour());
        $shop->updateQuietly(['offers_data' => $offersData]);
        $shop->refresh();
    }


    public function getCommandSignature(): string
    {
        return 'shop:hydrate_offers_data {shop?}';
    }

    public function asCommand(Command $command): int
    {
        if ($command->argument('shop')) {
            $shop = Shop::where('slug', $command->argument('shop'))->firstOrFail();
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
