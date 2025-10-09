<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 16:54:01 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\RecurringBill\UI;

use App\Actions\Fulfilment\Fulfilment\UI\ShowFulfilment;
use App\Actions\Fulfilment\FulfilmentCustomer\ShowFulfilmentCustomer;
use App\Actions\Fulfilment\RecurringBill\WithRecurringBillsSubNavigation;
use App\Actions\Fulfilment\WithFulfilmentCustomerSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithFulfilmentShopAuthorisation;
use App\Enums\Fulfilment\RecurringBill\RecurringBillStatusEnum;
use App\Http\Resources\Fulfilment\RecurringBillsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\RecurringBill;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexRecurringBills extends OrgAction
{
    use WithFulfilmentCustomerSubNavigation;
    use WithRecurringBillsSubNavigation;
    use WithFulfilmentShopAuthorisation;

    private Fulfilment|FulfilmentCustomer $parent;
    private string $bucket;



    public function asController(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $fulfilment;
        $this->bucket = 'all';
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle(parent: $fulfilment, bucket: $this->bucket);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function current(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $fulfilment;
        $this->bucket = 'current';
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle(parent: $fulfilment, bucket: $this->bucket);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function former(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $fulfilment;
        $this->bucket = 'former';
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle(parent: $fulfilment, bucket: $this->bucket);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilmentCustomer(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $fulfilmentCustomer;
        $this->bucket = 'all';
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle(parent: $fulfilmentCustomer, bucket: $this->bucket);
    }

    public function handle(Fulfilment|FulfilmentCustomer $parent, $prefix = null, $bucket = null): LengthAwarePaginator
    {

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('recurring_bills.reference', $value)
                    ->orWhereStartWith('recurring_bills.slug', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(RecurringBill::class);

        if ($parent instanceof FulfilmentCustomer) {
            $queryBuilder->where('recurring_bills.fulfilment_customer_id', $parent->id);
        } elseif ($bucket == 'current') {
            $queryBuilder->where('recurring_bills.fulfilment_id', $parent->id)
                ->where('recurring_bills.status', RecurringBillStatusEnum::CURRENT);
        } elseif ($bucket == 'former') {
            $queryBuilder->where('recurring_bills.fulfilment_id', $parent->id)
                ->where('recurring_bills.status', RecurringBillStatusEnum::FORMER);
        } else {
            $queryBuilder->where('recurring_bills.fulfilment_id', $parent->id);
        }
        $queryBuilder->join('recurring_bill_stats', 'recurring_bill_stats.recurring_bill_id', '=', 'recurring_bills.id');

        $queryBuilder->join('fulfilment_customers', 'recurring_bills.fulfilment_customer_id', '=', 'fulfilment_customers.id');
        $queryBuilder->join('customers', 'fulfilment_customers.customer_id', '=', 'customers.id');
        $queryBuilder->join('currencies', 'recurring_bills.currency_id', '=', 'currencies.id');

        return $queryBuilder
            ->defaultSort('reference')
            ->select([
                'recurring_bills.id',
                'recurring_bills.slug',
                'recurring_bills.status',
                'recurring_bills.reference',
                'recurring_bills.start_date',
                'recurring_bills.end_date',
                'recurring_bills.net_amount',
                'recurring_bill_stats.number_transactions',
                'fulfilment_customers.slug as fulfilment_customer_slug',
                'customers.name as customer_name',
                'currencies.code as currency_code',
                'currencies.symbol as currency_symbol',
            ])
            ->allowedSorts(['reference','number_transactions','net_amount', 'start_date', 'end_date'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Fulfilment|FulfilmentCustomer $parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch()
                ->withEmptyState(
                    match (class_basename($parent)) {
                        'Fulfilment' => [
                            'title'       => __("You don't have any recurring bill yet").' 😭',
                            'description' => __("Dont worry soon you will be pretty busy"),
                            'count'       => $parent->stats->number_recurring_bills,

                        ],
                        'FulfilmentCustomer' => [
                            'title' => __("This customer don't have any recurring bill yet").' 😭',
                            'count' => $parent->number_recurring_bills
                        ]
                    }
                );
            $table->column(key: 'status_icon', label: '', canBeHidden: false, type: 'icon');
            $table->column(key: 'reference', label: __('reference'), canBeHidden: false, sortable: true, searchable: true);
            if ($parent instanceof Fulfilment) {
                $table->column(key: 'customer_name', label: __('customer'), canBeHidden: false, sortable: true, searchable: true);
            }
            $table
                ->column(key: 'number_transactions', label: __('items'), canBeHidden: false, sortable: true, searchable: true, type: 'currency')
                ->column(key: 'net_amount', label: __('net'), canBeHidden: false, sortable: true, searchable: true, type: 'currency')
                ->column(key: 'start_date', label: __('start date'), canBeHidden: false, sortable: true, searchable: true, type: 'currency')
                ->column(key: 'end_date', label: __('end date'), canBeHidden: false, sortable: true, searchable: true, type: 'currency');
        };
    }

    public function jsonResponse(LengthAwarePaginator $recurringBills): AnonymousResourceCollection
    {
        return RecurringBillsResource::collection($recurringBills);
    }

    public function htmlResponse(LengthAwarePaginator $recurringBills, ActionRequest $request): Response
    {
        $subNavigation = [];

        $icon       = ['fal', 'fa-receipt'];
        $title      = __('Recurring bills');
        $afterTitle = null;
        $iconRight  = null;

        if ($this->parent instanceof FulfilmentCustomer) {
            $subNavigation = $this->getFulfilmentCustomerSubNavigation($this->parent, $request);
            $icon          = ['fal', 'fa-user'];
            $title         = $this->parent->customer->name;
            $iconRight     = [
                'icon' => 'fal fa-receipt',
            ];
            $afterTitle    = [

                'label' => __('recurring bills')
            ];
        } elseif ($this->parent instanceof Fulfilment) {
            $subNavigation = $this->getRecurringBillsNavigation($this->parent, $request);
            if ($this->bucket == 'current') {
                $title = __('Next bills');
            } elseif ($this->bucket == 'former') {
                $title = __('Former bills');
            }
        }

        return Inertia::render(
            'Org/Fulfilment/RecurringBills',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => $title,
                'pageHead'    => [
                    'title'         => $title,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'icon'          => $icon,
                    'subNavigation' => $subNavigation,
                ],
                'data'        => RecurringBillsResource::collection($recurringBills)
            ]
        )->table(
            $this->tableStructure(
                parent: $this->parent,
            )
        );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Recurring bills'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ];
        };


        return match ($routeName) {
            'grp.org.fulfilments.show.operations.recurring_bills.index' => array_merge(
                ShowFulfilment::make()->getBreadcrumbs(
                    $routeParameters
                ),
                $headCrumb(
                    [
                        'name'       => 'grp.org.fulfilments.show.operations.recurring_bills.index',
                        'parameters' => $routeParameters
                    ]
                )
            ),
            'grp.org.fulfilments.show.operations.recurring_bills.current.index' => array_merge(
                ShowFulfilment::make()->getBreadcrumbs(
                    $routeParameters
                ),
                $headCrumb(
                    [
                        'name'       => 'grp.org.fulfilments.show.operations.recurring_bills.current.index',
                        'parameters' => $routeParameters
                    ]
                )
            ),
            'grp.org.fulfilments.show.operations.recurring_bills.former.index' => array_merge(
                ShowFulfilment::make()->getBreadcrumbs(
                    $routeParameters
                ),
                $headCrumb(
                    [
                        'name'       => 'grp.org.fulfilments.show.operations.recurring_bills.former.index',
                        'parameters' => $routeParameters
                    ]
                )
            ),

            'grp.org.fulfilments.show.crm.customers.show.recurring_bills.index' => array_merge(
                ShowFulfilmentCustomer::make()->getBreadcrumbs(
                    $routeParameters
                ),
                $headCrumb(
                    [
                        'name'       => 'grp.org.fulfilments.show.crm.customers.show.recurring_bills.index',
                        'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment', 'fulfilmentCustomer'])
                    ]
                )
            ),
        };
    }
}
