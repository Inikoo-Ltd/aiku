<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 09-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Dropshipping\ApiToken\UI;

use App\Actions\RetinaAction;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowRetinaApiDropshippingDashboard extends RetinaAction
{
    use AsAction;


    public function handle(CustomerSalesChannel $customerSalesChannel): Response
    {
        return Inertia::render(
            'Dropshipping/Api/RetinaApiDropshippingDashboard',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    __('Api Token')
                ),
                'title'       => __('Api Token'),
                'pageHead'    => [
                    'title'     => 'API Token',
                    'icon'      => 'fal fa-key',
                    'noCapitalise'  => true,
                    // 'actions'   => [
                    //     [
                    //         'type'  => 'button',
                    //         'style' => 'edit',
                    //         'route' => [
                    //             'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                    //             'parameters' => $request->route()->originalParameters()
                    //         ]
                    //     ],

                    // ],

                ],
                'data'       => [
                    'route_generate' => [
                        'name' => 'retina.dropshipping.customer_sales_channels.api.show.token',
                        'parameters' => [
                            'customerSalesChannel' => $customerSalesChannel->slug,
                        ],
                    ],
                    'route_documentation' => '#',
                    'route_show' => [
                        'name' => 'retina.dropshipping.customer_sales_channels.api.show',
                        'parameters' => [
                            'customerSalesChannel' => $customerSalesChannel->slug,
                        ],
                    ],
                ],
                'routes'    => [
                    'create_token' => [  // TODO: route for creating a new API token
                        // 'name' => 'retina.dropshipping.customer_sales_channels.api.create.token',
                        // 'parameters' => [
                        //     'customerSalesChannel' => $customerSalesChannel->slug,
                        // ],
                    ],
                ],
                'dataTable' => [],  // TODO: for Table
            ]
        );
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): Response|RedirectResponse
    {
        $this->initialisation($request);
        return $this->handle($customerSalesChannel, $request);
    }

    public function getBreadcrumbs($label = null): array
    {
        return [
            [

                'type'   => 'simple',
                'simple' => [
                    'icon'  => 'fal fa-home',
                    'route' => [
                        'name' => 'retina.dashboard.show'
                    ]
                ]

            ],

        ];
    }
}
