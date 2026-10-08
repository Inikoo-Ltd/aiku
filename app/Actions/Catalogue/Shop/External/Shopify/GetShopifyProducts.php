<?php

namespace App\Actions\Catalogue\Shop\External\Shopify;

use App\Actions\Catalogue\Product\StoreProduct;
use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\Catalogue\Shop\Traits\WithShopifyExternalShopApi;
use App\Actions\Catalogue\Shop\UpdateShop;
use App\Actions\Maintenance\Catalogue\SetTradeUnitsForShopifyShops;
use App\Actions\OrgAction;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Enums\Catalogue\Product\ProductTradeConfigEnum;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Sentry;
use Throwable;

class GetShopifyProducts extends OrgAction
{
    use WithShopifyExternalShopApi;

    public const float MIN_LIVE_RATIO = 0.75;

    public const string SHOPIFY_ARCHIVED_STATUS = 'ARCHIVED';

    public string $commandSignature = 'external_shop:shopify_products {shop}';

    public $jobQueue = 'long-running';

    public function handle(Shop $shop, ?Command $command = null): void
    {
        if ($shop->type !== ShopTypeEnum::EXTERNAL || $shop->engine !== ShopEngineEnum::SHOPIFY) {
            return;
        }

        $shopifyUser = $this->getShopifyExternalShopUser($shop);

        if (!$shopifyUser) {
            $command?->error("Shop $shop->slug is not connected to Shopify");

            return;
        }

        UpdateShop::run($shop, [
            'last_external_shop_products_fetched_at' => now()
        ]);

        $result = $this->getAllShopifyExternalShopVariants($shopifyUser);

        if ($message = Arr::get($result, 'message')) {
            $command?->error('Shopify variants read incomplete: '.$message);
            Sentry::captureMessage("Shopify variants read incomplete ($shop->slug): $message");
        }

        $command?->info('Fetched '.count($result['variants']).' Shopify variants');

        $liveVariantIds = [];
        $seenSkus       = [];

        foreach ($result['variants'] as $shopifyVariant) {
            if (!$shopifyVariant['sku']) {
                $command?->warn('Variant without SKU skipped: '.$shopifyVariant['name']);

                continue;
            }

            $skuKey = mb_strtolower($shopifyVariant['sku']);

            if (isset($seenSkus[$skuKey])) {
                $command?->error('Duplicated SKU '.$shopifyVariant['sku'].' skipped: '.$shopifyVariant['name']);
                Sentry::captureMessage("Shopify SKU {$shopifyVariant['sku']} is used by more than one variant ($shop->slug)");

                continue;
            }

            $seenSkus[$skuKey] = true;

            if ($shopifyVariant['status'] !== self::SHOPIFY_ARCHIVED_STATUS) {
                $liveVariantIds[$shopifyVariant['variant_id']] = true;
            }

            $this->upsertShopifyProduct($shop, $shopifyVariant, $command);
        }

        if ($result['complete'] && $result['variants']) {
            $discontinued = $this->discontinueProductsGoneFromShopify($shop, $liveVariantIds, $command);
            $command?->info("Discontinued $discontinued products no longer on Shopify");
        }
    }

    /**
     * @param array{product_id: string, variant_id: string, inventory_item_id: ?string, inventory_tracked: bool, sku: ?string, name: string, description: ?string, price: float, status: string} $shopifyVariant
     */
    public function upsertShopifyProduct(Shop $shop, array $shopifyVariant, ?Command $command = null): ?Product
    {
        $sku = $shopifyVariant['sku'];

        if (!$sku) {
            return null;
        }

        $product = Product::where('shop_id', $shop->id)->where('marketplace_id', $shopifyVariant['variant_id'])->first()
            ?? Product::where('shop_id', $shop->id)->whereRaw('lower(code) = lower(?)', [$sku])->first();

        $productData = [
            'code'                  => $sku,
            'name'                  => $shopifyVariant['name'],
            'description'           => $shopifyVariant['description'] ?? $shopifyVariant['name'],
            'rrp'                   => $shopifyVariant['price'],
            'price'                 => $shopifyVariant['price'],
            'marketplace_id'        => $shopifyVariant['variant_id'],
            'marketplace_second_id' => $shopifyVariant['product_id'],
            'data'                  => [
                'shopify' => $shopifyVariant
            ]
        ];

        try {
            if ($product) {
                $isLive      = $shopifyVariant['status'] !== self::SHOPIFY_ARCHIVED_STATUS;
                $republished = $isLive && $product->state === ProductStateEnum::DISCONTINUED ? $this->getRepublishedStateData($product) : [];

                return UpdateProduct::make()->action($product, [
                    ...$republished,
                    ...$productData
                ], strict: false);
            }

            $product = StoreProduct::make()->action($shop, [
                ...$productData,
                'unit'         => 'Piece',
                'units'        => 1,
                'is_main'      => true,
                'trade_config' => ProductTradeConfigEnum::AUTO,
                'status'       => ProductStatusEnum::FOR_SALE,
                'state'        => ProductStateEnum::IN_PROCESS,
            ], strict: false);

            SetTradeUnitsForShopifyShops::run($product);
            $command?->info('Product added: '.$product->slug);

            return $product;
        } catch (Throwable $e) {
            $command?->error('Product upsert failed: '.$shopifyVariant['name'].' ('.$sku.') '.$e->getMessage());
            Sentry::captureException($e);

            return null;
        }
    }

    /**
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
     * Shopify only says a variant is gone by no longer returning it, so only a complete read can discontinue,
     * and a sudden loss of more than a quarter of the live products is treated as a short read, not a clear-out.
     *
     * @param array<string, true> $liveVariantIds
     */
    public function discontinueProductsGoneFromShopify(Shop $shop, array $liveVariantIds, ?Command $command = null): int
    {
        $candidates = Product::where('shop_id', $shop->id)
            ->whereNotNull('marketplace_id')
            ->where('state', '!=', ProductStateEnum::DISCONTINUED);

        $activeCount    = (clone $candidates)->count();
        $survivingCount = (clone $candidates)->whereIn('marketplace_id', array_keys($liveVariantIds))->count();

        if ($activeCount > 0 && $survivingCount < $activeCount * self::MIN_LIVE_RATIO) {
            Sentry::captureMessage("Shopify products discontinue skipped, only $survivingCount live of $activeCount ($shop->slug)");
            $command?->error("Discontinue skipped: only $survivingCount of $activeCount products are still live on Shopify");

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

    public function asCommand(Command $command): void
    {
        $shop = Shop::where('type', ShopTypeEnum::EXTERNAL)
            ->where('engine', ShopEngineEnum::SHOPIFY)
            ->where('slug', $command->argument('shop'))
            ->firstOrFail();

        $this->handle($shop, $command);
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("products.{$this->shop->id}.edit");
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): void
    {
        $this->initialisationFromShop($shop, $request);

        GetShopifyProducts::dispatch($shop);
    }
}
