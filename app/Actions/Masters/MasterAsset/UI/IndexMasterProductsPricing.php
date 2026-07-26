<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 26 Jul 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset\UI;

use App\Actions\Masters\MasterAsset\Json\GetMasterProductsPricingSales;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersAuthorisation;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use App\Services\QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Pricing tab of the master products index: lean listing scoped to a master product
 * category, feeding the bulk price editing UI.
 */
class IndexMasterProductsPricing extends OrgAction
{
    use AsObject;
    use WithMastersAuthorisation;

    public function handle(MasterProductCategory $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('master_assets.code', $value)
                    ->orWhereStartWith('master_assets.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(MasterAsset::class)
            ->leftJoin('groups', 'master_assets.group_id', '=', 'groups.id')
            ->leftJoin('currencies', 'groups.currency_id', '=', 'currencies.id')
            ->leftJoin('master_asset_stats', 'master_asset_stats.master_asset_id', '=', 'master_assets.id')
            ->where('master_assets.status', true)
            ->where('master_assets.is_main', true)
            ->where(
                match ($parent->type) {
                    MasterProductCategoryTypeEnum::FAMILY     => 'master_assets.master_family_id',
                    MasterProductCategoryTypeEnum::DEPARTMENT => 'master_assets.master_department_id',
                    default                                   => 'master_assets.master_sub_department_id',
                },
                $parent->id
            )
            ->select([
                'master_assets.id',
                'master_assets.code',
                'master_assets.name',
                'master_assets.slug',
                'master_assets.units',
                'master_assets.unit',
                'master_assets.web_images',
                'master_assets.price',
                'master_assets.rrp',
                'master_assets.master_prices',
                'master_assets.master_rrps',
                'master_assets.units_review',
                'currencies.code as currency_code',
                'master_asset_stats.number_current_assets as used_in',
                'master_asset_stats.number_customers_who_favourited as favourites',
                'master_asset_stats.total_products_rebel_prices as price_rebels',
            ])
            ->leftJoinLateral(
                DB::query()
                    ->fromSub(
                        DB::table('master_asset_has_stocks')
                            ->join('org_stocks', 'org_stocks.stock_id', '=', 'master_asset_has_stocks.stock_id')
                            ->join('organisations', 'organisations.id', '=', 'org_stocks.organisation_id')
                            ->whereColumn('master_asset_has_stocks.master_asset_id', 'master_assets.id')
                            ->groupBy('org_stocks.organisation_id', 'organisations.code')
                            ->select('org_stocks.organisation_id', 'organisations.code')
                            ->selectRaw('sum(org_stocks.quantity_available) as qty'),
                        'per_org'
                    )
                    ->selectRaw("min(per_org.qty) filter (where per_org.qty > 0) as stock_min, max(per_org.qty) filter (where per_org.qty > 0) as stock_max, count(*) filter (where per_org.qty <= 0) as orgs_out_of_stock, count(*) filter (where per_org.qty > 0) as orgs_with_stock, jsonb_agg(jsonb_build_object('code', per_org.code, 'qty', per_org.qty) order by per_org.qty desc) as stock_by_org"),
                'stock_per_org'
            )
            ->addSelect([
                'stock_per_org.stock_min',
                'stock_per_org.stock_max',
                'stock_per_org.orgs_out_of_stock',
                'stock_per_org.orgs_with_stock',
                'stock_per_org.stock_by_org',
            ])
            ->selectSub(
                "select string_agg(trade_units.code || ' ×' || trim_scale(model_has_trade_units.quantity), ', ' order by trade_units.code)
                 from model_has_trade_units
                 join trade_units on trade_units.id = model_has_trade_units.trade_unit_id
                 where model_has_trade_units.model_type = 'MasterAsset'
                   and model_has_trade_units.model_id = master_assets.id",
                'trade_units_label'
            )
            ->defaultSort('code')
            ->allowedSorts(['code', 'name', 'price', 'rrp'])
            ->allowedFilters([$globalSearch]);

        $masterAssets = $queryBuilder
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();

        $salesFigures = GetMasterProductsPricingSales::make()->handle(
            $masterAssets->getCollection()->pluck('id')->all(),
            'year'
        );

        $masterAssets->getCollection()->transform(function (MasterAsset $masterAsset) use ($salesFigures) {
            $figures = $salesFigures[$masterAsset->id] ?? null;

            $masterAsset->sales     = $figures->sales ?? null;
            $masterAsset->sold      = $figures->sold ?? null;
            $masterAsset->customers = $figures->customers ?? null;
            $masterAsset->sales_ly  = $figures->sales_ly ?? null;

            return $masterAsset;
        });

        return $masterAssets;
    }

    public function tableStructure(MasterProductCategory $parent, $prefix = null): \Closure
    {
        return function (InertiaTable $table) use ($parent, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title' => __('No master products found'),
                        'count' => $parent->stats->number_current_master_assets,
                    ],
                )
                ->column(key: 'code', label: __('Code'), sortable: true, searchable: true)
                ->column(key: 'name', label: __('Info'), sortable: true, searchable: true)
                ->column(key: 'price', label: __('Price'), sortable: true, align: 'right')
                ->column(key: 'rrp', label: __('RRP').'/'.__('Unit'), sortable: true, align: 'right')
                ->defaultSort('code');
        };
    }
}
