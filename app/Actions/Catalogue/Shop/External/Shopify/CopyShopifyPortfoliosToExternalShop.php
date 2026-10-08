<?php

namespace App\Actions\Catalogue\Shop\External\Shopify;

use App\Actions\Catalogue\Product\StoreProduct;
use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\Catalogue\Product\UpdateTradeUnitsForExternalProduct;
use App\Actions\Catalogue\Shop\Traits\WithShopifyExternalShopApi;
use App\Actions\OrgAction;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Enums\Catalogue\Product\ProductTradeConfigEnum;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\Portfolio;
use App\Models\Goods\TradeUnit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Sentry;
use Throwable;

class CopyShopifyPortfoliosToExternalShop extends OrgAction
{
    use WithShopifyExternalShopApi;

    public const string EXCLUDED_TRADE_UNIT_SLUG = 'ial01';

    public string $commandSignature = 'external_shop:shopify_copy_portfolios {shop}';

    /**
     * A store moving from a dropshipping channel keeps every Shopify listing it had: each portfolio becomes a
     * product of the external shop on the same Shopify variant, made of the same trade units as the product sold.
     *
     * @return array{copied: int, updated: int, skipped: int}
     */
    public function handle(Shop $shop, ?Command $command = null): array
    {
        $summary = ['copied' => 0, 'updated' => 0, 'skipped' => 0];

        $customerSalesChannelId = $this->getShopifyExternalShopUser($shop)?->customer_sales_channel_id;

        if (!$customerSalesChannelId) {
            $command?->error('The Shopify store of this shop has no dropshipping portfolios');

            return $summary;
        }

        $seenVariants = [];
        $seenCodes    = [];

        Portfolio::where('customer_sales_channel_id', $customerSalesChannelId)
            ->where('item_type', 'Product')
            ->where('status', true)
            ->whereNotNull('platform_product_variant_id')
            ->chunkById(100, function ($portfolios) use ($shop, $command, &$summary, &$seenVariants, &$seenCodes) {
                foreach ($portfolios as $portfolio) {
                    $result = $this->copyPortfolio($shop, $portfolio, $seenVariants, $seenCodes, $command);
                    $summary[$result]++;
                }
            });

        return $summary;
    }

    /**
     * @param array<string, true> $seenVariants
     * @param array<string, true> $seenCodes
     */
    private function copyPortfolio(Shop $shop, Portfolio $portfolio, array &$seenVariants, array &$seenCodes, ?Command $command): string
    {
        $sourceProduct = Product::find($portfolio->item_id);
        $code          = trim((string) ($portfolio->platform_sku ?: $portfolio->sku ?: $sourceProduct?->code));

        if (!$sourceProduct || $code === '') {
            $command?->warn("Portfolio $portfolio->id skipped: no product or SKU");

            return 'skipped';
        }

        $codeKey = mb_strtolower($code);

        if (isset($seenVariants[$portfolio->platform_product_variant_id]) || isset($seenCodes[$codeKey])) {
            $command?->warn("Portfolio $portfolio->id skipped: variant or SKU $code already copied");

            return 'skipped';
        }

        $seenVariants[$portfolio->platform_product_variant_id] = true;
        $seenCodes[$codeKey]                                   = true;

        $product = Product::where('shop_id', $shop->id)->where('marketplace_id', $portfolio->platform_product_variant_id)->first()
            ?? Product::where('shop_id', $shop->id)->whereRaw('lower(code) = lower(?)', [$code])->first();

        $price = (float) ($portfolio->selling_price ?: $sourceProduct->price);

        $productData = [
            'code'                  => $code,
            'name'                  => $portfolio->customer_product_name ?: $sourceProduct->name,
            'description'           => $portfolio->customer_description ?: ($sourceProduct->description ?: $sourceProduct->name),
            'price'                 => $price,
            'rrp'                   => $price,
            'marketplace_id'        => $portfolio->platform_product_variant_id,
            'marketplace_second_id' => $portfolio->platform_product_id,
        ];

        try {
            $isNew = !$product;

            DB::transaction(function () use (&$product, $shop, $productData, $sourceProduct) {
                if ($product) {
                    $product = UpdateProduct::make()->action($product, $productData, strict: false);
                } else {
                    $product = StoreProduct::make()->action($shop, [
                        ...$productData,
                        'unit'         => $sourceProduct->unit ?: 'Piece',
                        'units'        => (float) $sourceProduct->units ?: 1,
                        'is_main'      => true,
                        'trade_config' => ProductTradeConfigEnum::AUTO,
                        'status'       => ProductStatusEnum::FOR_SALE,
                        'state'        => ProductStateEnum::IN_PROCESS,
                    ], strict: false);
                }

                if (!$product->tradeUnits()->exists() && $tradeUnits = $this->getTradeUnits($sourceProduct)) {
                    UpdateTradeUnitsForExternalProduct::make()->action($product, ['trade_units' => $tradeUnits]);
                }
            });

            $command?->info(($isNew ? 'Copied ' : 'Updated ').$code);

            return $isNew ? 'copied' : 'updated';
        } catch (Throwable $e) {
            $command?->error("Portfolio $portfolio->id ($code) not copied: ".$e->getMessage());
            Sentry::captureException($e);

            return 'skipped';
        }
    }

    /**
     * @return array<int, array{id: int, quantity: float}>
     */
    private function getTradeUnits(Product $sourceProduct): array
    {
        return $sourceProduct->tradeUnits
            ->filter(fn (TradeUnit $tradeUnit) => $tradeUnit->slug != self::EXCLUDED_TRADE_UNIT_SLUG && (float) $tradeUnit->pivot->quantity > 0)
            ->map(fn (TradeUnit $tradeUnit) => [
                'id'       => $tradeUnit->id,
                'quantity' => (float) $tradeUnit->pivot->quantity,
            ])
            ->values()
            ->all();
    }

    public function asCommand(Command $command): int
    {
        $shop = Shop::where('type', ShopTypeEnum::EXTERNAL)
            ->where('engine', ShopEngineEnum::SHOPIFY)
            ->where('slug', $command->argument('shop'))
            ->firstOrFail();

        $summary = $this->handle($shop, $command);

        $command->info("Copied {$summary['copied']}, updated {$summary['updated']}, skipped {$summary['skipped']}");

        return 0;
    }
}
