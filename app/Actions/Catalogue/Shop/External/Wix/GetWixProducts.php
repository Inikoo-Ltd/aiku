<?php

namespace App\Actions\Catalogue\Shop\External\Wix;

use App\Actions\Catalogue\Product\StoreProduct;
use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\Catalogue\Shop\UpdateShop;
use App\Actions\Maintenance\Catalogue\SetTradeUnitsForWixShops;
use App\Actions\Catalogue\Shop\Traits\WithWixExternalShopApi;
use App\Actions\OrgAction;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Enums\Catalogue\Product\ProductTradeConfigEnum;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\WixUser;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Lorisleiva\Actions\ActionRequest;
use Sentry;
use Throwable;

class GetWixProducts extends OrgAction
{
    use WithWixExternalShopApi;

    public const float MIN_LIVE_RATIO = 0.75;

    public string $commandSignature = 'wix:products {shop} {--sku= : Import only the Wix variants with this SKU} {--dry-run : With --sku, show what would be imported without saving} {--missing-sku : List the Wix products with no SKU, which are never imported, and change nothing}';

    public $jobQueue = 'long-running';

    public function handle(Shop $shop, ?Command $command = null, ?string $sku = null, bool $dryRun = false): void
    {
        if ($shop->type !== ShopTypeEnum::EXTERNAL || $shop->engine !== ShopEngineEnum::WIX) {
            return;
        }

        $wixUser = WixUser::where('external_shop_id', $shop->id)->first();

        if (!$wixUser) {
            $command?->error("Shop $shop->slug is not connected to Wix");

            return;
        }

        if ($sku) {
            $this->importWixVariantsBySku($shop, $wixUser, $sku, $dryRun, $command);

            return;
        }

        UpdateShop::run($shop, [
            'last_external_shop_products_fetched_at' => now()
        ]);

        $result = $this->getAllWixVariants($wixUser);

        $command?->info('Fetched '.count($result['variants']).' Wix variants');

        foreach ($result['variants'] as $wixVariant) {
            $this->upsertWixProduct($shop, $wixVariant, $command);
        }

        if ($result['complete'] && $result['variants']) {
            $liveVariantKeys = collect($result['variants'])
                ->filter(fn (array $wixVariant) => $wixVariant['sku'])
                ->mapWithKeys(fn (array $wixVariant) => [$this->getWixVariantKey($wixVariant['product_id'], $wixVariant['variant_id']) => true])
                ->all();

            $discontinued = $this->discontinueProductsGoneFromWix($shop, $liveVariantKeys, $command);
            $command?->info("Discontinued $discontinued products no longer on Wix");
        }
    }

    /**
     * Imports the variants carrying one SKU and nothing else: no other product is touched and nothing is
     * discontinued, so a change to the import can be tried on a single product first.
     */
    public function importWixVariantsBySku(Shop $shop, WixUser $wixUser, string $sku, bool $dryRun = false, ?Command $command = null): void
    {
        $wixVariants = collect($this->getAllWixVariants($wixUser)['variants'])
            ->filter(fn (array $wixVariant) => $wixVariant['sku'] && strtolower($wixVariant['sku']) === strtolower($sku))
            ->values();

        if ($wixVariants->isEmpty()) {
            $command?->error("No Wix variant with SKU $sku");

            return;
        }

        foreach ($wixVariants as $wixVariant) {
            $existing = $this->findProduct($shop, $wixVariant);

            $command?->table(['Wix product id', 'Wix variant id', 'SKU', 'Name', 'Price', 'Matches'], [[
                $wixVariant['product_id'],
                $wixVariant['variant_id'],
                $wixVariant['sku'],
                $wixVariant['name'],
                $wixVariant['price'],
                $existing ? "#$existing->id $existing->code ($existing->slug)" : 'nothing, a new product',
            ]]);

            if ($dryRun) {
                continue;
            }

            $product = $this->upsertWixProduct($shop, $wixVariant, $command);

            if ($product) {
                $command?->info(($existing ? 'Updated' : 'Created')." #$product->id $product->code ($product->slug)");
            }
        }
    }

    /**
     * A variant without a SKU is never imported, so any Wix order for it is not imported either.
     *
     * @return array<int, array{name: string, visible: string, product_id: string, variant_id: string}>
     */
    public function listWixVariantsWithoutSku(Shop $shop, ?Command $command = null): array
    {
        $wixUser = WixUser::where('external_shop_id', $shop->id)->first();

        if (!$wixUser) {
            $command?->error("Shop $shop->slug is not connected to Wix");

            return [];
        }

        $rows = $this->getWixVariantsWithoutSku($this->getAllWixVariants($wixUser)['variants']);

        $command?->table(['Wix product', 'Visible in store', 'Wix product id', 'Wix variant id'], $rows);
        $command?->info(count($rows).' Wix variants have no SKU');

        return $rows;
    }

