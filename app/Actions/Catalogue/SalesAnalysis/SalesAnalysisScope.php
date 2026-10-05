<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 26 Sep 2026 03:10:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\SalesAnalysis;

use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Goods\Stock;
use App\Models\Goods\StockFamily;
use App\Models\Goods\TradeUnit;
use App\Models\Goods\TradeUnitFamily;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockFamily;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use ReflectionClass;
use Illuminate\Support\Facades\DB;

/**
 * What a sales analysis covers: the products whose invoices are counted, the currency, what the
 * breakdown table groups them by, and which records hold the changes shown on the chart.
 */
final class SalesAnalysisScope
{
    private const array ROW_SOURCES = [
        'master_assets'             => ['status' => 'status', 'for_sale' => 'is_for_sale', 'discontinued_at' => 'discontinued_at'],
        'master_product_categories' => ['status' => 'status', 'for_sale' => 'is_for_sale', 'discontinued_at' => 'discontinued_at'],
        'product_categories'        => ['status' => "state not in ('discontinued', 'in_process')", 'for_sale' => 'is_for_sale', 'discontinued_at' => 'discontinued_at'],
        'products'                  => ['status' => "state <> 'discontinued'", 'for_sale' => 'is_for_sale', 'discontinued_at' => 'discontinued_at'],
        'trade_units'               => ['status' => "coalesce(status, '') not like '%discontinued%'", 'for_sale' => 'is_for_sale', 'discontinued_at' => 'null'],
        'stocks'                    => ['status' => "state <> 'discontinued'", 'for_sale' => 'true', 'discontinued_at' => 'discontinued_at'],
        'org_stocks'                => ['status' => "state <> 'discontinued'", 'for_sale' => 'true', 'discontinued_at' => 'null'],
    ];

    /**
     * @param array<int, int> $productIds
     * @param array<int, int> $breakdownKeyByProduct product id => breakdown row key
     * @param array<int, array> $breakdownRows breakdown row key => code, name, slug, status, is_for_sale, created_at, discontinued_at
     * @param array<int, array{type: string, labels: array<int, string>, shops: array<int, int>|null}> $audits
     * @param array<string, array<int, int>> $offerTriggers offer trigger type => trigger ids
     * @param array<int, int> $webpageShops webpage id => shop id of the pages of the scope itself
     * @param array<int, string> $shopNodeStates shop id => state of the scope's own record in that shop
     */
    public function __construct(
        public string $currency,
        public string $amountColumn,
        public array $productIds,
        public ?string $breakdownLabel = null,
        public array $breakdownKeyByProduct = [],
        public array $breakdownRows = [],
        public array $audits = [],
        public array $offerTriggers = [],
        public array $webpageShops = [],
        public array $shopNodeStates = [],
        public ?string $cacheKey = null,
    ) {
    }

    public static function forMasterCategory(MasterProductCategory $category): self
    {
        return self::lazy('MasterProductCategory:'.$category->id, fn () => self::buildMasterCategory($category));
    }

    public static function forMasterAsset(MasterAsset $masterAsset): self
    {
        return self::lazy('MasterAsset:'.$masterAsset->id, fn () => self::buildMasterAsset($masterAsset));
    }

    public static function forProductCategory(ProductCategory $category): self
    {
        return self::lazy('ProductCategory:'.$category->id, fn () => self::buildProductCategory($category));
    }

    public static function forProduct(Product $product): self
    {
        return self::lazy('Product:'.$product->id, fn () => self::buildProduct($product));
    }

    public static function forTradeUnit(TradeUnit $tradeUnit): self
    {
        return self::lazy('TradeUnit:'.$tradeUnit->id, fn () => self::buildTradeUnit($tradeUnit));
    }

    public static function forTradeUnitFamily(TradeUnitFamily $tradeUnitFamily): self
    {
        return self::lazy('TradeUnitFamily:'.$tradeUnitFamily->id, fn () => self::buildTradeUnitFamily($tradeUnitFamily));
    }

    public static function forStock(Stock $stock): self
    {
        return self::lazy('Stock:'.$stock->id, fn () => self::buildStock($stock));
    }

    public static function forStockFamily(StockFamily $stockFamily): self
    {
        return self::lazy('StockFamily:'.$stockFamily->id, fn () => self::buildStockFamily($stockFamily));
    }

    public static function forOrgStock(OrgStock $orgStock): self
    {
        return self::lazy('OrgStock:'.$orgStock->id, fn () => self::buildOrgStock($orgStock));
    }

