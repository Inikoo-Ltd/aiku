<?php

/*
 * author Arya Permana - Kirin
 * created on 02-04-2025-15h-30m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Fulfilment\FulfilmentCustomer\UI;

use App\Actions\Fulfilment\FulfilmentCustomer\ShowFulfilmentCustomer;
use App\Actions\Fulfilment\WithFulfilmentCustomerPlatformSubNavigation;
use App\Actions\OrgAction;
use App\Enums\UI\Fulfilment\FulfilmentCustomerPlatformTabsEnum;
use App\Enums\UI\Fulfilment\FulfilmentCustomerTabsEnum;
use App\Models\CRM\CustomerHasPlatform;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowFulfilmentCustomerPlatform extends OrgAction
{
    use WithFulfilmentCustomerPlatformSubNavigation;

    public function handle(CustomerHasPlatform $customerHasPlatform): CustomerHasPlatform
    {
        return $customerHasPlatform;
    }


    public function asController(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, CustomerHasPlatform $customerHasPlatform, ActionRequest $request): CustomerHasPlatform
    {
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(FulfilmentCustomerTabsEnum::values());

        return $this->handle($customerHasPlatform);
    }

    public function htmlResponse(CustomerHasPlatform $customerHasPlatform, ActionRequest $request): Response
    {
        $navigation         = FulfilmentCustomerPlatformTabsEnum::navigation();

        $actions = [];

        return Inertia::render(
            'Org/Fulfilment/FulfilmentCustomerPlatform',
            [
                'title'       => __('customer'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $customerHasPlatform,
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    'icon'          => [
                        'title' => __('platform'),
                        'icon'  => 'fal fa-user',
                    ],
                    'model'         => __('Platform'),
                    'subNavigation' => $this->getFulfilmentCustomerPlatformSubNavigation($customerHasPlatform, $request),
                    'title'         => $customerHasPlatform->platform->name,
                    'afterTitle'    => [
                        'label' => '('.$customerHasPlatform->customer->name.')',
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

    public function getBreadcrumbs(CustomerHasPlatform $customerHasPlatform, array $routeParameters): array
    {
        $headCrumb = function (FulfilmentCustomer $fulfilmentCustomer, array $routeParameters, string $suffix = '') {
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
                            'label' => $fulfilmentCustomer->customer->reference,
                        ],

                    ],
                    'suffix'         => $suffix

                ],
            ];
        };

        $fulfilmentCustomer = $customerHasPlatform->customer->fulfilmentCustomer;

        return array_merge(
            ShowFulfilmentCustomer::make()->getBreadcrumbs(
                $routeParameters
            ),
            $headCrumb(
                $fulfilmentCustomer,
                [

                    'index' => [
                        'name'       => 'grp.org.fulfilments.show.crm.customers.show.platforms.index',
                        'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment', 'fulfilmentCustomer'])
                    ],
                    'model' => [
                        'name'       => 'grp.org.fulfilments.show.crm.customers.show.platforms.show',
                        'parameters' => [
                            'organisation'        => $routeParameters['organisation'],
                            'fulfilment'          => $routeParameters['fulfilment'],
                            'fulfilmentCustomer'  => $routeParameters['fulfilmentCustomer'],
                            'customerHasPlatform' => $customerHasPlatform->id
                        ]
                    ]
                ]
            )
        );
    }
}
