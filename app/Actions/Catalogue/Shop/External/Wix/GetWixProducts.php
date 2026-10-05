<?php

namespace App\Actions\Catalogue\Shop\External\Wix;

use App\Actions\Catalogue\Product\StoreProduct;
use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\Catalogue\Shop\UpdateShop;
use App\Actions\Maintenance\Catalogue\SetTradeUnitsForFaireShops;
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

    public string $commandSignature = 'wix:products {shop}';

    public $jobQueue = 'long-running';

    public function handle(Shop $shop, ?Command $command = null): void
    {
        if ($shop->type !== ShopTypeEnum::EXTERNAL || $shop->engine !== ShopEngineEnum::WIX) {
            return;
        }

        $wixUser = WixUser::where('external_shop_id', $shop->id)->first();

        if (!$wixUser) {
            $command?->error("Shop $shop->slug is not connected to Wix");

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
            $liveVariantIds = collect($result['variants'])
                ->filter(fn (array $wixVariant) => $wixVariant['sku'])
                ->mapWithKeys(fn (array $wixVariant) => [$wixVariant['variant_id'] => true])
                ->all();

            $discontinued = $this->discontinueProductsGoneFromWix($shop, $liveVariantIds, $command);
            $command?->info("Discontinued $discontinued products no longer on Wix");
        }
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

        $product = Product::where('shop_id', $shop->id)->where('marketplace_id', $wixVariant['variant_id'])->first()
            ?? Product::where('shop_id', $shop->id)->whereNull('marketplace_id')->whereRaw('lower(code) = lower(?)', [$sku])->first();

        $productData = [
            'code'                  => $sku,
            'name'                  => $wixVariant['name'],
            'description'           => $wixVariant['description'] ?? $wixVariant['name'],
            'rrp'                   => $wixVariant['price'],
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

            SetTradeUnitsForFaireShops::run($product);
            $command?->info('Product added: '.$product->slug);

            return $product;
        } catch (Throwable $e) {
            $command?->error('Product upsert failed: '.$wixVariant['name'].' ('.$sku.') '.$e->getMessage());
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
     * Wix does not report a deleted product, it just stops returning it, so only a complete read of the
     * catalogue can say a product is gone. A sudden loss of more than a quarter of the live products is far
     * likelier to be a short read than a real clear-out, so it is reported and nothing is touched.
     *
     * @param array<string, true> $liveVariantIds
     */
    public function discontinueProductsGoneFromWix(Shop $shop, array $liveVariantIds, ?Command $command = null): int
    {
        $candidates = Product::where('shop_id', $shop->id)
            ->whereNotNull('marketplace_id')
            ->where('state', '!=', ProductStateEnum::DISCONTINUED);

        $activeCount    = (clone $candidates)->count();
        $survivingCount = (clone $candidates)->whereIn('marketplace_id', array_keys($liveVariantIds))->count();
        if ($activeCount > 0 && $survivingCount < $activeCount * self::MIN_LIVE_RATIO) {
            Sentry::captureMessage("Wix products discontinue skipped, only $survivingCount live of $activeCount ($shop->slug)");
            $command?->error("Discontinue skipped: only $survivingCount of $activeCount products are still live on Wix");

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
            ->where('engine', ShopEngineEnum::WIX)
            ->where('slug', $command->argument('shop'))
            ->firstOrFail();

        $this->handle($shop, $command);
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): void
    {
        $this->initialisation($organisation, $request);

        GetWixProducts::dispatch($shop);
    }
}