    public static function forOrgStockFamily(OrgStockFamily $orgStockFamily): self
    {
        return self::lazy('OrgStockFamily:'.$orgStockFamily->id, fn () => self::buildOrgStockFamily($orgStockFamily));
    }

    /**
     * Building a scope reads every product of a department, so it is built only when the analysis
     * is not cached yet: the cache key is set upfront and the rest is read on first use.
     */
    private static function lazy(string $cacheKey, Closure $build): self
    {
        $reflector = new ReflectionClass(self::class);
        $scope     = $reflector->newLazyGhost(function (self $scope) use ($build) {
            foreach (Arr::except(get_object_vars($build()), 'cacheKey') as $property => $value) {
                $scope->$property = $value;
            }
        });
        $reflector->getProperty('cacheKey')->setRawValueWithoutLazyInitialization($scope, $cacheKey);

        return $scope;
    }

    private static function buildMasterCategory(MasterProductCategory $category): self
    {
        [$masterColumn, $shopColumn] = match ($category->type) {
            MasterProductCategoryTypeEnum::DEPARTMENT => ['master_department_id', 'department_id'],
            MasterProductCategoryTypeEnum::SUB_DEPARTMENT => ['master_sub_department_id', 'sub_department_id'],
            default => ['master_family_id', 'family_id'],
        };

        $shopCategories = DB::table('product_categories')
            ->where('master_product_category_id', $category->id)
            ->select(['id', 'shop_id', 'state', 'webpage_id'])
            ->get();
        $masterAssetIds = DB::table('master_assets')->where($masterColumn, $category->id)->pluck('id');

        $productIds = DB::table('products')->whereIn('master_product_id', $masterAssetIds)->pluck('id')
            ->merge(DB::table('products')->whereIn($shopColumn, $shopCategories->pluck('id'))->pluck('id'))
            ->unique()->values()->all();

        $isFamily = $category->type === MasterProductCategoryTypeEnum::FAMILY;
        $masterAssetOfProduct = self::productColumn($productIds, 'master_product_id');

        [$label, $keys, $rows] = $isFamily
            ? [__('Products'), $masterAssetOfProduct, self::rows('master_assets', $masterAssetIds->all())]
            : [__('Families'), self::masterFamilyOfProducts($masterAssetOfProduct), self::rows('master_product_categories', DB::table('master_assets')->whereIn('id', $masterAssetIds)->whereNotNull('master_family_id')->distinct()->pluck('master_family_id')->all())];

        return new self(
            currency: group()->currency->code,
            amountColumn: 'grp_net_amount',
            productIds: $productIds,
            breakdownLabel: $label,
            breakdownKeyByProduct: $keys,
            breakdownRows: $rows,
            audits: [
                self::audit('MasterProductCategory', [$category->id => $category->code]),
                self::audit('MasterAsset', $isFamily ? DB::table('master_assets')->whereIn('id', $masterAssetIds)->pluck('code', 'id')->all() : []),
                self::audit('ProductCategory', $shopCategories->pluck('id')->mapWithKeys(fn ($id) => [$id => $category->code])->all(), $shopCategories->pluck('shop_id', 'id')->all()),
                self::productAudit($productIds),
            ],
            offerTriggers: ['ProductCategory' => $shopCategories->pluck('id')->all(), 'Product' => $productIds],
            webpageShops: $shopCategories->filter(fn ($row) => $row->webpage_id)->pluck('shop_id', 'webpage_id')->all(),
            shopNodeStates: $shopCategories->pluck('state', 'shop_id')->all(),
        );
    }

    private static function buildMasterAsset(MasterAsset $masterAsset): self
    {
        $productIds = DB::table('products')->where('master_product_id', $masterAsset->id)->pluck('id')->all();

        return new self(
            currency: group()->currency->code,
            amountColumn: 'grp_net_amount',
            productIds: $productIds,
            audits: [self::audit('MasterAsset', [$masterAsset->id => $masterAsset->code]), self::productAudit($productIds)],
            offerTriggers: ['Product' => $productIds],
            webpageShops: self::productWebpages($productIds),
            shopNodeStates: DB::table('products')->whereIn('id', $productIds)->pluck('state', 'shop_id')->all(),
        );
    }

