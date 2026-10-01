<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\Competitor\UI;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersAuthorisation;
use App\Enums\Masters\Competitor\CompetitorSellsToEnum;
use App\Enums\Masters\Competitor\MasterAssetCompetitorProductStatusEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Helpers\Currency;
use App\Models\Masters\MasterAssetCompetitorProduct;
use App\Models\Masters\MasterShop;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Our products next to what competitors charge, both per unit in the group currency. Shoppers'
 * prices are set against our RRP, everyone else's against our price.
 */
class IndexMasterAssetCompetitorProducts extends OrgAction
{
    use WithMastersAuthorisation;

    public function handle(MasterShop $masterShop, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('master_assets.code', $value)
                    ->orWhereAnyWordStartWith('master_assets.name', $value)
                    ->orWhereAnyWordStartWith('competitor_products.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $rows = QueryBuilder::for(MasterAssetCompetitorProduct::class)
            ->where('master_asset_competitor_products.master_shop_id', $masterShop->id)
            ->where(fn ($query) => $query->where('master_asset_competitor_products.status', '!=', MasterAssetCompetitorProductStatusEnum::REJECTED)
                ->orWhereNotNull('master_asset_competitor_products.reviewed_by_user_id'))
            ->join('master_assets', 'master_assets.id', '=', 'master_asset_competitor_products.master_asset_id')
            ->join('competitor_products', 'competitor_products.id', '=', 'master_asset_competitor_products.competitor_product_id')
            ->join('competitors', 'competitors.id', '=', 'competitor_products.competitor_id')
            ->select([
                'master_asset_competitor_products.id',
                'master_asset_competitor_products.status',
                'master_asset_competitor_products.is_same_item',
                'master_asset_competitor_products.confidence',
                'master_asset_competitor_products.reviewed_by_user_id',
                'competitor_products.image_url as competitor_image_url',
                'competitor_products.rrp as competitor_rrp',
                'master_assets.code',
                'master_assets.slug',
                'master_assets.name',
                'master_assets.units',
                'master_assets.price',
                'master_assets.rrp',
                'competitors.name as competitor_name',
                'competitors.sells_to',
                'competitors.currency_id',
                'competitor_products.name as competitor_product_name',
                'competitor_products.url as competitor_product_url',
                'competitor_products.price as competitor_price',
                'competitor_products.units as competitor_units',
                'competitor_products.minimum_order',
                'competitor_products.fetched_at',
            ])
            ->allowedFilters([$globalSearch])
            ->defaultSort('-is_same_item')
            ->allowedSorts(['code', 'competitor_name', 'status', 'is_same_item'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();

        $groupCurrency = $masterShop->group->currency;
        $exchanges     = [];

        $rows->getCollection()->each(function (MasterAssetCompetitorProduct $row) use ($groupCurrency, &$exchanges) {
            $exchanges[$row->currency_id] ??= $row->currency_id === $groupCurrency->id ? 1.0 : GetCurrencyExchange::run(Currency::find($row->currency_id), $groupCurrency);

            $ours   = ($row->sells_to === CompetitorSellsToEnum::CONSUMER->value ? $row->rrp : $row->price) / max((float) $row->units, 1);
            $theirs = $row->competitor_price !== null && $exchanges[$row->currency_id]
                ? $row->competitor_price * $exchanges[$row->currency_id] / max((float) $row->competitor_units, 1)
                : null;

            $row->our_unit_price        = $ours ? round($ours, 2) : null;
            $row->competitor_unit_price = $theirs !== null ? round($theirs, 2) : null;
            $row->difference            = $ours && $theirs ? (int) round(100 * ($theirs / $ours - 1)) : null;
            $row->currency_code         = $groupCurrency->code;
        });

        return $rows;
    }

    public function tableStructure($prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState([
                    'title'       => __('No competitor prices yet'),
                    'description' => __('Competitor websites are searched every week for the products with a price tip'),
                ])
                ->column(key: 'code', label: __('Product'), sortable: true, searchable: true)
                ->column(key: 'our_unit_price', label: __('Ours / unit'), align: 'right')
                ->column(key: 'competitor_name', label: __('Competitor'), sortable: true)
                ->column(key: 'competitor_product_name', label: __('Their product'))
                ->column(key: 'competitor_unit_price', label: __('Theirs / unit'), align: 'right')
                ->column(key: 'difference', label: __('Difference'), align: 'right')
                ->column(key: 'status', label: __('Right match?'), sortable: true)
                ->defaultSort('-is_same_item');
        };
    }
}
