<?php

/*
 * author Louis Perez
 * created on 17-04-2026-15h-34m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Fulfilment\PalletReturn\UI;

use App\Actions\OrgAction;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Fulfilment\RecurringBill;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;

class IndexPalletReturnsBacklog extends OrgAction
{
    public function handle(Fulfilment|FulfilmentCustomer|RecurringBill $parent, PalletReturnStateEnum|null $stateFilter = null, PalletReturnTypeEnum|null $typeFilter = null, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('pallet_returns.reference', $value)
                    ->orWhereStartWith('pallet_returns.customer_reference', $value)
                    ->orWhereStartWith('customers.name', $value)
                    ->orWhereStartWith('pallet_returns.slug', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(PalletReturn::class);
        $queryBuilder
            ->leftJoin('pallet_return_stats', 'pallet_return_stats.pallet_return_id', '=', 'pallet_returns.id')
            ->leftJoin('currencies', 'currencies.id', '=', 'pallet_returns.currency_id')
            ->leftJoin('fulfilment_customers', 'fulfilment_customers.id', 'pallet_returns.fulfilment_customer_id')
            ->leftJoin('customers', 'customers.id', 'fulfilment_customers.customer_id');

        if ($parent instanceof Fulfilment) {
            $queryBuilder->where('pallet_returns.fulfilment_id', $parent->id);
        } elseif ($parent instanceof RecurringBill) {
            $queryBuilder->where('pallet_returns.recurring_bill_id', $parent->id);
        } else {
            $queryBuilder->where('pallet_returns.fulfilment_customer_id', $parent->id);
        }

        if ($stateFilter) {
            $queryBuilder->where('pallet_returns.state', $stateFilter->value);
        }

        if ($typeFilter) {
            $queryBuilder->wherE('pallet_returns.type', $typeFilter->value);
        }

        $queryBuilder->defaultSort('-date');

        return $queryBuilder
            ->select(
                'pallet_returns.id',
                'pallet_returns.state',
                'pallet_returns.slug',
                'pallet_returns.reference',
                'fulfilment_customers.slug as cust_slug',
                'customers.name as cust_name',
                'pallet_returns.customer_reference',
                'pallet_return_stats.number_pallets as number_pallets',
                'pallet_return_stats.number_services as number_services',
                'pallet_returns.created_at as date',
                'pallet_returns.dispatched_at',
                'pallet_returns.type',
                'pallet_returns.total_amount',
                'currencies.code as currency_code',
                DB::raw(
                    "(
                    SELECT COUNT(DISTINCT stored_item_id)
                    FROM pallet_return_items
                    WHERE pallet_return_items.pallet_return_id = pallet_returns.id
                ) as unique_stored_item_count"
                )
            )
            ->allowedSorts(['reference', 'customer_reference', 'number_pallets', 'date', 'state', 'cust_name', 'unique_stored_item_count'])
            ->allowedFilters([$globalSearch, 'type'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Fulfilment|FulfilmentCustomer|RecurringBill $parent, PalletReturnTypeEnum|null $typeFilter = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $typeFilter, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    match (class_basename($parent)) {
                        'Fulfilment' => [
                            'title' => __('No pallet returns found for this shop'),
                            'count' => $parent->stats->number_pallet_returns
                        ],
                        'RecurringBill' => [
                            'title'       => __('No pallet returns found for this recurring bill'),
                            'description' => __('This recurring bill has no any pallet returns yet'),
                            'count'       => $parent->stats->number_pallet_returns
                        ],
                        'FulfilmentCustomer' => [
                            'title'       => __('No pallet returns found for this customer'),
                            'description' => __('This customer has not received any pallet returns yet'),
                            'count'       => $parent->number_pallet_returns
                        ]
                    }
                )
                ->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon')
                ->column(key: 'reference', label: __('Reference'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'date', label: __('Created date'), canBeHidden: false, sortable: true, searchable: true, align: 'right')
                ->column(key: 'cust_name', label: __('Customer'), canBeHidden: false, sortable: true, searchable: true);

            if ($typeFilter === PalletReturnTypeEnum::STORED_ITEM) {
                $table
                    ->column(key: 'unique_stored_item_count', label: __('SKU'), canBeHidden: false, sortable: true, searchable: true);
            } else {
                $table
                    ->column(key: 'number_pallets', label: __('Pallets'), canBeHidden: false, sortable: true, searchable: true);
            }

            $table
                ->column(key: 'customer_reference', label: __('Customer reference'), canBeHidden: false, sortable: true, searchable: true);
        };
    }
}
