<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 17 Dec 2025 02:42:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

/*
 * Author: Junie (JetBrains AI)
 * Created: Wed, 17 Dec 2025 02:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 */

namespace App\Actions\Goods\TradeUnit\UI\Traits;

use App\InertiaTable\InertiaTable;
use App\Models\Goods\TradeUnit;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

trait WithTradeUnitIndex
{
    protected function tradeUnitGlobalSearch(): AllowedFilter
    {
        return AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('trade_units.code', $value)
                    ->orWhereAnyWordStartWith('trade_units.name', $value);
            });
        });
    }

    protected function updateQueryBuilderParametersIfPrefixed($prefix): void
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }
    }

    protected function baseTradeUnitIndexBuilder(): QueryBuilder
    {
        return QueryBuilder::for(TradeUnit::class);
    }

    protected function finalizeTradeUnitIndex(
        QueryBuilder $queryBuilder,
        array $allowedSorts,
        AllowedFilter $globalSearch,
        $prefix
    ): LengthAwarePaginator {
        return $queryBuilder
            ->allowedSorts($allowedSorts)
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    protected function setupTradeUnitTable(
        InertiaTable $table,
        ?array $modelOperations = null,
        $prefix = null,
        bool $withLabelRecord = true,
        ?array $emptyState = null
    ): InertiaTable {
        if ($prefix) {
            $table->name($prefix)->pageName($prefix.'Page');
        }

        $table->defaultSort('code')
            ->withGlobalSearch()
            ->withModelOperations($modelOperations);

        if ($withLabelRecord) {
            $table->withLabelRecord([__('trade unit'), __('trade units')]);
        }

        if ($emptyState) {
            $table->withEmptyState($emptyState);
        }

        return $table;
    }

    protected function addColumnCodeAndName(InertiaTable $table): void
    {
        $table
            ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
            ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);
    }

    protected function addColumnType(InertiaTable $table, string $label = 'Type'): void
    {
        $table->column(key: 'type', label: __($label), canBeHidden: false, sortable: true, searchable: true);
    }

    protected function addColumnNetWeight(InertiaTable $table, string $label = 'Weight'): void
    {
        $table->column(key: 'net_weight', label: __($label), canBeHidden: false, sortable: true, searchable: true);
    }

    protected function addColumnQuantity(
        InertiaTable $table,
        string $label = 'Quantity',
        bool $sortable = false,
        bool $searchable = false,
        ?string $align = 'right'
    ): void {
        $table->column(key: 'quantity', label: __($label), canBeHidden: false, sortable: $sortable, searchable: $searchable, align: $align);
    }

    protected function addColumnQuantityPlain(
        InertiaTable $table,
        string $label = 'quantity',
        bool $sortable = true,
        bool $searchable = true
    ): void {
        $table->column(key: 'quantity', label: __($label), canBeHidden: false, sortable: $sortable, searchable: $searchable);
    }

    protected function addColumnStatusAvatar(InertiaTable $table): void
    {
        $table->column(key: 'status', label: '', icon: 'fal fa-yin-yang', canBeHidden: false, sortable: true, type: 'avatar');
    }

    protected function addColumnNumberCurrentProducts(InertiaTable $table): void
    {
        $table->column(key: 'number_current_products', label: __('Products'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
    }

    protected function addColumnNumberCurrentStocks(InertiaTable $table): void
    {
        $table->column(key: 'number_current_stocks', label: __('SKUs'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
    }

    protected function addColumnMarketingWeight(InertiaTable $table): void
    {
        $table->column(key: 'marketing_weight', label: __('Weight').' ('.__('Marketing').')', canBeHidden: false, sortable: true, searchable: true, align: 'right');
    }
}
