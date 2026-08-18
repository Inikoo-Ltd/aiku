<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 17 Aug 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Transfers;

use App\Models\Catalogue\Asset;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\HistoricAsset;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Goods\Stock;
use App\Models\Goods\StockFamily;
use App\Models\Goods\TradeUnit;
use App\Models\Inventory\OrgStock;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use Illuminate\Support\Facades\Log;

/*
 * Aiku owns the goods/catalogue tier now: trade units, stocks, products, categories,
 * collections and everything master-tier is edited in aiku, for every organisation —
 * including the ones that still follow Aurora for orders and warehouse movements. An
 * Aurora fetch may therefore still CREATE a record aiku has never seen (a part born in
 * Aurora that a purchase order needs), but it may never rewrite an existing one.
 *
 * Every Aurora fetch entry point wraps its work in during(), and WithActionUpdate — the
 * single update() chokepoint all Update* actions share — refuses to touch a protected
 * model while the flag is up. Rows created inside the same fetch stay writable
 * (wasRecentlyCreated), so store flows can finish assembling what they just made.
 */
class AuroraCatalogueGuard
{
    protected static int $depth = 0;

    public const array PROTECTED_MODELS = [
        TradeUnit::class,
        Product::class,
        Asset::class,
        HistoricAsset::class,
        ProductCategory::class,
        Collection::class,
        Stock::class,
        OrgStock::class,
        StockFamily::class,
        MasterAsset::class,
        MasterProductCategory::class,
        MasterShop::class,
    ];

    public static function during(callable $callback): mixed
    {
        static::$depth++;
        try {
            return $callback();
        } finally {
            static::$depth--;
        }
    }

    public static function active(): bool
    {
        return static::$depth > 0;
    }

    public static function blocksUpdate(mixed $model): bool
    {
        if (!static::active() || !is_object($model) || $model->wasRecentlyCreated) {
            return false;
        }

        foreach (static::PROTECTED_MODELS as $class) {
            if ($model instanceof $class) {
                Log::warning('Aurora fetch blocked update of '.class_basename($model).' id '.$model->getKey());

                return true;
            }
        }

        return false;
    }
}
