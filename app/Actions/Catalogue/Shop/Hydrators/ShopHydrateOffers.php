<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Sept 2024 22:14:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\Offer;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydrateOffers implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public string $commandSignature = 'hydrate:shop-offers {--s|slug= : Shop slug}';

    public function getJobUniqueId(Shop $shop): string
    {
        return $shop->id;
    }

    public function asCommand(Command $command): void
    {
        if ($command->option('slug')) {
            $shop = Shop::where('slug', $command->option('slug'))->first();

            if (!$shop) {
                $command->error("Shop not found.");
                return;
            }

            $this->hydrateShop($command, $shop);
        } else {
            $shops = Shop::all();

            if ($shops->isEmpty()) {
                $command->warn("No shops found.");
                return;
            }

            $command->info("Hydrating offers stats for all shops...");

            $bar = $command->getOutput()->createProgressBar($shops->count());
            $bar->setFormat('debug');
            $bar->start();

            foreach ($shops as $shop) {
                $this->handle($shop);
                $bar->advance();
            }

            $bar->finish();
            $command->info("");
            $command->info("Completed hydrating offers stats for all shops!");
        }
    }

    private function hydrateShop(Command $command, Shop $shop): void
    {
        $command->info("Hydrating offers stats for shop: {$shop->slug}");
        $this->handle($shop);
        $command->info("Completed hydrating offers stats for shop: {$shop->slug}");
    }

    public function handle(Shop $shop): void
    {
        $stats = [
            'number_offers'         => $shop->offers()->count(),
            'number_current_offers' => $shop->offers()->where('status', true)->count(),

        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'offers',
                field: 'state',
                enum: OfferStateEnum::class,
                models: Offer::class,
                where: function ($q) use ($shop) {
                    $q->where('shop_id', $shop->id);
                }
            )
        );

        $shop->discountsStats()->update($stats);
    }
}
