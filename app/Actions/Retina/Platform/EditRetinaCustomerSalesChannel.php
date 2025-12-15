<?php

/*
 * author Arya Permana - Kirin
 * created on 01-07-2025-11h-01m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Platform;

use App\Actions\RetinaAction;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\EbayUser;
use App\Models\Helpers\TaxCategory;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\LaravelOptions\Options;

class EditRetinaCustomerSalesChannel extends RetinaAction
{
    public function handle(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): Response
    {
        $request->route()->getName();

        /** @var \App\Models\Dropshipping\EbayUser|\App\Models\Dropshipping\WooCommerceUser|\App\Models\Dropshipping\ShopifyUser $user */
        $user = $customerSalesChannel->user;

        $properties = [];
        $routeName = 'retina.models.customer_sales_channel.update';

        if (class_basename($user) == class_basename(EbayUser::class)) {
            $properties = [
                [
                    "label"  => __("Pricing"),
                    'icon'    => 'fa-light fa-user',
                    'title'  => __('pricing'),
                    'fields' => [
                        'is_vat_adjustment' => [
                            'type'  => 'toggle',
                            'label' => __('VAT Pricing Adjustment'),
                            'value' => (bool) Arr::get($customerSalesChannel->settings, 'tax_category.checked')
                        ],
                        'tax_category_id' => [
                            'type'     => 'select',
                            'label'    => __('Vat Category'),
                            'required' => true,
                            'hidden' => ! Arr::get($customerSalesChannel->settings, 'tax_category.checked'),
                            'options' => Options::forModels(TaxCategory::class),
                            'value'    => Arr::get($customerSalesChannel->settings, 'tax_category.id')
                        ],
                    ]
                ],
            ];
        }

        if ($user instanceof EbayUser) {
            $routeName = 'retina.models.customer_sales_channel.ebay_update';
            $properties = [
                ...$properties,
                [
                    "label"  => __("Shipping"),
                    'icon'    => 'fa-light fa-truck',
                    'title'  => __('shipping'),
                    'fields' => [
                        'shipping_service' => [
                            'type'  => 'select',
                            'label' => __('shipping service'),
                            'options' => Options::forArray($user->getServicesForOptions()),
                            'value' => Arr::get($customerSalesChannel->settings, 'shipping.service_code'),
                        ],
                        'shipping_price' => [
                            'type'  => 'input',
                            'label' => __('shipping price'),
                            'value' => Arr::get($customerSalesChannel->settings, 'shipping.price')
                        ],
                        'shipping_max_dispatch_time' => [
                            'type'  => 'input',
                            'label' => __('shipping max dispatch time'),
                            'value' => Arr::get($customerSalesChannel->settings, 'shipping.max_dispatch_time')
                        ],
                    ]
                ],
                [
                    "label"  => __("Returns"),
                    'icon'    => 'fa-light fa-arrow-left',
                    'title'  => __('returns'),
                    'fields' => [
                        'return_accepted' => [
                            'type'  => 'toggle',
                            'label' => __('Returns Accepted'),
                            'value' => (bool) Arr::get($customerSalesChannel->settings, 'return.accepted')
                        ],
                        'return_payer' => [
                            'type'     => 'select',
                            'label'    => __('Return Payer'),
                            'required' => true,
                            'hidden' => ! Arr::get($customerSalesChannel->settings, 'return.accepted'),
                            'options' => Options::forArray([
                                    'SELLER' => __('Seller'),
                                    'BUYER' => __('Buyer')
                                ]),
                            'value'    => Arr::get($customerSalesChannel->settings, 'return.payer')
                        ],
                        'return_within' => [
                            'type'  => 'select',
                            'label' => __('returns within'),
                            'required' => true,
                            'hidden' => ! Arr::get($customerSalesChannel->settings, 'return.accepted'),
                            'options' => Options::forArray([
                                14 => __('14 Days'),
                                30 => __('30 Days'),
                                60 => __('60 Days')
                            ]),
                            'value' => Arr::get($customerSalesChannel->settings, 'return.within')
                        ],
                        'return_description' => [
                            'type'  => 'textarea',
                            'label' => __('return description'),
                            'hidden' => ! Arr::get($customerSalesChannel->settings, 'return.accepted'),
                            'value' => Arr::get($customerSalesChannel->settings, 'return.description')
                        ],
                    ]
                ],
            ];
        }

        return Inertia::render(
            'EditModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $customerSalesChannel
                ),
                'title'       => __('Edit sales channel'),
                'pageHead'    => [
                    'title'   => __('Edit sales channel'),
                    'icon'    => [
                        'icon'  => ['fal', 'fa-code-branch'],
                        'title' => __('Sales channel')
                    ],
                    'actions' => [
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
                                'icon'    => 'fa-light fa-fingerprint',
                                'title'  => __('properties'),
                                'fields' => [
                                    'name' => [
                                        'type'  => 'input',
                                        'label' => __('store name'),
                                        'value' => $customerSalesChannel->name
                                    ],
                                ]
                            ],
                            [
                                "label"  => __("Manage Stock"),
                                'icon'    => 'fa-light fa-box',
                                'title'  => __('manage stock'),
                                'fields' => [
                                    'max_quantity_advertise' => [
                                        'type'  => 'input',
                                        'label' => __('Max Quantity To Advertise'),
                                        'value' => $customerSalesChannel->max_quantity_advertise
                                    ],
                                    'stock_update' => [
                                        'type'  => 'toggle',
                                        'label' => __('Stock Update'),
                                        'value' => (bool) $customerSalesChannel->stock_update
                                    ],
                                    'stock_threshold' => [
                                        'type'  => 'input',
                                        'label' => __('Stock Threshold'),
                                        'value' => $customerSalesChannel->stock_threshold
                                    ],
                                ]
                            ],
                            ...$properties
                        ],
                    'args'      => [
                        'updateRoute' => [
                            'name'       => $routeName,
                            'parameters' => [
                                'customerSalesChannel' => $customerSalesChannel->id
                            ],
                            'method'     => 'patch'
                        ]
                    ]
                ]
            ]
        );
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): Response
    {
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