    private static function buildProductCategory(ProductCategory $category): self
    {
        $column = match ($category->type) {
            ProductCategoryTypeEnum::DEPARTMENT => 'department_id',
            ProductCategoryTypeEnum::SUB_DEPARTMENT => 'sub_department_id',
            default => 'family_id',
        };
        $productIds = DB::table('products')->where($column, $category->id)->pluck('id')->all();
        $isFamily   = $category->type === ProductCategoryTypeEnum::FAMILY;

        [$label, $keys, $rows] = $isFamily
            ? [__('Products'), array_combine($productIds, $productIds), self::rows('products', $productIds)]
            : [__('Families'), self::productColumn($productIds, 'family_id'), self::rows('product_categories', DB::table('products')->whereIn('id', $productIds)->whereNotNull('family_id')->distinct()->pluck('family_id')->all())];

        return new self(
            currency: $category->shop->currency->code,
            amountColumn: 'net_amount',
            productIds: $productIds,
            breakdownLabel: $label,
            breakdownKeyByProduct: $keys,
            breakdownRows: $rows,
            audits: [self::audit('ProductCategory', [$category->id => $category->code], [$category->id => $category->shop_id]), self::productAudit($productIds)],
            offerTriggers: ['ProductCategory' => [$category->id], 'Product' => $productIds],
            webpageShops: $category->webpage_id ? [$category->webpage_id => $category->shop_id] : [],
            shopNodeStates: [$category->shop_id => $category->state?->value],
        );
    }

    private static function buildProduct(Product $product): self
    {
        return new self(
            currency: $product->shop->currency->code,
            amountColumn: 'net_amount',
            productIds: [$product->id],
            audits: [self::productAudit([$product->id])],
            offerTriggers: ['Product' => [$product->id]],
            webpageShops: $product->webpage_id ? [$product->webpage_id => $product->shop_id] : [],
            shopNodeStates: [$product->shop_id => $product->state?->value],
        );
    }

    private static function buildTradeUnit(TradeUnit $tradeUnit): self
    {
        $productIds = self::tradeUnitProducts([$tradeUnit->id])->pluck('model_id')->unique()->values()->all();

        return new self(
            currency: group()->currency->code,
            amountColumn: 'grp_net_amount',
            productIds: $productIds,
            audits: [self::audit('TradeUnit', [$tradeUnit->id => $tradeUnit->code]), self::productAudit($productIds)],
            offerTriggers: ['Product' => $productIds],
            webpageShops: self::productWebpages($productIds),
        );
    }

    private static function buildTradeUnitFamily(TradeUnitFamily $tradeUnitFamily): self
    {
        $tradeUnitIds = DB::table('trade_units')->where('trade_unit_family_id', $tradeUnitFamily->id)->pluck('id')->all();
        $links        = self::tradeUnitProducts($tradeUnitIds);
        $productIds   = $links->pluck('model_id')->unique()->values()->all();

        return new self(
            currency: group()->currency->code,
            amountColumn: 'grp_net_amount',
            productIds: $productIds,
            breakdownLabel: __('Trade units'),
            breakdownKeyByProduct: $links->unique('model_id')->pluck('trade_unit_id', 'model_id')->all(),
            breakdownRows: self::rows('trade_units', $tradeUnitIds),
            audits: [
                self::audit('TradeUnitFamily', [$tradeUnitFamily->id => $tradeUnitFamily->code]),
                self::audit('TradeUnit', DB::table('trade_units')->whereIn('id', $tradeUnitIds)->pluck('code', 'id')->all()),
                self::productAudit($productIds),
            ],
            offerTriggers: ['Product' => $productIds],
            webpageShops: self::productWebpages($productIds),
        );
    }

    private static function buildStock(Stock $stock): self
    {
        $orgStockIds = DB::table('org_stocks')->where('stock_id', $stock->id)->pluck('id')->all();
        $productIds  = self::orgStockProducts($orgStockIds)->pluck('product_id')->unique()->values()->all();

        return new self(
            currency: group()->currency->code,
            amountColumn: 'grp_net_amount',
            productIds: $productIds,
            audits: [self::audit('Stock', [$stock->id => $stock->code]), self::productAudit($productIds)],
            offerTriggers: ['Product' => $productIds],
            webpageShops: self::productWebpages($productIds),
        );
    }

    private static function buildStockFamily(StockFamily $stockFamily): self
    {
        $stockIds = DB::table('stocks')->where('stock_family_id', $stockFamily->id)->pluck('id')->all();
        $links    = DB::table('product_has_org_stocks')
            ->join('org_stocks', 'org_stocks.id', 'product_has_org_stocks.org_stock_id')
            ->whereIn('org_stocks.stock_id', $stockIds)
            ->select(['product_has_org_stocks.product_id', 'org_stocks.stock_id'])
            ->get();
        $productIds = $links->pluck('product_id')->unique()->values()->all();

        return new self(
            currency: group()->currency->code,
            amountColumn: 'grp_net_amount',
            productIds: $productIds,
            breakdownLabel: __('Stocks'),
            breakdownKeyByProduct: $links->unique('product_id')->pluck('stock_id', 'product_id')->all(),
            breakdownRows: self::rows('stocks', $stockIds),
            audits: [
                self::audit('StockFamily', [$stockFamily->id => $stockFamily->code]),
                self::audit('Stock', DB::table('stocks')->whereIn('id', $stockIds)->pluck('code', 'id')->all()),
                self::productAudit($productIds),
            ],
            offerTriggers: ['Product' => $productIds],
            webpageShops: self::productWebpages($productIds),
        );
    }

