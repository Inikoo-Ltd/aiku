<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 16 Oct 2024 10:47:26 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Client\UI;

use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Http\Resources\CRM\CustomerClientResource;
use App\InertiaTable\InertiaTable;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Dropshipping\TiktokUser;
use App\Models\PlatformHasClient;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexRetinaPlatformCustomerClients extends RetinaAction
{
    private Customer|ShopifyUser|TiktokUser $parent;
    private Platform $platform;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->is_root;
    }

    public function asController(Platform $platform, ActionRequest $request): LengthAwarePaginator
    {
        $this->platform = $platform;
        $this->initialisation($request);

        $this->parent = match ($platform->type) {
            PlatformTypeEnum::TIKTOK => $this->customer->tiktokUser,
            PlatformTypeEnum::SHOPIFY => $this->customer->shopifyUser,
            PlatformTypeEnum::WOOCOMMERCE => throw new \Exception('To be implemented')
        };

        return $this->handle($this->customer);
    }

    public function inPupil(Platform $platform, ActionRequest $request): LengthAwarePaginator
    {
        $this->asAction = true;
        $this->initialisationFromPupil($request);

        $this->platform = $platform;
        if ($platform->type === PlatformTypeEnum::SHOPIFY) {
            $this->parent = $this->customer->shopifyUser;
        } else {
            $this->parent = $this->customer->tiktokUser;
        }

        return $this->handle($this->customer);
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
                    $request->route()->originalParameters(),
                    $request->route()->getName()
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
                ShowRetinaDashboard::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'retina.dropshipping.client.index',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Clients'),
                        ]
                    ]
                ]
            );
    }
}
