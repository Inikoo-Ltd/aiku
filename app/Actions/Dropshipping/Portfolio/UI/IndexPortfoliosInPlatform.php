<?php

/*
 * author Arya Permana - Kirin
 * created on 11-04-2025-10h-58m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Portfolio\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\Portfolio;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class IndexPortfoliosInPlatform extends OrgAction
{
    public function handle(Shop $shop, Platform $platform, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('portfolios.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(Portfolio::class);
        $query->where('portfolios.platform_id', $platform->id);
        $query->where('portfolios.shop_id', $shop->id);
        $query->where('portfolios.status', true);

        $query->leftJoin('customers', 'customers.id', 'portfolios.customer_id');
        $query->leftJoin('platforms', 'platforms.id', 'portfolios.platform_id');

        return $query
            ->select([
                'portfolios.id',
                'portfolios.reference',
                'portfolios.created_at',
                'portfolios.item_name',
                'portfolios.item_code',
                'portfolios.item_type',
                'portfolios.platform_product_id',
                'portfolios.item_id',
                'portfolios.customer_sales_channel_id',
            ])
            ->defaultSort('portfolios.reference')
            ->allowedSorts(['reference', 'created_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }
            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch()
                ->column(key: 'item_code', label: __('product'), canBeHidden: false, searchable: true)
                ->column(key: 'item_name', label: __('product name'), canBeHidden: false, searchable: true)
                ->column(key: 'platform_status', label: __('Status'), canBeHidden: false, sortable: true, searchable: true);
        };
    }
}
