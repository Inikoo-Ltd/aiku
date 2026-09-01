<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 15 Oct 2025 16:46:12 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Portfolio\Logs;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\PlatformPortfolioLogs;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class IndexPlatformPortfolioLogs extends OrgAction
{
    public function handle(CustomerSalesChannel $customerSalesChannel, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereWith('portfolios.item_code', $value)
                    ->orWhereWith('platform_portfolio_logs.type', $value)
                    ->orWhereWith('platform_portfolio_logs.status', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(PlatformPortfolioLogs::class);
        $query->where('platform_portfolio_logs.customer_sales_channel_id', $customerSalesChannel->id);

        $query->leftJoin('portfolios', 'portfolios.id', 'platform_portfolio_logs.portfolio_id');
        $query->leftJoin('platforms', 'platforms.id', 'platform_portfolio_logs.platform_id');

        return $query
            ->select([
                'platform_portfolio_logs.id',
                'platform_portfolio_logs.created_at',
                'platform_portfolio_logs.type',
                'platform_portfolio_logs.status',
                'platform_portfolio_logs.response',
                'platform_portfolio_logs.platform_id',
                'platform_portfolio_logs.platform_type',
                'platform_portfolio_logs.portfolio_id',
                'portfolios.item_code',
                'platforms.name as platform_name',
            ])
            ->defaultSort('-platform_portfolio_logs.created_at')
            ->allowedSorts(['created_at', 'type', 'status', 'item_code'])
            ->allowedFilters([$globalSearch, 'created_at', 'type', 'status', 'item_code'])
            ->withPaginator($prefix, tableName: request()->route()?->getName())
            ->withQueryString();
    }

    public function tableStructure(?array $modelOperations = null, $prefix = null): Closure
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
                ->column(key: 'item_code', label: __('Product Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'type', label: __('Type'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'platform_name', label: __('Platform'), canBeHidden: false, sortable: false, searchable: true)
                ->column(key: 'status', label: __('Status'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'response', label: __('Response'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'created_at', label: __('Date'), canBeHidden: false, sortable: true, searchable: false);
        };
    }

}
