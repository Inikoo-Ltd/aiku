<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 05 Aug 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Inventory\OrgStock\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Inventory\OrgStock;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;

class OrgStockHydrateSkuCommercialValue implements ShouldBeUnique
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:org-stock-sku-commercial-value {organisations?*} {--s|slugs=}';

    public function __construct()
    {
        $this->model = OrgStock::class;
    }

    public function getJobUniqueId(OrgStock $orgStock): string
    {
        return $orgStock->id;
    }

    public function handle(OrgStock $orgStock): void
    {
        $commercialValue = $this->getCommercialValuePerSku($orgStock);

        if ($commercialValue <= 0) {
            return;
        }

        $orgStock->update(['sku_commercial_value' => $commercialValue]);

        if ($orgStock->wasChanged('sku_commercial_value')) {
            OrgStockHydrateStockValue::dispatch($orgStock);
        }
    }

    protected function getCommercialValuePerSku(OrgStock $orgStock): float
    {
        $singleStockProducts = DB::table('product_has_org_stocks')
            ->select('product_id')
            ->groupBy('product_id')
            ->havingRaw('count(*) = 1');

        $priceInOrgCurrency = 'products.price / product_has_org_stocks.quantity * shop_exchange.exchange';

        return (float) DB::connection('aiku_no_sticky')
            ->table('product_has_org_stocks')
            ->joinSub($singleStockProducts, 'single_stock_products', 'single_stock_products.product_id', '=', 'product_has_org_stocks.product_id')
            ->join('products', 'products.id', '=', 'product_has_org_stocks.product_id')
            ->joinSub($this->shopExchangeSubQuery(), 'shop_exchange', 'shop_exchange.shop_id', '=', 'products.shop_id')
            ->where('product_has_org_stocks.org_stock_id', $orgStock->id)
            ->where('product_has_org_stocks.quantity', '>', 0)
            ->where('products.price', '>', 0)
            ->where('products.is_for_sale', true)
            ->whereNull('products.deleted_at')
            ->avg(DB::raw($priceInOrgCurrency)) ?? 0;
    }

    protected function shopExchangeSubQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('invoice_transactions')
            ->select('shop_id')
            ->selectRaw('avg(org_exchange) as exchange')
            ->where('org_exchange', '>', 0)
            ->where('date', '>=', now()->subYear())
            ->groupBy('shop_id');
    }
}
