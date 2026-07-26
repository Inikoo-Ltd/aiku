<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 26 Jul 2026 13:05:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use App\Actions\Catalogue\Product\BreakProductInWebpagesCache;
use App\Actions\Masters\MasterAsset\Hydrators\MasterAssetHydrateMasterPricesRRPtoChild;
use App\Events\MasterAssetPricesCascadeProgressEvent;
use App\Models\Catalogue\Product;
use App\Models\Masters\MasterAsset;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Cascades a master asset's prices/rrps to child products, breaking each product's
 * webpage cache right after its update and broadcasting progress so the edit UI can
 * show "n/total products updated" live. Run synchronously for small fan-outs, dispatch
 * for large ones (see UpdateMasterAssetPrices).
 */
class CascadeMasterAssetPricesToChildren
{
    use AsAction;

    public string $jobQueue = 'price_change_control';

    public function handle(MasterAsset $masterAsset, string $type = 'both'): void
    {
        $done = 0;

        MasterAssetHydrateMasterPricesRRPtoChild::run(
            $masterAsset,
            skipWebpageCacheBreak: true,
            afterEachProduct: function (Product $product, int $doneSoFar, int $total) use ($masterAsset, $type, &$done) {
                if ($product->webpage) {
                    BreakProductInWebpagesCache::run($product);
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

        MasterAssetPricesCascadeProgressEvent::dispatch($masterAsset, [
            'state' => 'done',
            'type'  => $type,
            'done'  => $done,
            'total' => $done,
        ]);
    }
}