    private static function buildOrgStock(OrgStock $orgStock): self
    {
        $productIds = self::orgStockProducts([$orgStock->id])->pluck('product_id')->unique()->values()->all();

        return new self(
            currency: $orgStock->organisation->currency->code,
            amountColumn: 'org_net_amount',
            productIds: $productIds,
            audits: [self::audit('OrgStock', [$orgStock->id => $orgStock->code]), self::productAudit($productIds)],
            offerTriggers: ['Product' => $productIds],
            webpageShops: self::productWebpages($productIds),
        );
    }

    private static function buildOrgStockFamily(OrgStockFamily $orgStockFamily): self
    {
        $orgStockIds = DB::table('org_stocks')->where('org_stock_family_id', $orgStockFamily->id)->pluck('id')->all();
        $links       = self::orgStockProducts($orgStockIds);
        $productIds  = $links->pluck('product_id')->unique()->values()->all();

        return new self(
            currency: $orgStockFamily->organisation->currency->code,
            amountColumn: 'org_net_amount',
            productIds: $productIds,
            breakdownLabel: __('SKOs'),
            breakdownKeyByProduct: $links->unique('product_id')->pluck('org_stock_id', 'product_id')->all(),
            breakdownRows: self::rows('org_stocks', $orgStockIds),
            audits: [
                self::audit('OrgStockFamily', [$orgStockFamily->id => $orgStockFamily->code]),
                self::audit('OrgStock', DB::table('org_stocks')->whereIn('id', $orgStockIds)->pluck('code', 'id')->all()),
                self::productAudit($productIds),
            ],
            offerTriggers: ['Product' => $productIds],
            webpageShops: self::productWebpages($productIds),
        );
    }

    /**
     * @return array{type: string, labels: array<int, string>, shops: array<int, int>|null}
     */
    private static function audit(string $type, array $labels, ?array $shops = null): array
    {
        return ['type' => $type, 'labels' => $labels, 'shops' => $shops];
    }

    private static function productAudit(array $productIds): array
    {
        $products = DB::table('products')->whereIn('id', $productIds)->select(['id', 'code', 'shop_id'])->get();

        return self::audit('Product', $products->pluck('code', 'id')->all(), $products->pluck('shop_id', 'id')->all());
    }

    private static function productColumn(array $productIds, string $column): array
    {
        return DB::table('products')->whereIn('id', $productIds)->pluck($column, 'id')->map(fn ($value) => (int)$value)->all();
    }

    private static function masterFamilyOfProducts(array $masterAssetOfProduct): array
    {
        $familyOfMasterAsset = DB::table('master_assets')->whereIn('id', array_unique($masterAssetOfProduct))->pluck('master_family_id', 'id');

        return array_map(fn ($masterAssetId) => (int)($familyOfMasterAsset[$masterAssetId] ?? 0), $masterAssetOfProduct);
    }

    private static function productWebpages(array $productIds): array
    {
        return DB::table('products')->whereIn('id', $productIds)->whereNotNull('webpage_id')->pluck('shop_id', 'webpage_id')->all();
    }

    private static function tradeUnitProducts(array $tradeUnitIds): Collection
    {
        return DB::table('model_has_trade_units')
            ->where('model_type', 'Product')
            ->whereIn('trade_unit_id', $tradeUnitIds)
            ->select(['model_id', 'trade_unit_id'])
            ->get();
    }

    private static function orgStockProducts(array $orgStockIds): Collection
    {
        return DB::table('product_has_org_stocks')->whereIn('org_stock_id', $orgStockIds)->select(['product_id', 'org_stock_id'])->get();
    }

    private static function rows(string $table, array $ids): array
    {
        $source = self::ROW_SOURCES[$table];

        return DB::table($table)
            ->whereIn('id', $ids)
            ->selectRaw("id, code, name, slug, ({$source['status']})::boolean as status, ({$source['for_sale']})::boolean as is_for_sale, created_at::date::text as created_at, ({$source['discontinued_at']})::date::text as discontinued_at")
            ->get()
            ->keyBy('id')
            ->map(fn ($row) => (array)$row)
            ->all();
    }
}
