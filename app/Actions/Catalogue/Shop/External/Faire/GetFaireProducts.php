<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 01 Mar 2026 22:05:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\External\Faire;

use App\Actions\Catalogue\Product\StoreProduct;
use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\Catalogue\Shop\UpdateShop;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Maintenance\Catalogue\FlagFaireCaseSizeMismatch;
use App\Actions\Maintenance\Catalogue\SetTradeUnitsForFaireShops;
use App\Actions\OrgAction;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Enums\Catalogue\Product\ProductTradeConfigEnum;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Currency;
use App\Models\SysAdmin\Organisation;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Sentry;
use Throwable;

class GetFaireProducts extends OrgAction
{
    public const float MIN_LIVE_RATIO = 0.75;

    public string $commandSignature = 'faire:products {shop} {min_hours?}';

    public $jobQueue = 'long-running';

    public function handle(Shop $shop, array $modelData, ?Command $command = null): void
    {
        if ($shop->type !== ShopTypeEnum::EXTERNAL || $shop->engine !== ShopEngineEnum::FAIRE) {
            return;
        }

        $faireProducts = [];
        $limit         = 200;
        $page          = 1;
        $filters       = [];

        if ($minHours = Arr::get($modelData, 'min_hours')) {
            $filters = [
                'updated_at_min' => now()->subHours($minHours)->toIsoString(),
            ];
        }

        UpdateShop::run($shop, [
            'last_external_shop_products_fetched_at' => now()
        ]);

        $fetchFailed = false;

        do {
            $response = $shop->getFaireProducts([
                ...$filters,
                'limit' => $limit,
                'page'  => $page
            ]);

            if (Arr::get($response, 'success') === false) {
                $fetchFailed = true;
                $command?->error("Faire products fetch failed on page $page");
                break;
            }

            $fetchedProducts = Arr::get($response, 'products') ?? [];

            $faireProducts = array_merge($faireProducts, $fetchedProducts);
            $command?->info("Fetched  ($page) ".count($fetchedProducts)." products, total: ".count($faireProducts));


            $page++;
        } while (count($fetchedProducts) === $limit);


        foreach ($faireProducts as $faireProduct) {
            $this->upsertFaireProduct($shop, $faireProduct, $command);
        }

        if (!$filters && !$fetchFailed && $faireProducts) {
            $discontinued = $this->discontinueProductsGoneFromFaire($shop, $this->getLiveFaireVariantIds($faireProducts), $command);
            $command?->info("Discontinued $discontinued products no longer on Faire");
        }
    }

    /**
     * A product coming back from Faire goes where a new one would: active only once its trade units are set,
     * otherwise back to in process. Status is reset too, because discontinuing set it and the stock hydrator
     * never lifts a discontinued status on its own.
     *
     * @return array<string, ProductStateEnum|ProductStatusEnum>
     */
    public function getRepublishedStateData(Product $product): array
    {
        if (!$product->tradeUnits()->exists()) {
            return [
                'state'  => ProductStateEnum::IN_PROCESS,
                'status' => ProductStatusEnum::IN_PROCESS,
            ];
        }

        return [
            'state'  => ProductStateEnum::ACTIVE,
            'status' => ProductStatusEnum::FOR_SALE,
        ];
    }

    /**
     * Faire does not report a deleted product, it just stops returning it. Only a complete, successful fetch
     * of the whole catalogue can say a product is gone, so this never runs on a partial or failed sync.
     *
     * @param array<int, array<string, mixed>> $faireProducts
     *
     * @return array<string, true>
     */
    public function getLiveFaireVariantIds(array $faireProducts): array
    {
        $liveVariantIds = [];

        foreach ($faireProducts as $faireProduct) {
            if (!in_array(Arr::get($faireProduct, 'lifecycle_state'), ['PUBLISHED', 'UNPUBLISHED'])) {
                continue;
            }

            foreach (Arr::get($faireProduct, 'variants', []) as $variant) {
                if (in_array(Arr::get($variant, 'lifecycle_state'), ['PUBLISHED', 'UNPUBLISHED'])) {
                    $liveVariantIds[$variant['id']] = true;
                }
            }
        }

        return $liveVariantIds;
    }

    /**
     * A product deleted on Faire used to stay active in Aiku for ever, so every re-upload left a duplicate
     * behind (HELP-2040). Discontinued, never deleted: past orders and invoices still point at it.
     *
     * @param array<string, true> $liveVariantIds
     */
    public function discontinueProductsGoneFromFaire(Shop $shop, array $liveVariantIds, ?Command $command = null): int
    {
        $candidates = Product::where('shop_id', $shop->id)
            ->whereNotNull('marketplace_id')
            ->where('state', '!=', ProductStateEnum::DISCONTINUED);

        /**
         * A 2xx page that silently comes back short ends the fetch as if the catalogue were complete. Faire
         * losing more than a quarter of a shop's live products at once is far likelier to be that than a real
         * clear-out, so it is reported and nothing is touched.
         */
        $activeCount    = (clone $candidates)->count();
        $survivingCount = (clone $candidates)->whereIn('marketplace_id', array_keys($liveVariantIds))->count();
        if ($activeCount > 0 && $survivingCount < $activeCount * self::MIN_LIVE_RATIO) {
            Sentry::captureMessage("Faire products discontinue skipped, only $survivingCount live of $activeCount ({$shop->slug})");
            $command?->error("Discontinue skipped: only $survivingCount of $activeCount products are still live on Faire");

            return 0;
        }

        $discontinued = 0;

        $candidates->chunkById(200, function ($products) use ($liveVariantIds, &$discontinued, $command) {
            foreach ($products as $product) {
                if (isset($liveVariantIds[$product->marketplace_id])) {
                    continue;
                }

                try {
                    UpdateProduct::make()->action($product, ['state' => ProductStateEnum::DISCONTINUED], hydratorsDelay: 120, strict: false);
                    $discontinued++;
                } catch (Throwable $e) {
                    $command?->error("Discontinue failed: $product->slug ".$e->getMessage());
                    Sentry::captureException($e);
                }
            }
        });

        return $discontinued;
    }

