<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 21 February 2023 17:54:17 Malaga, Spain
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\PaymentServiceProvider\UI;

use App\Actions\GrpAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Http\Resources\Accounting\PaymentServiceProvidersResource;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\PaymentServiceProvider;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexPaymentServiceProviders extends GrpAction
{
    public function handle(Group $group, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('payment_service_providers.code', $value)
                    ->orWhereAnyWordStartWith('payment_service_providers.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(PaymentServiceProvider::class);

        $queryBuilder->where('group_id', $group->id);


        /*
        foreach ($this->elementGroups as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }
        */

        return $queryBuilder
            ->defaultSort('payment_service_providers.code')
            ->select(['code', 'slug', 'number_payment_accounts', 'number_payments','name'])
            ->leftJoin('payment_service_provider_stats', 'payment_service_providers.id', 'payment_service_provider_stats.payment_service_provider_id')
            ->with('media')
            ->allowedSorts(['code', 'number_payment_accounts', 'number_payments','name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure($prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }
            $table
                ->withGlobalSearch()
                ->defaultSort('code')
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);

        };
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("group-reports");
    }


    public function jsonResponse(LengthAwarePaginator $paymentServiceProviders): AnonymousResourceCollection
    {
        return PaymentServiceProvidersResource::collection($paymentServiceProviders);
    }


    public function htmlResponse(LengthAwarePaginator $paymentServiceProviders, ActionRequest $request): Response
    {
        return Inertia::render(
            'Accounting/PaymentServiceProviders',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Payment Service Providers'),
                'pageHead'    => [
                    'title' => __('Payment Service Providers'),
                    'icon'  => ['fal', 'fa-cash-register'],
                ],
                'data'        => PaymentServiceProvidersResource::collection($paymentServiceProviders),


            ]
        )->table($this->tableStructure());
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation(app('group'), $request);
        return $this->handle($this->group);
    }

    public function getBreadcrumbs(array $routeParameters, $suffix = null): array
    {
        return array_merge(
            ShowGroupOverviewHub::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.overview.accounting.payment-service-providers.index',
                            'parameters' => $routeParameters
                        ],
                        'label' => __('Providers'),
                        'icon'  => 'fal fa-bars',
                    ],
                    'suffix' => $suffix
                ],
            ]
        );
    }
}
