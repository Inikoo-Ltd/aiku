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
use App\Http\Resources\Fulfilment\FulfilmentCustomersRejectedResource;
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

class IndexFulfilmentCustomersRejected extends OrgAction
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
        $queryBuilder->where('customers.status', CustomerStatusEnum::REJECTED);


        return $queryBuilder
            ->defaultSort('-customers.rejected_at')
            ->select([
                'reference',
                'rejected_at',
                'rejected_reason',
                'rejected_notes',
                'customers.name',
                'fulfilment_customers.id',
                'fulfilment_customers.slug',
            ])
            ->leftJoin('customers', 'customers.id', 'fulfilment_customers.customer_id')
            ->allowedSorts(['rejected_at', 'reference', 'name', 'rejected_at', 'rejected_reason'])
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
                        'title' => __("You don't have any rejected customers").' 🌟',
                        'count' => $fulfilment->shop->crmStats->number_customers_status_rejected,
                    ]
                )
                ->column(key: 'reference', label: __('Reference'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'rejected_at', label: __('Date'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'rejected_reason', label: __('reason'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'rejected_notes', label: __('rejection notes'));
        };
    }

    public function asController(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($fulfilment);
    }


    public function jsonResponse(LengthAwarePaginator $customers): AnonymousResourceCollection
    {
        return FulfilmentCustomersRejectedResource::collection($customers);
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
                'title'       => __('rejected customer'),
                'pageHead'    => [
                    'title'         => __('rejected customers'),
                    'icon'          => [
                        'icon'    => ['fal', 'fa-user-times'],
                        'tooltip' => $this->fulfilment->shop->name.' '.__('rejected customers')
                    ],
                    'subNavigation' => $navigation
                ],
                'data'        => FulfilmentCustomersRejectedResource::collection($customers)
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
