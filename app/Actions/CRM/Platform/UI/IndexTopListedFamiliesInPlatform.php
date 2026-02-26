<?php

namespace App\Actions\CRM\Platform\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\Platform;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;

class IndexTopListedFamiliesInPlatform extends OrgAction
{
    public function handle(Group|Shop $parent, Platform $platform, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('families.name', $value)
                    ->orWhereStartWith('families.code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(\App\Models\Dropshipping\Portfolio::class)
            ->select(
                'families.id',
                'families.code',
                'families.name',
                DB::raw('COUNT(portfolios.id) as total_listed'),
                DB::raw('COUNT(DISTINCT portfolios.customer_id) as total_customers')
            )
            // portfolios -> assets (via item_id)
            ->join('assets', function ($join) {
                $join->on('portfolios.item_id', '=', 'assets.id')
                    ->where('portfolios.item_type', '=', 'Product');
            })
            // assets -> products (via product.asset_id = assets.id)
            ->join('products', 'products.asset_id', '=', 'assets.id')
            // products -> product_categories/families (via products.family_id)
            ->join('product_categories as families', 'families.id', '=', 'products.family_id')
            ->where('portfolios.platform_id', $platform->id)
            ->where('assets.type', 'product')
            ->whereNull('portfolios.last_removed_at')
            ->whereNotNull('products.family_id') // pastikan ada family-nya
            ->groupBy('families.id', 'families.code', 'families.name')
            ->orderByDesc('total_listed');

        if ($parent instanceof Shop) {
            $query->where('portfolios.shop_id', $parent->id);
        }

        return $query
            ->allowedSorts(['total_listed', 'total_customers', 'families.name', 'families.code'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
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
                    'title'       => __('No top listed families found'),
                    'description' => __('There are no product families currently listed on this platform.'),
                ])
                ->column(key: 'code', label: __('Code'), sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), sortable: true, searchable: true)
                ->column(key: 'total_customers', label: __('Customers'), sortable: true)
                ->column(key: 'total_listed', label: __('Total Listed'), sortable: true)
                ->defaultSort('-total_listed');
        };
    }
}
