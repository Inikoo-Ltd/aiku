<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Jan 2024 20:05:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\FulfilmentCustomer\UI;

use App\Actions\Fulfilment\Fulfilment\UI\ShowFulfilment;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithFulfilmentShopAuthorisation;
use App\Actions\Traits\WithFulfilmentCustomersSubNavigation;
use App\Enums\CRM\Customer\CustomerStatusEnum;
use App\Http\Resources\Fulfilment\FulfilmentCustomersPendingApprovalResource;
use App\InertiaTable\InertiaTable;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexFulfilmentCustomersPendingApproval extends OrgAction
{
    use WithFulfilmentShopAuthorisation;
    use WithFulfilmentCustomersSubNavigation;


    public function handle(Fulfilment $fulfilment, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('customers.name', $value)
                    ->orWhereStartWith('customers.email', $value)
                    ->orWhere('customers.reference', '=', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(FulfilmentCustomer::class);
        $queryBuilder->where('fulfilment_customers.fulfilment_id', $fulfilment->id);


        $queryBuilder->where('customers.status', CustomerStatusEnum::PENDING_APPROVAL->value);


        return $queryBuilder
            ->defaultSort('registered_at')
            ->select([
                'reference',
                'fulfilment_customers.status',
                'customers.id',
                'customers.name',
                'customers.location',
                'registered_at',
                'fulfilment_customers.slug',
                'customers.location',
                'customers.phone',
                'customers.email'
            ])
            ->leftJoin('customers', 'customers.id', 'fulfilment_customers.customer_id')
            ->allowedSorts(['registered_at', 'email','reference', 'name', 'registered_at', 'customers.phone'])
            ->allowedFilters([$globalSearch])
            ->withPaginator(prefix: $prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Fulfilment $fulfilment, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($fulfilment, $modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }


            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title' => __("You don't have any customer for approval").' 😅',
                        'count' => $fulfilment->shop->crmStats->number_customers_status_pending_approval,

                    ]
                )
                ->column(key: 'reference', label: __('Reference'), canBeHidden: false, sortable: true, searchable: true, type: 'icon')
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'email', label: __('email'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'phone', label: __('phone'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'location', label: __('location'), canBeHidden: false, searchable: true)
                ->column(key: 'registered_at', label: ['type' => 'text', 'data' => __('Date'), 'tooltip' => __('Registered at')], canBeHidden: false, sortable: true, align:'right')
                ->column(key: 'action', label: __('Actions'));
        };
    }

    public function asController(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($fulfilment);
    }


    public function jsonResponse(LengthAwarePaginator $customers): AnonymousResourceCollection
    {
        return FulfilmentCustomersPendingApprovalResource::collection($customers);
    }

    public function htmlResponse(LengthAwarePaginator $customers, ActionRequest $request): Response
    {
        $navigation = $this->getSubNavigation($this->fulfilment, $request);

        return Inertia::render(
            'Org/Fulfilment/FulfilmentCustomers',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'title'       => __('Pending Customer Approval'),
                'pageHead'    => [
                    'title'         => __('Pending Customer Approval'),
                    'icon'          => [
                        'icon'    => ['fal', 'fa-user-clock'],
                        'tooltip' => $this->fulfilment->shop->name.' '.__('customers to approve')
                    ],
                    'subNavigation' => $navigation
                ],
                'data'        => FulfilmentCustomersPendingApprovalResource::collection($customers)
            ]
        )->table($this->tableStructure($this->fulfilment));
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
