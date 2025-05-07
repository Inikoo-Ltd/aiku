<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 21 February 2023 17:54:17 Malaga, Spain
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\OrgPaymentServiceProvider\UI;

use App\Actions\Accounting\UI\ShowAccountingDashboard;
use App\Actions\OrgAction;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Http\Resources\Accounting\SelectOrgPaymentServiceProvidersResource;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\PaymentServiceProvider;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\LaravelOptions\Options;
use Spatie\QueryBuilder\AllowedFilter;

class IndexOrgPaymentServiceProviders extends OrgAction
{
    public function handle(Organisation $parent, $prefix = null): LengthAwarePaginator
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

        $queryBuilder->where('payment_service_providers.group_id', $parent->group_id);


        return $queryBuilder
            ->defaultSort('payment_service_providers.code')
            ->select(['org_payment_service_providers.slug','payment_service_providers.code', 'payment_service_providers.state', 'org_payment_service_providers.code as org_code', 'org_payment_service_providers.slug as org_slug', 'org_payment_service_provider_stats.number_payment_accounts', 'org_payment_service_provider_stats.number_payments','name', 'payment_service_providers.id'])
            ->leftJoin(
                'org_payment_service_providers',
                function ($leftJoin) use ($parent) {
                    $leftJoin->on('payment_service_providers.id', '=', 'org_payment_service_providers.payment_service_provider_id')
                        ->where('org_payment_service_providers.organisation_id', '=', $parent->id)
                     ->leftJoin('org_payment_service_provider_stats', 'org_payment_service_providers.id', 'org_payment_service_provider_stats.org_payment_service_provider_id');


                }
            )

            ->with('media')
            ->allowedSorts(['code', 'number_payment_accounts', 'number_payments','name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix)
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $paymentServiceProviders): AnonymousResourceCollection
    {
        return SelectOrgPaymentServiceProvidersResource::collection($paymentServiceProviders);
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
                ->column(key: 'adoption', label: '', type: 'icon')
                ->column(key: 'logo', label: '')
                ->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_payment_accounts', label: __('accounts'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_payments', label: __('payments'), canBeHidden: false, sortable: true, searchable: true);
        };
    }

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->authTo("accounting.{$this->organisation->id}.edit");
        return $request->user()->authTo("accounting.{$this->organisation->id}.view");
    }

    public function htmlResponse(LengthAwarePaginator $paymentServiceProviders, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Accounting/SelectPaymentServiceProviders',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Payment Service Providers'),
                'pageHead'    => [
                    'title' => __('Payment Service Providers'),
                    'icon'  => ['fal', 'fa-cash-register'],

                ],
                'data'                => SelectOrgPaymentServiceProvidersResource::collection($paymentServiceProviders),
                'paymentAccountTypes' => Options::forEnum(PaymentAccountTypeEnum::class),
                'organisation_id'     => $this->organisation->id
            ]
        )->table($this->tableStructure());
    }


    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);
        return $this->handle($organisation);
    }


    public function getBreadcrumbs(array $routeParameters, $suffix = null): array
    {
        return array_merge(
            ShowAccountingDashboard::make()->getBreadcrumbs('grp.org.accounting.dashboard', $routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.accounting.org_payment_service_providers.index',
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
