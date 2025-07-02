<?php

/*
 * author Arya Permana - Kirin
 * created on 01-07-2025-11h-01m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Platform;

use App\Actions\RetinaAction;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\LaravelOptions\Options;

class EditRetinaCustomerSalesChannel extends RetinaAction
{
    public function handle(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): Response
    {
        $request->route()->getName();

        return Inertia::render(
            'EditModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $customerSalesChannel
                ),
                'title'       => __('edit sales channel'),
                'pageHead'    => [
                    'title'        => __('edit sales channel'),
                    'icon'         => [
                        'icon'  => ['fal', 'fa-code-branch'],
                        'title' => __('sales channel')
                    ],
                    'actions'      => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'label' => __('Exit edit'),
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ],
                        ]
                    ]
                ],
                'formData'    => [
                    'blueprint' =>
                        [
                            [
                                "label"  => __("Properties"),
                                'title'  => __('properties'),
                                'fields' => [
                                    'name' => [
                                        'type'  => 'input',
                                        'label' => __('store name'),
                                        'value' => $customerSalesChannel->name
                                    ],
                                    'status' => [
                                        'type'     => 'select',
                                        'label'    => __('status'),
                                        'value'    => $customerSalesChannel->status,
                                        'options'  => Options::forEnum(CustomerSalesChannelStatusEnum::class)
                                    ],
                                ]
                            ],
                        ],
                    'args' => [
                        'updateRoute'     => [
                            'name'      => 'retina.models.customer_sales_channel.update',
                            'parameters' => [
                                'customerSalesChannel' => $customerSalesChannel->id
                            ],
                            'method' => 'patch'
                        ]
                    ]
                ]
            ]
        );
    }

    public function asController(
        CustomerSalesChannel $customerSalesChannel,
        ActionRequest $request
    ): Response {
        $this->initialisation($request);

        return $this->handle($customerSalesChannel, $request);
    }

    public function getBreadcrumbs(CustomerSalesChannel $customerSalesChannel): array
    {
        return array_merge(
            ShowRetinaCustomerSalesChannelDashboard::make()->getBreadcrumbs(
                $customerSalesChannel,
            ),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Editing Channel'),
                    ]
                ]
            ]
        );
    }
}
