<?php

namespace App\Actions\Catalogue\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Asset;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;

class IndexTopListedProducts extends OrgAction
{
    public function handle(Group|Shop $parent, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('assets.name', $value)
                    ->orWhereStartWith('assets.code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = filled(request()->input('between')) ? $this->liveQuery($parent) : $this->rankedQuery($parent);

        return $query
            ->allowedSorts(['total_listed', 'total_customers', 'assets.name', 'assets.slug', 'assets.code'])
            ->allowedFilters([$globalSearch])
            ->withBetweenDates(['created_at'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    private function rankedQuery(Group|Shop $parent): QueryBuilder
    {
        $totals = DB::table('catalogue_top_listed_products')
            ->when($parent instanceof Shop, fn ($query) => $query->where('shop_id', $parent->id))
            ->groupBy('asset_id')
            ->select('asset_id', DB::raw('SUM(total_listed)::bigint as total_listed'), DB::raw('SUM(total_customers)::bigint as total_customers'));

        return QueryBuilder::for(Asset::withTrashed())
            ->joinSub($totals, 'rankings', 'rankings.asset_id', '=', 'assets.id')
            ->select('assets.id', 'assets.slug', 'assets.code', 'assets.name', 'rankings.total_listed', 'rankings.total_customers')
            ->orderByDesc('total_listed');
    }

    private function liveQuery(Group|Shop $parent): QueryBuilder
    {
        $query = QueryBuilder::for(\App\Models\Dropshipping\Portfolio::class)
            ->select(
                'assets.id',
                'assets.slug',
                'assets.code',
                'assets.name',
                DB::raw('COUNT(portfolios.id) as total_listed'),
                DB::raw('COUNT(DISTINCT portfolios.customer_id) as total_customers')
            )
            ->join('products', function ($join) {
                $join->on('portfolios.item_id', '=', 'products.id')
                    ->where('portfolios.item_type', '=', 'Product');
            })
            ->join('assets', 'assets.id', '=', 'products.asset_id')
            ->where('assets.type', 'product')
            ->whereNull('portfolios.last_removed_at')
            ->groupBy('assets.id', 'assets.slug', 'assets.code', 'assets.name')
            ->orderByDesc('total_listed');

        if ($parent instanceof Shop) {
            $query->where('portfolios.shop_id', $parent->id);
        }

        return $query;
    }

    public function tableStructure(?array $modelOperations = null, ?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch()
                ->withEmptyState([
                    'title'       => __('No top listed products found'),
                    'description' => __('There are no products currently listed on this platform.'),
                ])
                ->betweenDates(['created_at'])
                ->column(key: 'slug', label: __('Slug'), sortable: true, searchable: true)
                ->column(key: 'code', label: __('Code'), sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), sortable: true, searchable: true)
                ->column(key: 'total_customers', label: __('Customers'), sortable: true)
                ->column(key: 'total_listed', label: __('Total Listed'), sortable: true)
                ->defaultSort('-total_listed');
        };
    }
}