    public function upsertFaireProduct(Shop $shop, array $faireProduct, ?Command $command = null): void
    {
        $faireState = Arr::get($faireProduct, 'lifecycle_state');
        if (in_array($faireState, ['PUBLISHED', 'UNPUBLISHED'])) {

            foreach ($faireProduct['variants'] as $variant) {
                $faireVariantState = Arr::get($variant, 'lifecycle_state');

                if (!in_array($faireVariantState, ['PUBLISHED', 'UNPUBLISHED'])) {
                    continue;
                }


                $faireSKU = Arr::get($variant, 'sku');
                if (!$faireSKU) {
                    continue;
                }

                $product = Product::where('shop_id', $shop->id)->where('marketplace_id', $variant['id'])->first();

                if ($product) {
                    try {
                        $caseSizeChanged = (float) $product->units != (float) $faireProduct['unit_multiplier'];
                        $republished     = $product->state === ProductStateEnum::DISCONTINUED ? $this->getRepublishedStateData($product) : [];
                        UpdateProduct::make()->action($product, [
                            ...$republished,
                            'code'                  => $faireSKU,
                            'name'                  => $faireProduct['name'].' - '.$variant['name'],
                            'description'           => $faireProduct['description'],
                            'rrp'                   => $this->extractFaireRetailPrices($shop, Arr::get($variant, 'prices')),
                            'price'                 => $faireProduct['unit_multiplier'] * $this->extractFaireCostPrices($shop, Arr::get($variant, 'prices')),
                            'units'                 => $faireProduct['unit_multiplier'],
                            'marketplace_second_id' => $faireProduct['id'],
                            'data'                  => [
                                'faire' => $variant
                            ]
                        ], strict: false);
                        if ($caseSizeChanged && $product->tradeUnits()->exists()) {
                            $product->updateQuietly(['units_review' => FlagFaireCaseSizeMismatch::BUCKET]);
                        }
                    } catch (Exception $e) {
                        $command?->error("Product update failed: ".$faireProduct['name'].' - '.$variant['name'].' '.$e->getMessage());
                    }
                } else {
                    try {
                        $product = StoreProduct::make()->action($shop, [
                            'code'                  => $faireSKU,
                            'name'                  => $faireProduct['name'].' - '.$variant['name'],
                            'description'           => $faireProduct['description'],
                            'rrp'                   => $this->extractFaireRetailPrices($shop, Arr::get($variant, 'prices')),
                            'price'                 => $faireProduct['unit_multiplier'] * $this->extractFaireCostPrices($shop, Arr::get($variant, 'prices')),
                            'unit'                  => 'Piece',
                            'units'                 => $faireProduct['unit_multiplier'],
                            'is_main'               => true,
                            'trade_config'          => ProductTradeConfigEnum::AUTO,
                            'status'                => ProductStatusEnum::FOR_SALE,
                            'state'                 => ProductStateEnum::IN_PROCESS,
                            'marketplace_id'        => $variant['id'],
                            'marketplace_second_id' => $faireProduct['id'],
                            'data'                  => [
                                'faire' => $variant
                            ]
                        ], strict: false);
                        SetTradeUnitsForFaireShops::run($product);
                        $command?->info("Product added: ".$product->slug);
                    } catch (Exception|Throwable $e) {
                        $command?->error("Product creation failed: ".$faireProduct['name'].' - '.$variant['name'].' '.$e->getMessage());
                    }
                }
            }
        }
    }

    public function extractFaireRetailPrices(Shop $shop, array $prices): float
    {
        $found         = false;
        $rrp           = Arr::get($prices, '0.retail_price.amount_minor');
        $faireCurrency = Arr::get($prices, '0.retail_price.currency');

        foreach ($prices as $price) {
            if (Arr::get($price, 'retail_price.currency') === $shop->currency->code) {
                $found = true;
                $rrp   = Arr::get($price, 'retail_price.amount_minor');
            }
        }

        if (!$found) {
            $currency = Currency::where('code', $faireCurrency)->first();
            $rrp      = GetCurrencyExchange::run($currency, $shop->currency);
        }

        return $rrp / 100;
    }

    public function extractFaireCostPrices(Shop $shop, array $prices): float
    {
        $found         = false;
        $cost          = Arr::get($prices, '0.wholesale_price.amount_minor');
        $faireCurrency = Arr::get($prices, '0.wholesale_price.currency');

        foreach ($prices as $price) {
            if (Arr::get($price, 'wholesale_price.currency') === $shop->currency->code) {
                $found = true;
                $cost  = Arr::get($price, 'wholesale_price.amount_minor');
            }
        }

        if (!$found) {
            $currency = Currency::where('code', $faireCurrency)->first();
            $cost     = GetCurrencyExchange::run($currency, $shop->currency);
        }

        return $cost / 100;
    }

    public function asCommand(Command $command): void
    {
        $shop = Shop::where('type', ShopTypeEnum::EXTERNAL)->where('engine', ShopEngineEnum::FAIRE)
            ->where('slug', $command->argument('shop'))
            ->first();

        $modelData = [
            'min_hours' => $command->argument('min_hours')
        ];

        $this->handle($shop, $modelData, $command);
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): void
    {
        $this->initialisation($organisation, $request);

        GetFaireProducts::dispatch($shop, $this->validatedData);
    }
}
