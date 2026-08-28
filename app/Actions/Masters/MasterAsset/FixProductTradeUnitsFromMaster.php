<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Mar 2026 20:39:18 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use App\Actions\Catalogue\Product\BreakProductInWebpagesCache;
use App\Actions\Catalogue\Product\SyncProductTradeUnits;
use App\Actions\Traits\WithVarnishBan;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Events\MasterAssetPricesCascadeProgressEvent;
use App\Models\Masters\MasterAsset;
use App\Models\Web\Webpage;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Pushes the master's trade unit composition onto every child product that follows it,
 * which re-derives warehouse picking, weights and delivery note pick quantities.
 *
 * Each product's webpage cache is broken as the fan-out advances and progress is
 * broadcast on the master's channel, so the UI can show "n/total products updated"
 * instead of an unexplained pause; varnish bans are deduplicated and sent once at
 * the end, mirroring CascadeMasterAssetPricesToChildren.
 */
class FixProductTradeUnitsFromMaster
{
    use AsAction;
    use WithVarnishBan;

    public string $jobQueue = 'price_change';

    public function handle(MasterAsset $masterProduct): void
    {
        $tradeUnitData = [];
        foreach ($masterProduct->tradeUnits as $tradeUnit) {
            $tradeUnitData[] = [
                'id'       => $tradeUnit->id,
                'quantity' => data_get($tradeUnit, 'pivot.quantity'),
            ];
        }

        /*
         * Queried fresh rather than read off $masterProduct->products: callers clear a
         * product's not_follow_master_trade_units immediately before running this, and a
         * relation loaded earlier would still carry the old flag and silently skip it.
         */
        $products = $masterProduct->products()
            ->where('not_follow_master_trade_units', false)
            ->where('products.status', '!=', ProductStatusEnum::DISCONTINUED)
            ->get();
        $total    = $products->count();

        $cacheBreaker = BreakProductInWebpagesCache::make();
        $webpages     = collect();
        $done         = 0;

        foreach ($products as $product) {
            SyncProductTradeUnits::run($product, $tradeUnitData);

            if ($product->webpage) {
                $productWebpages = $cacheBreaker->getWebpages($product);
                $productWebpages->each(fn (Webpage $webpage) => $cacheBreaker->forgetCacheKeys($webpage));
                $webpages = $webpages->union($productWebpages);
            }

            /*
             * Broadcast in steps, not per product: the event is ShouldBroadcastNow, so
             * every dispatch is a blocking call to soketi inside this loop.
             */
            $done++;
            if ($done === $total || $done % max(1, intdiv($total, 10)) === 0) {
                MasterAssetPricesCascadeProgressEvent::dispatch($masterProduct, [
                    'state' => 'updating',
                    'type'  => 'trade_units',
                    'done'  => $done,
                    'total' => $total,
                ]);
            }
        }

        $this->sendVarnishBansHttpPool(
            $webpages->map(fn (Webpage $webpage) => ['x-ban-webpage' => $webpage->id])->values()->all()
        );

        MasterAssetPricesCascadeProgressEvent::dispatch($masterProduct, [
            'state' => 'done',
            'type'  => 'trade_units',
            'done'  => $done,
            'total' => $total,
        ]);
    }

    public string $commandSignature = 'master_product:fix_shop_products_trade_units {masterProduct}';

    public function asCommand(Command $command): void
    {
        $masterProduct = MasterAsset::where('slug', $command->argument('masterProduct'))->firstOrFail();
        $this->handle($masterProduct);
    }

}
