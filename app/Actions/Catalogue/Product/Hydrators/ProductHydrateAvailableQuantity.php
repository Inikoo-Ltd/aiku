<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 24 May 2025 14:40:04 Central Indonesia Time, Plane Bali-KL
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\Hydrators;

use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\Dispatching\CrossDispatch\ResolveCrossDispatch;
use App\Actions\Ordering\Transaction\SyncBasketLinesWithProductStock;
use App\Actions\Traits\AsUniqueUntilProcessingAction;
use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Events\BroadcastProductAvailability;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ProductHydrateAvailableQuantity implements ShouldBeUniqueUntilProcessing
{
    use AsUniqueUntilProcessingAction;
    use WithEnumStats;

    public string $jobQueue = 'stock-control';

    /** @var array<int, array<int, array{0: Organisation|null, 1: int}>> */
    private array $otherWarehousesByShop = [];

    private ?ResolveCrossDispatch $resolver = null;

    private bool $preloadOtherWarehouses = false;

    /** Storefronts read a stock of 250 or more as "Unlimited quantity available". */
    public const int ON_DEMAND_QUANTITY = 250;

    /**
     * What is really on the shelf for a product, correlated on products.id. On-demand products
     * store ON_DEMAND_QUANTITY as their available quantity, so staff views read this instead.
     */
    public static function shelfQuantityQuery(): Builder
    {
        return DB::table('product_has_org_stocks')
            ->join('org_stocks', 'org_stocks.id', '=', 'product_has_org_stocks.org_stock_id')
            ->selectRaw('greatest(min('.self::wholeProductsSql('org_stocks.quantity_available').'), 0)')
            ->whereColumn('product_has_org_stocks.product_id', 'products.id')
            ->where('product_has_org_stocks.quantity', '>', 0);
    }

    /**
     * A product that is 1/n of an SKO stores its ratio rounded to 8 decimals (1/6 as 0.16666667),
     * so dividing by it falls just short of whole numbers: 13 SKOs gave 77 products, not 78.
     * When the ratio is 1/n rounded, count with n itself; any other ratio divides as stored, in
     * whole thousandths and hundred-millionths (the column scales) so 378.4 / 0.4 is 946, not 945.
     */
    public static function wholeProducts(float|string $skoQuantity, float|string $productToOrgStockRatio): int
    {
        $productsPerSko = round(1 / $productToOrgStockRatio);
        if ($productsPerSko > 0 && abs(round(1 / $productsPerSko, 8) - $productToOrgStockRatio) < 1e-12) {
            return intdiv((int) round($skoQuantity * 1000) * (int) $productsPerSko, 1000);
        }

        return intdiv((int) round($skoQuantity * 1000) * 100000, (int) round($productToOrgStockRatio * 100000000));
    }

    /** The SQL twin of wholeProducts(); $skoQuantity is a column or a placeholder, used twice. */
    public static function wholeProductsSql(string $skoQuantity): string
    {
        $ratio = 'product_has_org_stocks.quantity';

        return "(case when round(1 / $ratio) > 0 and round(1 / round(1 / $ratio), 8) = $ratio"
            ." then floor($skoQuantity * round(1 / $ratio)) else floor($skoQuantity / $ratio) end)";
    }

    public function getJobUniqueId(Product $product): string
    {
        return $product->id;
    }

    public function handle(Product $product): Product
    {
        $oldQuantity = $product->available_quantity;
        $oldStatus   = $product->status;
        $oldOnDemand = $product->is_on_demand;

        $product = $this->hydrate($product);

        $quantityChanged = $product->available_quantity != $oldQuantity || is_null($product->available_quantity) !== is_null($oldQuantity);
        if ($quantityChanged || $product->status !== $oldStatus || $product->is_on_demand !== $oldOnDemand) {
            rescue(fn () => BroadcastProductAvailability::dispatch($product), report: false);
        }

        return $product;
    }

    private function hydrate(Product $product): Product
    {
        if ($product->state == ProductStateEnum::DISCONTINUED) {
            $wasAvailable = $product->available_quantity > 0;
            $product      = UpdateProduct::run($product, [
                'available_quantity'           => null,
                'available_quantity_elsewhere' => 0,
                'status'                       => ProductStatusEnum::DISCONTINUED,
            ]);
            if ($wasAvailable) {
                SyncBasketLinesWithProductStock::dispatch($product);
            }

            return $product;
        }


        $currentQuantity          = $product->available_quantity;
        $currentHomeQuantity      = ($currentQuantity ?? 0) - $product->available_quantity_elsewhere;
        $neverStockedOnlyElsewhere = $product->available_quantity_elsewhere > 0 && $currentHomeQuantity <= 0 && !$product->first_in_stock_at && !$product->first_available_at;

        $numberOrgStocksChecked                 = 0;
        $numberOrgStocksHasNeverBeenInWarehouse = 0;
        $quantitiesFromStockedOrgStocks         = [];

        $isNotOnDemand = false;
        foreach ($product->orgStocks as $orgStock) {
            if (!$orgStock->is_on_demand) {
                $isNotOnDemand = true;
            }

            if (!$orgStock->has_been_in_warehouse) {
                $numberOrgStocksHasNeverBeenInWarehouse++;
            }

            $productToOrgStockRatio = $orgStock->pivot->quantity;
            if (!$productToOrgStockRatio || $productToOrgStockRatio == 0) {
                continue;
            }

            $numberOrgStocksChecked++;

            if (!$orgStock->is_on_demand) {
                $quantitiesFromStockedOrgStocks[] = self::wholeProducts($orgStock->quantity_available, $productToOrgStockRatio);
            }
        }

        $onDemand = $numberOrgStocksChecked > 0 && !$isNotOnDemand;

        if ($quantitiesFromStockedOrgStocks) {
            $availableQuantity = max(0, min($quantitiesFromStockedOrgStocks));
        } elseif ($onDemand) {
            $availableQuantity = self::ON_DEMAND_QUANTITY;
        } else {
            $availableQuantity = 0;
        }


        $homeQuantity       = $availableQuantity;
        $quantityElsewhere  = $onDemand ? 0 : rescue(fn () => $this->quantityElsewhere($product, $homeQuantity), 0);
        $availableQuantity += $quantityElsewhere;

        $dataToUpdate = [
            'available_quantity'           => $availableQuantity,
            'available_quantity_elsewhere' => $quantityElsewhere,
            'is_on_demand'                 => $onDemand
        ];

        if ($currentQuantity == 0 && $availableQuantity > 0) {
            $dataToUpdate['back_in_stock_since'] = now();
        }

        if ($currentHomeQuantity <= 0 && $homeQuantity > 0 && ($product->status === ProductStatusEnum::COMING_SOON || $neverStockedOnlyElsewhere)) {
            $dataToUpdate['first_in_stock_at'] = now();
        }

        if (in_array($product->status, [
            ProductStatusEnum::FOR_SALE,
            ProductStatusEnum::OUT_OF_STOCK,
            ProductStatusEnum::COMING_SOON,
            ProductStatusEnum::NOT_FOR_SALE,
            ProductStatusEnum::DISCONTINUED
        ])) {
            if ($availableQuantity == 0) {
                $status = ProductStatusEnum::OUT_OF_STOCK;

                if ($numberOrgStocksChecked == 0 || $numberOrgStocksHasNeverBeenInWarehouse > 0) {
                    $status = ProductStatusEnum::COMING_SOON;
                }


                if ($currentQuantity > 0 || !$product->out_of_stock_since) {
                    $dataToUpdate['out_of_stock_since'] = now();
                }
            } else {
                $status = ProductStatusEnum::FOR_SALE;
            }
            $dataToUpdate['status'] = $status;
        }

        if (!$product->is_for_sale) {
            $dataToUpdate['status'] = ProductStatusEnum::NOT_FOR_SALE;
        }

        $product = UpdateProduct::run($product, $dataToUpdate);
        $product = ProductHydrateFirstAvailableAt::run($product);

        $wentOutOfStock = ($currentQuantity ?? 0) > 0 && $availableQuantity == 0;
        $cameBackInStock = ($currentQuantity ?? 0) == 0 && $availableQuantity > 0;
        if ($wentOutOfStock || $cameBackInStock) {
            SyncBasketLinesWithProductStock::dispatch($product);
        }

        return $product;
    }

    /**
     * INI-485 D2. What the best single other warehouse the shop counts could ship beyond the home figure: a line is never
     * split between warehouses, so the figure is the larger of home and that warehouse, never their sum.
     */
    private function quantityElsewhere(Product $product, int $homeQuantity): int
    {
        $otherWarehouses = $this->otherWarehousesByShop[$product->shop_id] ??= array_map(
            fn (array $entry) => [Organisation::find($entry['organisation_id']), $entry['safety_margin']],
            $product->shop->stockFromOtherWarehouses()
        );

        $best = $homeQuantity;
        foreach ($otherWarehouses as [$organisation, $safetyMargin]) {
            if ($organisation) {
                if ($this->preloadOtherWarehouses) {
                    $this->resolver()->preloadOrgStocksOf($organisation);
                }
                $best = max($best, $this->resolver()->wholeProductsIn($product, $organisation, $safetyMargin) ?? 0);
            }
        }

        return $best - $homeQuantity;
    }

    private function resolver(): ResolveCrossDispatch
    {
        return $this->resolver ??= ResolveCrossDispatch::make();
    }

    public string $commandSignature = 'product:hydrate-available-quantity {id?} {--shop=* : Shop slugs, all aiku shops when omitted}';

    public function asCommand(Command $command): void
    {
        $this->otherWarehousesByShop  = [];
        $this->resolver               = null;
        $this->preloadOtherWarehouses = false;

        if ($command->argument('id')) {
            if (is_numeric($command->argument('id'))) {
                $product = Product::findOrFail($command->argument('id'));
            } else {
                $product = Product::Where('slug', $command->argument('id'))->firstOrFail();
            }

            $this->handle($product);

            return;
        }

        $this->preloadOtherWarehouses = true;

        $chunkSize = 100; // Process 100 products at a time to save memory
        $count     = 0;

        $shopsQuery = Shop::where('is_aiku', true);
        if ($shopSlugs = $command->option('shop')) {
            $shopsQuery->whereIn('slug', $shopSlugs);
        }
        $aikuShops = $shopsQuery->pluck('id')->toArray();

        // Get total count for progress bar
        $total = Product::whereIn('shop_id', $aikuShops)->count();

        if ($total === 0) {
            $command->info("No products found.");

            return;
        }

        // Create a progress bar
        $progressBar = $command->getOutput()->createProgressBar($total);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $progressBar->start();

        Product::whereIn('shop_id', $aikuShops)->chunk($chunkSize, function ($products) use (&$count, $progressBar) {
            $this->resolver = null;
            foreach ($products as $product) {
                $this->handle($product);
                $count++;
                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $command->newLine();
        $command->info("Updated available quantity for $count products.");
    }

}
