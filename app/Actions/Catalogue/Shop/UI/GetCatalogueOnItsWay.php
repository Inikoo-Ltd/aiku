<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\UI;

use App\Actions\Catalogue\Product\GetProductIncomingStock;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Masters\MasterShop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Every stock on its way to the organisations behind a shop or master shop, earliest first, with
 * the products (or master products) it is sold as. A stock nothing in the catalogue uses yet is
 * listed with no products, so staff can spot new goods before they are in the catalogue.
 */
class GetCatalogueOnItsWay
{
    use AsObject;

    /**
     * @return array<int, array{key: string, code: string, name: string, quantity: float, eta: string|null, is_estimate: bool, products: array<int, array<string, mixed>>, lines: array<int, array<string, mixed>>}>
     */
    public function handle(Shop|MasterShop $parent): array
    {
        $organisations = $parent instanceof Shop
            ? collect([$parent->organisation])
            : Organisation::whereIn('id', Shop::where('master_shop_id', $parent->id)->select('organisation_id'))->get();

        $lines = $organisations->flatMap(fn (Organisation $organisation) => GetProductIncomingStock::make()->forOrganisation($organisation))
            ->sortBy(fn ($line) => [$line['eta'] === null, $line['eta']])
            ->values();

        if ($lines->isEmpty()) {
            return [];
        }

        $stockIdByOrgStock = DB::table('org_stocks')->whereIn('id', $lines->pluck('org_stock_id')->unique())->pluck('stock_id', 'id');
        $keyOf             = fn ($line) => ($stockId = $stockIdByOrgStock->get($line['org_stock_id'])) ? "stock:$stockId" : "org_stock:{$line['org_stock_id']}";
        $linesByKey        = $lines->groupBy($keyOf);

        $productsByKey = $parent instanceof Shop
            ? $this->shopProducts($parent, $lines->pluck('org_stock_id')->unique(), $stockIdByOrgStock)
            : $this->masterProducts($parent, $stockIdByOrgStock->filter()->unique());

        return $linesByKey->map(function (Collection $lines, string $key) use ($productsByKey) {
            $first = $lines->first();

            return [
                'key'         => $key,
                'code'        => $first['org_stock_code'],
                'name'        => $first['org_stock_name'],
                'quantity'    => round($lines->sum('quantity'), 3),
                'eta'         => $first['eta'],
                'is_estimate' => $first['is_estimate'],
                'products'    => $productsByKey->get($key, collect())->unique('slug')->values()->all(),
                'lines'       => $lines->map(fn ($line) => [
                    'type'              => $line['type'],
                    'reference'         => $line['reference'],
                    'supplier_name'     => $line['supplier_name'],
                    'supplier_code'     => $line['supplier_code'] ?: $line['supplier_name'],
                    'supplier_type'     => $line['supplier_type'],
                    'state_label'       => $line['state_label'],
                    'quantity'          => $line['quantity'],
                    'eta'               => $line['eta'],
                    'is_estimate'       => $line['is_estimate'],
                    'organisation_slug' => $line['organisation_slug'],
                ])->values()->all(),
            ];
        })->values()->all();
    }

    private function shopProducts(Shop $shop, Collection $orgStockIds, Collection $stockIdByOrgStock): Collection
    {
        return DB::table('product_has_org_stocks')
            ->join('products', 'products.id', 'product_has_org_stocks.product_id')
            ->where('products.shop_id', $shop->id)
            ->whereNull('products.deleted_at')
            ->whereIn('product_has_org_stocks.org_stock_id', $orgStockIds)
            ->get(['product_has_org_stocks.org_stock_id', 'products.slug', 'products.code', 'products.name', 'products.state'])
            ->groupBy(fn ($row) => ($stockId = $stockIdByOrgStock->get($row->org_stock_id)) ? "stock:$stockId" : "org_stock:$row->org_stock_id")
            ->map(fn (Collection $rows) => $rows->map(fn ($row) => [
                'slug'        => $row->slug,
                'code'        => $row->code,
                'name'        => $row->name,
                'state'       => $row->state,
                'state_label' => ProductStateEnum::from($row->state)->label(),
            ]));
    }

    private function masterProducts(MasterShop $masterShop, Collection $stockIds): Collection
    {
        return DB::table('master_asset_has_stocks')
            ->join('master_assets', 'master_assets.id', 'master_asset_has_stocks.master_asset_id')
            ->where('master_assets.master_shop_id', $masterShop->id)
            ->whereNull('master_assets.deleted_at')
            ->whereIn('master_asset_has_stocks.stock_id', $stockIds)
            ->get(['master_asset_has_stocks.stock_id', 'master_assets.slug', 'master_assets.code', 'master_assets.name', 'master_assets.status'])
            ->groupBy(fn ($row) => "stock:$row->stock_id")
            ->map(fn (Collection $rows) => $rows->map(fn ($row) => [
                'slug'        => $row->slug,
                'code'        => $row->code,
                'name'        => $row->name,
                'state'       => $row->status ? 'active' : 'inactive',
                'state_label' => $row->status ? __('Active') : __('Inactive'),
            ]));
    }
}
