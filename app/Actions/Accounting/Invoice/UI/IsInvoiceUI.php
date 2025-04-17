<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 14 Feb 2025 12:52:08 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice\UI;

use App\Actions\Fulfilment\Fulfilment\UI\ShowFulfilment;
use App\Actions\Fulfilment\FulfilmentCustomer\ShowFulfilmentCustomer;
use App\Actions\UI\Accounting\ShowAccountingDashboard;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Accounting\Invoice;
use App\Models\Comms\Outbox;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\SysAdmin\Organisation;
use Arr;
use Lorisleiva\Actions\ActionRequest;

trait IsInvoiceUI
{
    public function getCustomerRoute(Invoice $invoice): array
    {
        if ($this->parent instanceof Fulfilment) {
            $customerRoute = [
                'name' => 'grp.org.fulfilments.show.crm.customers.show',
                'parameters' => [
                    'organisation' => $invoice->organisation->slug,
                    'fulfilment' => $invoice->customer->fulfilmentCustomer->fulfilment->slug,
                    'fulfilmentCustomer' => $invoice->customer->fulfilmentCustomer->slug,
                ]
            ];
        } else {
            $customerRoute = [
                'name' => 'grp.org.shops.show.crm.customers.show',
                'parameters' => [
                    'organisation' => $invoice->organisation->slug,
                    'shop' => $invoice->shop->slug,
                    'customer' => $invoice->customer->slug,
                ]
            ];
        }

        return $customerRoute;
    }

    public function getOutboxRoute(Invoice $invoice): array
    {
        /** @var Outbox $outbox */
        $outbox = $invoice->shop->outboxes()->where('code', OutboxCodeEnum::SEND_INVOICE_TO_CUSTOMER->value)->first();

        if ($invoice->shop->type === ShopTypeEnum::FULFILMENT) {
            return [
                'name'       => 'grp.org.fulfilments.show.operations.comms.outboxes.workshop',
                'parameters' => [
                    'organisation' => $invoice->organisation->slug,
                    'fulfilment'   => $invoice->customer->fulfilmentCustomer->fulfilment->slug,
                    'outbox'       => $outbox->slug
                ]
            ];
        }

        return [
            'name'       => 'grp.org.shops.show.dashboard.comms.outboxes.workshop',
            'parameters' => [
                'organisation' => $invoice->organisation->slug,
                'shop'   => $invoice->customer->shop->slug,
                'outbox'       => $outbox->slug
            ]
        ];
    }

    public function getBoxStats(Invoice $invoice): array
    {
        return  [
            'customer'    => [
                'slug'         => $invoice->customer->slug,
                'reference'    => $invoice->customer->reference,
                'route'        => $this->getCustomerRoute($invoice),
                'contact_name' => $invoice->customer->contact_name,
                'company_name' => $invoice->customer->company_name,
                'location'     => $invoice->customer->location,
                'phone'        => $invoice->customer->phone,
                // 'address'      => AddressResource::collection($invoice->customer->addresses),
            ],
            'information' => [
                'paid_amount'    => $invoice->payment_amount,
                'pay_amount'     => round($invoice->total_amount - $invoice->payment_amount, 2)
            ]
        ];
    }

