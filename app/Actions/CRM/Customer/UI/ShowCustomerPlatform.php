<?php

/*
 * author Arya Permana - Kirin
 * created on 11-04-2025-09h-45m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\CRM\Customer\UI;

use App\Actions\OrgAction;
use App\Enums\UI\CRM\CustomerPlatformTabsEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Ordering\ModelHasPlatform;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowCustomerPlatform extends OrgAction
{
    use WithCustomerPlatformSubNavigation;

    public function handle(ModelHasPlatform $modelHasPlatform): ModelHasPlatform
    {
        return $modelHasPlatform;
    }

    public function asController(Organisation $organisation, Shop $shop, Customer $customer, ModelHasPlatform $modelHasPlatform, ActionRequest $request): ModelHasPlatform
    {
        $this->initialisationFromShop($shop, $request)->withTab(CustomerPlatformTabsEnum::values());

        return $this->handle($modelHasPlatform);
    }

    public function htmlResponse(ModelHasPlatform $modelHasPlatform, ActionRequest $request): Response
    {
        $customer = $modelHasPlatform->model;
        $navigation = CustomerPlatformTabsEnum::navigation();

        $actions = [];

        return Inertia::render(
            'Org/Fulfilment/FulfilmentCustomerPlatform',
            [
                'title'       => __('customer'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $modelHasPlatform,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    'icon'          => [
                        'title' => __('platform'),
                        'icon'  => 'fal fa-user',
                    ],
                    'model'         => __('Platform'),
                    'subNavigation' => $this->getCustomerPlatformSubNavigation($modelHasPlatform, $customer, $request),
                    'title'         => $modelHasPlatform->platform->name,
                    'afterTitle'    => [
                        'label' => '('.$modelHasPlatform->model->name.')',
                    ],
                    'actions'       => $actions
                ],

                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => $navigation
                ],
            ]
        );
    }

    public function getBreadcrumbs(ModelHasPlatform $modelHasPlatform, string $routeName, array $routeParameters): array
    {
        $headCrumb = function (Customer $customer, array $routeParameters, string $suffix = '') {
            return [
                [

                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Channels')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $customer->reference,
                        ],

                    ],
                    'suffix'         => $suffix

                ],
            ];
        };

        $customer = $modelHasPlatform->model;

        return array_merge(
            ShowCustomer::make()->getBreadcrumbs(
                $routeName,
                $routeParameters
            ),
            $headCrumb(
                $customer,
                [

                    'index' => [
                        'name'       => 'grp.org.shops.show.crm.customers.show.platforms.index',
                        'parameters' => Arr::only($routeParameters, ['organisation', 'shop', 'customer'])
                    ],
                    'model' => [
                        'name'       => 'grp.org.shops.show.crm.customers.show.platforms.show',
                        'parameters' => [
                            'organisation' => $routeParameters['organisation'],
                            'shop'   => $routeParameters['shop'],
                            'customer' => $routeParameters['customer'],
                            'modelHasPlatform'     => $modelHasPlatform->id
                        ]
                    ]
                ]
            )
        );
    }
}
