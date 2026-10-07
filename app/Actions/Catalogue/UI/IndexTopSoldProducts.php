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

class IndexTopSoldProducts extends OrgAction
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
            ->allowedSorts(['total_sold', 'total_amount', 'assets.name', 'assets.slug', 'assets.code'])
            ->allowedFilters([$globalSearch])
            ->withBetweenDates(['date'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    private function rankedQuery(Group|Shop $parent): QueryBuilder
    {
        $totals = DB::table('catalogue_top_sold_products')
            ->when($parent instanceof Shop, fn ($query) => $query->where('shop_id', $parent->id))
            ->groupBy('asset_id')
            ->select(
                'asset_id',
                DB::raw('SUM(total_sold) as total_sold'),
                DB::raw('SUM('.($parent instanceof Group ? 'total_grp_amount' : 'total_amount').') as total_amount')
            );

        return QueryBuilder::for(Asset::withTrashed())
            ->joinSub($totals, 'rankings', 'rankings.asset_id', '=', 'assets.id')
            ->select(
                'assets.id',
                'assets.slug',
                'assets.code',
                'assets.name',
                'rankings.total_sold',
                'rankings.total_amount',
                DB::raw("'".$parent->currency->code."' as currency_code")
            )
            ->orderByDesc('total_sold');
    }

    private function liveQuery(Group|Shop $parent): QueryBuilder
    {
        $query = QueryBuilder::for(\App\Models\Accounting\InvoiceTransaction::class)
            ->select(
                'assets.id',
                'assets.slug',
                'assets.code',
                'assets.name',
                DB::raw('SUM(invoice_transactions.quantity) as total_sold'),
                DB::raw('SUM(invoice_transactions.'.($parent instanceof Group ? 'grp_net_amount' : 'net_amount').') as total_amount'),
                DB::raw("'" . $parent->currency->code . "' as currency_code")
            )
            ->join('assets', function ($join) {
                $join->on('invoice_transactions.asset_id', '=', 'assets.id')
                    ->where('invoice_transactions.model_type', '=', 'Product');
            })
            ->join('invoices', 'invoice_transactions.invoice_id', '=', 'invoices.id')
            ->where('assets.type', 'product')
            ->whereNull('invoice_transactions.deleted_at')
            ->where('invoice_transactions.in_process', false)
            ->groupBy('assets.id', 'assets.slug', 'assets.code', 'assets.name')
            ->orderByDesc('total_sold');

        if ($parent instanceof Shop) {
            $query->where('invoice_transactions.shop_id', $parent->id);
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
                    'title'       => __('No top sold products found'),
                    'description' => __('There are no sales recorded for products on this platform.'),
                ])
                ->betweenDates(['date'])
                ->column(key: 'slug', label: __('Slug'), sortable: true, searchable: true)
                ->column(key: 'code', label: __('Code'), sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), sortable: true, searchable: true)
                ->column(key: 'total_sold', label: __('Total Sold'), sortable: true)
                ->column(key: 'total_amount', label: __('Total Amount'), sortable: true)
                ->defaultSort('-total_sold');
        };
    }
}