    public function getPrevious(Invoice $invoice, ActionRequest $request): ?array
    {
        $previous = Invoice::where('reference', '<', $invoice->reference)
            ->where('invoices.shop_id', $invoice->shop_id)
            ->orderBy('reference', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(Invoice $invoice, ActionRequest $request): ?array
    {
        $next = Invoice::where('reference', '>', $invoice->reference)
            ->where('invoices.shop_id', $invoice->shop_id)
            ->orderBy('reference')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    public function getInvoiceActions(Invoice $invoice, ActionRequest $request, array $payBoxData): array
    {
        $wrappedActions = [];

        $trashIcon = 'fal fa-trash-alt';

        if ($this->parent instanceof Fulfilment) {
            $wrappedActions[] =
                $this->isSupervisor
                    ? [
                    'supervisor' => true,
                    'type'       => 'button',
                    'style'      => 'red_outline',
                    'tooltip'    => __('delete'),
                    'icon'       => $trashIcon,
                    'key'        => 'delete_booked_in',
                    'ask_why'    => true,
                    'route'      => [
                        'method'     => 'delete',
                        'name'       => 'grp.models.invoice.delete',
                        'parameters' => [
                            'invoice' => $invoice->id
                        ]
                    ]
                ]
                    : [
                    'supervisor'        => false,
                    'supervisors_route' => [
                        'method'     => 'get',
                        'name'       => 'grp.json.fulfilment.supervisors.index',
                        'parameters' => [
                            'fulfilment' => $invoice->shop->fulfilment->slug
                        ]
                    ],
                    'type'              => 'button',
                    'style'             => 'red_outline',
                    'tooltip'           => __('Delete'),
                    'icon'              => $trashIcon,
                    'key'               => 'delete_booked_in',
                    'ask_why'           => true,
                    'route'             => [
                        'method'     => 'delete',
                        'name'       => 'grp.models.invoice.delete',
                        'parameters' => [
                            'invoice' => $invoice->id
                        ]
                    ]
                ];
        } else {
            $wrappedActions[] =
                [
                    'supervisor' => true,
                    'type'       => 'button',
                    'style'      => 'red_outline',
                    'tooltip'    => __('delete'),
                    'icon'       => $trashIcon,
                    'key'        => 'delete_booked_in',
                    'ask_why'    => true,
                    'route'      => [
                        'method'     => 'delete',
                        'name'       => 'grp.models.invoice.delete',
                        'parameters' => [
                            'invoice' => $invoice->id
                        ]
                    ]
                ];
        }

        if ($this->parent instanceof Organisation) {
            $wrappedActions[] = [
                'type'  => 'button',
                'style' => 'edit',
                'label' => __('edit'),
                'route' => [
                    'name'       => 'grp.org.accounting.invoices.edit',
                    'parameters' => $request->route()->originalParameters()
                ],
            ];
        } elseif ($this->parent instanceof FulfilmentCustomer) {
            $wrappedActions[] = [
                'type'  => 'button',
                'style' => 'edit',
                'label' => __('edit'),
                'route' => [
                    'name'       => 'grp.org.fulfilments.show.crm.customers.show.invoices.edit',
                    'parameters' => $request->route()->originalParameters()
                ],
            ];
        }


        $wrappedActions[] =
            [
                'type'  => 'button',
                'style' => 'tertiary',
                'label' => __('send invoice'),
                'key'   => 'send-invoice',
                'route' => [
                    'method'     => 'post',
                    'name'       => 'grp.models.invoice.send_invoice',
                    'parameters' => [
                        'invoice' => $invoice->id
                    ]
                ]
            ];

        if ($payBoxData['invoice_pay']['total_refunds'] != $invoice->total_amount) {
            $wrappedActions[] =
                [
                    'type'  => 'button',
                    'style' => 'create',
                    'label' => __('create refund'),
                    'route' => [
                        'method'     => 'post',
                        'name'       => 'grp.models.refund.create',
                        'parameters' => [
                            'invoice' => $invoice->id,

                        ],
                        'body'       => [
                            'referral_route' => [
                                'name'       => $request->route()->getName(),
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ]
                    ],
                ];
        }





        return $wrappedActions;
    }

    protected function getNavigation(?Invoice $invoice, string $routeName): ?array
    {
        if (!$invoice) {
            return null;
        }


        return match ($routeName) {
            'grp.org.fulfilments.show.operations.invoices.all_invoices.show',
            'grp.org.fulfilments.show.operations.invoices.unpaid_invoices.show',
            'grp.org.fulfilments.show.operations.invoices.show' => [
                'label' => $invoice->reference,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $invoice->organisation->slug,
                        'fulfilment'   => $this->parent->slug,
                        'invoice'      => $invoice->slug
                    ]

                ]
            ],
            'grp.org.fulfilments.show.crm.customers.show.invoices.show' => [
                'label' => $invoice->reference,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation'       => $invoice->organisation->slug,
                        'fulfilment'         => $invoice->shop->fulfilment->slug,
                        'fulfilmentCustomer' => $this->parent->slug,
                        'invoice'            => $invoice->slug
                    ]
                ]
            ],

            'grp.org.accounting.invoices.show', 'grp.org.accounting.invoices.all_invoices.show', 'grp.org.accounting.invoices.unpaid_invoices.show' => [
                'label' => $invoice->reference,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $invoice->organisation->slug,
                        'invoice'      => $invoice->slug
                    ]

                ]
            ],
            'grp.org.shops.show.dashboard.invoices.invoices.show' => [
                'label' => $invoice->reference,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $invoice->organisation->slug,
                        'shop'         => $this->parent->slug,
                        'invoice'      => $invoice->slug
                    ]

                ]
            ],
        };
    }


    public function getBreadcrumbs(Invoice $invoice, string $routeName, array $routeParameters, string $suffix = ''): array
    {
        $headCrumb = function (Invoice $invoice, array $routeParameters, string $suffix = null, $suffixIndex = '') {
            return [
                [

                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Invoices').$suffixIndex,
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $invoice->reference,
                        ],

                    ],
                    'suffix'         => $suffix

                ],
            ];
        };

        return match ($routeName) {
            'grp.org.accounting.invoices.all_invoices.show',
            => array_merge(
                ShowAccountingDashboard::make()->getBreadcrumbs('grp.org.accounting.dashboard', $routeParameters),
                $headCrumb(
                    $invoice,
                    [
                        'index' => [
                            'name'       => 'grp.org.accounting.invoices.index',
                            'parameters' => Arr::only($routeParameters, ['organisation'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.accounting.invoices.all_invoices.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'invoice'])
                        ]
                    ],
                    $suffix,
                    ' ('.__('All').')'
                ),
            ),

            'grp.org.accounting.invoices.unpaid_invoices.show',
            => array_merge(
                ShowAccountingDashboard::make()->getBreadcrumbs('grp.org.accounting.dashboard', $routeParameters),
                $headCrumb(
                    $invoice,
                    [
                        'index' => [
                            'name'       => 'grp.org.accounting.invoices.unpaid_invoices.index',
                            'parameters' => Arr::only($routeParameters, ['organisation'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.accounting.invoices.unpaid_invoices.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'invoice'])
                        ]
                    ],
                    $suffix,
                    ' ('.__('Unpaid').')'
                ),
            ),

            'grp.org.accounting.invoices.show',
            => array_merge(
                ShowAccountingDashboard::make()->getBreadcrumbs('grp.org.accounting.dashboard', $routeParameters),
                $headCrumb(
                    $invoice,
                    [
                        'index' => [
                            'name'       => 'grp.org.accounting.invoices.index',
                            'parameters' => Arr::only($routeParameters, ['organisation'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.accounting.invoices.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'invoice'])
                        ]
                    ],
                    $suffix
                ),
            ),
            'grp.org.fulfilments.show.operations.invoices.show', => array_merge(
                ShowFulfilment::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $invoice,
                    [
                        'index' => [
                            'name'       => 'grp.org.fulfilments.show.operations.invoices.all.index',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.fulfilments.show.operations.invoices.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment', 'invoice'])
                        ]
                    ],
                    $suffix
                ),
            ),
            'grp.org.fulfilments.show.operations.invoices.all_invoices.show', => array_merge(
                ShowFulfilment::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $invoice,
                    [
                        'index' => [
                            'name'       => 'grp.org.fulfilments.show.operations.invoices.all.index',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.fulfilments.show.operations.invoices.all_invoices.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment', 'invoice'])
                        ]
                    ],
                    $suffix,
                    ' ('.__('All').')'
                ),
            ),
            'grp.org.fulfilments.show.operations.invoices.unpaid_invoices.show', => array_merge(
                ShowFulfilment::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $invoice,
                    [
                        'index' => [
                            'name'       => 'grp.org.fulfilments.show.operations.unpaid_invoices.index',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.fulfilments.show.operations.invoices.unpaid_invoices.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment', 'invoice'])
                        ]
                    ],
                    $suffix,
                    ' ('.__('Unpaid').')'
                ),
            ),
            'grp.org.fulfilments.show.crm.customers.show.invoices.show', => array_merge(
                ShowFulfilmentCustomer::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $invoice,
                    [
                        'index' => [
                            'name'       => 'grp.org.fulfilments.show.crm.customers.show.invoices.index',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment', 'fulfilmentCustomer'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.fulfilments.show.crm.customers.show.invoices.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment', 'fulfilmentCustomer', 'invoice'])
                        ]
                    ],
                    $suffix
                ),
            ),
            default => []
        };
    }

}
