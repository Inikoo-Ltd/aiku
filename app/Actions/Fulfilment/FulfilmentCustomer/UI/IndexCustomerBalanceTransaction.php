<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 03-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Fulfilment\FulfilmentCustomer\UI;

use App\Actions\Fulfilment\Fulfilment\UI\ShowFulfilment;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithFulfilmentShopAuthorisation;
use App\Actions\Traits\WithFulfilmentCustomersSubNavigation;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\CreditTransaction;
use App\Models\CRM\Customer;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class IndexCustomerBalanceTransaction extends OrgAction
{
    // use WithFulfilmentShopAuthorisation;
    // use WithFulfilmentCustomersSubNavigation;


    public function handle(Customer $customer, $prefix = null): LengthAwarePaginator
    {
        // $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
        //     $query->where(function ($query) use ($value) {
        //         $query->whereAnyWordStartWith('customers.name', $value)
        //             ->orWhereStartWith('customers.email', $value)
        //             ->orWhere('customers.reference', '=', $value);
        //     });
        // });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(CreditTransaction::class);

        $queryBuilder->where('customer_id', $customer->id);

        $queryBuilder->leftJoin('currencies', 'currencies.id', 'credit_transactions.currency_id');

        return $queryBuilder
            ->defaultSort('-date')
            ->select([
                'type',
                'notes',
                'date',
                'amount',
                'running_amount',
                'currencies.code as currency_code',
            ])
            ->allowedSorts(['type', 'notes', 'date', 'amount', 'running_amount'])
            // ->allowedFilters([$globalSearch])
            ->withPaginator(prefix: $prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Customer $customer, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($customer, $modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }


            $table
                ->withModelOperations($modelOperations)
                ->defaultSort('-date')
                ->column(key: 'type', label: __('Transaction type'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'notes', label: __('Notes'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'date', label: __('Date'), canBeHidden: false, sortable: true, searchable: true, align: 'right')
                ->column(key: 'amount', label: __('Amount'), canBeHidden: false, sortable: true, searchable: true, align: 'right')
                ->column(key: 'running_amount', label: __('Running balance'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
        };
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Customers'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ];
        };


        return array_merge(
            ShowFulfilment::make()->getBreadcrumbs(
                $routeParameters
            ),
            $headCrumb(
                [
                    'name'       => 'grp.org.fulfilments.show.crm.customers.index',
                    'parameters' => $routeParameters
                ]
            )
        );
    }
}
