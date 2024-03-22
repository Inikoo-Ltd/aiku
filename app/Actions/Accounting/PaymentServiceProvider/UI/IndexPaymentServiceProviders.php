<?php
/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 21 February 2023 17:54:17 Malaga, Spain
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\PaymentServiceProvider\UI;

use App\Actions\OrgAction;
use App\Actions\UI\Accounting\ShowAccountingDashboard;
use App\Http\Resources\Accounting\PaymentServiceProviderResource;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\PaymentServiceProvider;
use App\Models\Market\Shop;
use App\Models\SysAdmin\Organisation;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use App\Services\QueryBuilder;

class IndexPaymentServiceProviders extends OrgAction
{
    public function handle(Organisation|Shop $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('payment_service_providers.code', $value)
                    ->orWhereAnyWordStartWith('payment_service_providers.data', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(PaymentServiceProvider::class);

        $queryBuilder->where('organisation_id', $this->organisation->id);


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
            ->select(['code', 'slug', 'number_payment_accounts', 'number_payments'])
            ->leftJoin('payment_service_provider_stats', 'payment_service_providers.id', 'payment_service_provider_stats.payment_service_provider_id')
            ->when(true, function ($query) use ($parent) {
                if (class_basename($parent) == 'Shop') {
                    $query->leftJoin('payment_service_provider_shop', 'payment_service_providers.id', 'payment_service_provider_shop.payment_service_provider_id');
                    $query->where('payment_service_provider_shop.shop_id', $parent->id);
                }
            })
            ->allowedSorts(['code', 'number_payment_accounts', 'number_payments'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix)
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
                ->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_payment_accounts', label: __('accounts'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_payments', label: __('payments'), canBeHidden: false, sortable: true, searchable: true);
        };
    }

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->hasPermissionTo("accounting.{$this->organisation->id}.edit");
        return $request->user()->hasPermissionTo("accounting.{$this->organisation->id}.view");
    }


    public function jsonResponse(LengthAwarePaginator $paymentServiceProviders): AnonymousResourceCollection
    {
        return PaymentServiceProviderResource::collection($paymentServiceProviders);
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

                ],
                'data'        => PaymentServiceProviderResource::collection($paymentServiceProviders),


            ]
        )->table($this->tableStructure());
    }


    public function inOrganisation(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
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
                            'name'       => 'grp.org.accounting.payment-service-providers.index',
                            'parameters' => $routeParameters
                        ],
                        'label' => __('providers'),
                        'icon'  => 'fal fa-bars',
                    ],
                    'suffix' => $suffix
                ],
            ]
        );
    }
}
