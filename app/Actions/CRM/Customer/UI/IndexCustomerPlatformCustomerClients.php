<?php
/*
 * author Arya Permana - Kirin
 * created on 11-04-2025-14h-33m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\CRM\Customer\UI;

use App\Actions\CRM\Customer\UI\ShowCustomerPlatform;
use App\Actions\OrgAction;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Http\Resources\CRM\CustomerClientResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Dropshipping\TiktokUser;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Ordering\ModelHasPlatform;
use App\Models\PlatformHasClient;
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
    private ShopifyUser|TiktokUser $parent;
    private ModelHasPlatform $modelHasPlatform;

    public function asController(Organisation $organisation, Shop $shop, Customer $customer, ModelHasPlatform $modelHasPlatform, ActionRequest $request): LengthAwarePaginator
    {
        $this->modelHasPlatform = $modelHasPlatform;
        $this->initialisationFromShop($shop, $request);

        $this->parent = match ($modelHasPlatform->platform->type) {
            PlatformTypeEnum::TIKTOK => $customer->tiktokUser,
            PlatformTypeEnum::SHOPIFY => $customer->shopifyUser,
            PlatformTypeEnum::WOOCOMMERCE => throw new \Exception('To be implemented')
        };

        return $this->handle($customer);
    }

    public function handle(Customer $customer, $prefix = null): LengthAwarePaginator
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

        $queryBuilder = QueryBuilder::for(PlatformHasClient::class);
        $queryBuilder->where('platform_has_clients.customer_id', $customer->id);
        $queryBuilder->where('platform_has_clients.userable_type', $this->parent->getMorphClass());
        $queryBuilder->where('platform_has_clients.userable_id', $this->parent->id);

        /*
        foreach ($this->elementGroups as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                prefix: $prefix,
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine']
            );
        }
        */

        $queryBuilder->leftJoin('customer_clients', 'platform_has_clients.customer_client_id', 'customer_clients.id');

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

    public function tableStructure($parent, ?array $modelOperations = null, $prefix = null): Closure
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
                        'Customer' => [
                            'title'       => __("No clients found"),
                            'description' => __("You can add your client 🤷🏽‍♂️"),
                            'count'       => $parent->stats->number_customer_clients,
                            'action'      => [
                                'type'    => 'button',
                                'style'   => 'create',
                                'tooltip' => __('new client'),
                                'label'   => __('client'),
                                'route'   => [
                                    'name'       => 'retina.dropshipping.client.create',
                                ]
                            ]
                        ],
                        default => null
                    }
                )
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
        // $scope = $this->parent;
        $icon       = ['fal', 'fa-user'];
        $title      = $this->parent->name;
        $iconRight  = [
            'icon'  => ['fal', 'fa-user-friends'],
            'title' => __('customer client')
        ];

        if ($this->parent instanceof TiktokUser) {
            $afterTitle = [
                'label' => __('Tiktok Clients')
            ];
        } elseif ($this->parent instanceof ShopifyUser) {
            $afterTitle = [
                'label' => __('Shopify Clients')
            ];
        } else {
            $afterTitle = [
                'label' => __('Clients')
            ];
        }


        return Inertia::render(
            'Dropshipping/Client/CustomerClients',
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
                    'actions'       => [
                        match (class_basename($this->parent)) {
                            'ShopifyUser' => [
                                'type'    => 'button',
                                'style'   => 'create',
                                'tooltip' => __('Fetch Client'),
                                'label'   => __('Fetch Client'),
                                'route'   => [
                                    'name'       => 'pupil.dropshipping.platforms.client.fetch',
                                    'parameters' => [
                                        'platform' => $this->platform->slug
                                    ]
                                ]
                            ]
                        },
                    ],

                ],
                'data'        => CustomerClientResource::collection($customerClients),

            ]
        )->table($this->tableStructure($this->parent));
    }

    public function getBreadcrumbs($routeName, $routeParameters): array
    {
        return
            array_merge(
                ShowCustomerPlatform::make()->getBreadcrumbs($this->modelHasPlatform, $routeName, $routeParameters),
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
