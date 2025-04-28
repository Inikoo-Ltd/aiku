<?php

/*
 * author Arya Permana - Kirin
 * created on 11-04-2025-14h-33m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\CRM\Customer\UI;

use App\Actions\Dropshipping\Platform\UI\ShowPlatformInCustomer;
use App\Actions\OrgAction;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Http\Resources\CRM\CustomerClientResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\CRM\CustomerHasPlatform;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\Platform;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexCustomerPlatformCustomerClients extends OrgAction
{
    use WithCustomerPlatformSubNavigation;

    private Platform $platform;
    private CustomerHasPlatform $customerHasPlatform;

    public function asController(Organisation $organisation, Shop $shop, Customer $customer, Platform $platform, ActionRequest $request): LengthAwarePaginator
    {
        $customerHasPlatform = CustomerHasPlatform::where('customer_id', $customer->id)->where('platform_id', $platform->id)->first();

        $this->customerHasPlatform = $customerHasPlatform;
        $this->platform = $platform;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($customer, $platform);
    }

    public function handle(Customer $customer, Platform $platform, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('customer_clients.name', $value)
                    ->orWhereStartWith('customer_clients.email', $value)
                    ->orWhere('customer_clients.reference', '=', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(CustomerClient::class);
        $queryBuilder->where('customer_clients.customer_id', $customer->id);
        $queryBuilder->where('customer_clients.platform_id', $platform->id);

        return $queryBuilder
            ->defaultSort('customer_clients.reference')
            ->select([
                'customer_clients.location',
                'customer_clients.reference',
                'customer_clients.id',
                'customer_clients.name',
                'customer_clients.ulid',
                'customer_clients.created_at'
            ])
            ->allowedSorts(['reference', 'name', 'created_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
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
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'location', label: __('location'), canBeHidden: false, searchable: true)
                ->column(key: 'created_at', label: __('since'), canBeHidden: false, sortable: true, searchable: true);
        };
    }

    public function jsonResponse(LengthAwarePaginator $customerClients): AnonymousResourceCollection
    {
        return CustomerClientResource::collection($customerClients);
    }

    public function htmlResponse(LengthAwarePaginator $customerClients, ActionRequest $request): Response
    {
        $icon       = ['fal', 'fa-user'];
        $title      = $this->customerHasPlatform->customer->name;
        $iconRight  = [
            'icon'  => ['fal', 'fa-user-friends'],
            'title' => __('customer client')
        ];

        if ($this->platform->type == PlatformTypeEnum::TIKTOK) {
            $afterTitle = [
                'label' => __('Tiktok Clients')
            ];
        } elseif ($this->platform->type == PlatformTypeEnum::SHOPIFY) {
            $afterTitle = [
                'label' => __('Shopify Clients')
            ];
        } else {
            $afterTitle = [
                'label' => __('Clients')
            ];
        }

        $subNavigation = $this->getCustomerPlatformSubNavigation($this->customerHasPlatform, $request);

        return Inertia::render(
            'Org/Shop/CRM/CustomerClients',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters(),
                ),
                'title'       => __('customer clients'),
                'pageHead'    => [
                    'title'         => $title,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'icon'          => $icon,
                    'subNavigation' => $subNavigation
                ],
                'data'        => CustomerClientResource::collection($customerClients),

            ]
        )->table($this->tableStructure());
    }

    public function getBreadcrumbs($routeName, $routeParameters): array
    {
        return
            array_merge(
                ShowPlatformInCustomer::make()->getBreadcrumbs($this->platform, $routeName, $routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.shops.show.crm.customers.show.platforms.show.customer-clients.other-platform.index',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Clients'),
                        ]
                    ]
                ]
            );
    }
}
