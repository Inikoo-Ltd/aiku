<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 26 Jul 2026 13:05:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use App\Actions\Catalogue\Product\BreakProductInWebpagesCache;
use App\Actions\Masters\MasterAsset\Hydrators\MasterAssetHydrateMasterPricesRRPtoChild;
use App\Actions\Traits\WithVarnishBan;
use App\Events\MasterAssetPricesCascadeProgressEvent;
use App\Models\Catalogue\Product;
use App\Models\Masters\MasterAsset;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Cascades a master asset's prices/rrps to child products, breaking each product's
 * webpage cache right after its update and broadcasting progress so the edit UI can
 * show "n/total products updated" live. Run synchronously for small fan-outs, dispatch
 * for large ones (see UpdateMasterAssetPrices).
 *
 * App cache keys are forgotten per product as the cascade advances (instant, Redis),
 * while varnish bans are deduplicated across the whole cascade — shared family and
 * department pages would otherwise be banned once per product — and sent as a single
 * concurrent round at the end, so a slow varnish costs one timeout, not one per page.
 */
class CascadeMasterAssetPricesToChildren
{
    use AsAction;
    use WithVarnishBan;

    public string $jobQueue = 'price_change_control';

    public function handle(MasterAsset $masterAsset, string $type = 'both'): void
    {
        $done     = 0;
        $webpages = collect();

        $cacheBreaker = BreakProductInWebpagesCache::make();

        MasterAssetHydrateMasterPricesRRPtoChild::run(
            $masterAsset,
            skipWebpageCacheBreak: true,
            afterEachProduct: function (Product $product, int $doneSoFar, int $total) use ($masterAsset, $type, $cacheBreaker, &$done, &$webpages) {
                if ($product->webpage) {
                    $productWebpages = $cacheBreaker->getWebpages($product);
                    $productWebpages->each(fn (Webpage $webpage) => $cacheBreaker->forgetCacheKeys($webpage));
                    $webpages = $webpages->union($productWebpages);
                }
                $done = $doneSoFar;
                MasterAssetPricesCascadeProgressEvent::dispatch($masterAsset, [
                    'state' => 'updating',
                    'type'  => $type,
                    'done'  => $doneSoFar,
                    'total' => $total,
                ]);
            }
        );

        $this->sendVarnishBansHttpPool(
            $webpages->map(fn (Webpage $webpage) => ['x-ban-webpage' => $webpage->id])->values()->all()
        );

        MasterAssetPricesCascadeProgressEvent::dispatch($masterAsset, [
            'state' => 'done',
            'type'  => $type,
            'done'  => $done,
            'total' => $done,
        ]);
    }
}