    /**
     * @param array<int, array{product_id: string, variant_id: string, sku: ?string, name: string, visible: bool}> $wixVariants
     *
     * @return array<int, array{name: string, visible: string, product_id: string, variant_id: string}>
     */
    public function getWixVariantsWithoutSku(array $wixVariants): array
    {
        return collect($wixVariants)
            ->filter(fn (array $wixVariant) => !trim((string) $wixVariant['sku']))
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->map(fn (array $wixVariant) => [
                'name'       => $wixVariant['name'],
                'visible'    => $wixVariant['visible'] ? 'yes' : 'no',
                'product_id' => $wixVariant['product_id'],
                'variant_id' => $wixVariant['variant_id'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param array{product_id: string, variant_id: string, sku: ?string, name: string, description: ?string, price: float, image: ?string, visible: bool} $wixVariant
     */
    public function upsertWixProduct(Shop $shop, array $wixVariant, ?Command $command = null): ?Product
    {
        $sku = $wixVariant['sku'];

        if (!$sku) {
            return null;
        }

        $product = $this->findProduct($shop, $wixVariant);

        $productData = [
            'code'                  => $sku,
            'name'                  => $wixVariant['name'],
            'description'           => $wixVariant['description'] ?? $wixVariant['name'],
            'rrp'                   => $wixVariant['price'] > 0 ? $wixVariant['price'] : null,
            'price'                 => $wixVariant['price'],
            'marketplace_id'        => $wixVariant['variant_id'],
            'marketplace_second_id' => $wixVariant['product_id'],
            'data'                  => [
                'wix' => $wixVariant
            ]
        ];

        try {
            if ($product) {
                $republished = $product->state === ProductStateEnum::DISCONTINUED ? $this->getRepublishedStateData($product) : [];

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

            SetTradeUnitsForWixShops::run($product);
            $command?->info('Product added: '.$product->slug);

            return $product;
        } catch (Throwable $e) {
            $command?->error('Product upsert failed: '.$wixVariant['name'].' ('.$sku.') '.$e->getMessage());
            Sentry::captureException($e);

            return null;
        }
    }

    /**
     * A Wix product without options has no variant of its own: every one of them carries the same all-zero
     * variant id, so a variant is only identified together with its product. A product stored before its Wix
     * product id was kept is matched by a real variant id alone, and anything left by its SKU.
     *
     * @param array{product_id: string, variant_id: string, sku: ?string} $wixVariant
     */
    public function findProduct(Shop $shop, array $wixVariant): ?Product
    {
        $variantId = $wixVariant['variant_id'];

        $product = Product::where('shop_id', $shop->id)
            ->where('marketplace_second_id', $wixVariant['product_id'])
            ->where('marketplace_id', $variantId)
            ->first();

        if (!$product && $variantId !== self::WIX_V1_DEFAULT_VARIANT_ID) {
            $product = Product::where('shop_id', $shop->id)
                ->whereNull('marketplace_second_id')
                ->where('marketplace_id', $variantId)
                ->first();
        }

        return $product ?? Product::where('shop_id', $shop->id)
            ->whereRaw('lower(code) = lower(?)', [$wixVariant['sku']])
            ->where(function ($query) {
                $query->whereNull('marketplace_id')
                    ->orWhereNull('marketplace_second_id')
                    ->orWhere('marketplace_id', self::WIX_V1_DEFAULT_VARIANT_ID);
            })
            ->first();
    }

    public function getWixVariantKey(?string $wixProductId, ?string $wixVariantId): string
    {
        return $wixProductId.':'.$wixVariantId;
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
     * Wix does not report a deleted product, it just stops returning it, so only a complete read of the
     * catalogue can say a product is gone. A sudden loss of more than a quarter of the live products is far
     * likelier to be a short read than a real clear-out, so it is reported and nothing is touched.
     *
     * @param array<string, true> $liveVariantKeys keyed by getWixVariantKey()
     */
    public function discontinueProductsGoneFromWix(Shop $shop, array $liveVariantKeys, ?Command $command = null): int
    {
        $candidates = Product::where('shop_id', $shop->id)
            ->whereNotNull('marketplace_id')
            ->where('state', '!=', ProductStateEnum::DISCONTINUED)
            ->get(['id', 'marketplace_id', 'marketplace_second_id']);

        $goneIds = $candidates
            ->reject(fn (Product $product) => isset($liveVariantKeys[$this->getWixVariantKey($product->marketplace_second_id, $product->marketplace_id)]))
            ->pluck('id');

        $activeCount    = $candidates->count();
        $survivingCount = $activeCount - $goneIds->count();
        if ($activeCount > 0 && $survivingCount < $activeCount * self::MIN_LIVE_RATIO) {
            Sentry::captureMessage("Wix products discontinue skipped, only $survivingCount live of $activeCount ($shop->slug)");
            $command?->error("Discontinue skipped: only $survivingCount of $activeCount products are still live on Wix");

            return 0;
        }

        $discontinued = 0;

        foreach ($goneIds->chunk(200) as $ids) {
            foreach (Product::whereIn('id', $ids)->get() as $product) {
                try {
                    UpdateProduct::make()->action($product, ['state' => ProductStateEnum::DISCONTINUED], hydratorsDelay: 120, strict: false);
                    $discontinued++;
                } catch (Throwable $e) {
                    $command?->error("Discontinue failed: $product->slug ".$e->getMessage());
                    Sentry::captureException($e);
                }
            }
        }

        return $discontinued;
    }

    public function asCommand(Command $command): void
    {
        $shop = Shop::where('type', ShopTypeEnum::EXTERNAL)
            ->where('engine', ShopEngineEnum::WIX)
            ->where('slug', $command->argument('shop'))
            ->firstOrFail();

        if ($command->option('missing-sku')) {
            $this->listWixVariantsWithoutSku($shop, $command);

            return;
        }

        $this->handle($shop, $command, $command->option('sku'), (bool) $command->option('dry-run'));
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("products.{$this->shop->id}.edit");
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): void
    {
        $this->initialisationFromShop($shop, $request);

        GetWixProducts::dispatch($shop);
    }
}
