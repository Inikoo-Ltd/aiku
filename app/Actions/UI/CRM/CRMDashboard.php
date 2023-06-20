<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Mar 2023 18:43:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\UI\CRM;

use App\Actions\UI\Dashboard\Dashboard;
use App\Actions\UI\WithInertia;
use App\Models\Market\Shop;
use App\Models\Tenancy\Tenant;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class CRMDashboard
{
    use AsAction;
    use WithInertia;

    public function handle($scope)
    {
        return $scope;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->hasPermissionTo("crm.view");
    }


    public function inTenant(): Tenant
    {
        return $this->handle(app('currentTenant'));
    }

    public function inShop(Shop $shop): Shop
    {
        return $this->handle($shop);
    }


    public function htmlResponse(Tenant|Shop $scope, ActionRequest $request): Response
    {

        $container = null;
        $scopeType = 'Tenant';
        if (class_basename($scope) == 'Shop') {
            $scopeType = 'Shop';
            $container = [
                'icon'    => ['fal', 'fa-store-alt'],
                'tooltip' => __('Shop'),
                'label'   => Str::possessive($scope->name)
            ];
        }


        return Inertia::render(
            'CRM/CRMDashboard',
            [
                'breadcrumbs'  => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->parameters
                ),
                'title'       => 'CRM',
                'pageHead'    => [
                    'title'     => __('customer relationship manager'),
                    'container' => $container
                ],
                'flatTreeMaps' =>
                    match ($scopeType) {
                        'Shop' => [
                            [

                                [
                                    'name'  => __('customers'),
                                    'icon'  => ['fal', 'fa-money-check-alt'],
                                    'href'  => ['crm.shop.customers.index', $scope->slug],
                                    'index' => [
                                        'number' => $scope->crmStats->number_customers
                                    ]

                                ],
                                [
                                    'name'  => __('orders'),
                                    'icon'  => ['fal', 'fa-coins'],
                                    'href'  => ['crm.shop.orders.index', $scope->slug],
                                    'index' => [
                                        'number' => $scope->crmStats->number_orders
                                    ]

                                ],


                            ]
                        ],
                        default => [
                            [


                                [
                                    'name'  => __('customers'),
                                    'icon'  => ['fal', 'fa-cash-register'],
                                    'href'  => ['crm.customers.index'],
                                    'index' => [
                                        'number' => $scope->crmStats->number_customers
                                    ]

                                ],
                                [
                                    'name'  => __('orders'),
                                    'icon'  => ['fal', 'fa-money-check-alt'],
                                    'href'  => ['crm.orders.index'],
                                    'index' => [
                                        'number' => $scope->crmStats->number_orders
                                    ]

                                ],


                            ],

                        ]
                    }


            ]
        );
    }


    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {


        return match ($routeName) {
            'crm.shop.dashboard' =>
            array_merge(
                Dashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'crm.shop.dashboard',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('CRM').' ('.$routeParameters['shop']->code.')',
                        ]
                    ]
                ]
            ),
            default =>
            array_merge(
                Dashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'crm.dashboard'
                            ],
                            'label' => __('CRM').' ('.__('all shops').')',
                        ]
                    ]
                ]
            )
        };
    }

}
