<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 14 Feb 2025 12:52:08 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice\UI;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Accounting\Invoice;
use App\Models\Comms\Outbox;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\SysAdmin\Organisation;
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
        $actions = [];

        $trashIcon = 'fal fa-trash-alt';

        if ($this->parent instanceof Fulfilment) {
            $actions[] =
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
            $actions[] =
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
            $actions[] = [
                'type'  => 'button',
                'style' => 'edit',
                'label' => __('edit'),
                'route' => [
                    'name'       => 'grp.org.accounting.invoices.edit',
                    'parameters' => $request->route()->originalParameters()
                ],
            ];
        } elseif ($this->parent instanceof FulfilmentCustomer) {
            $actions[] = [
                'type'  => 'button',
                'style' => 'edit',
                'label' => __('edit'),
                'route' => [
                    'name'       => 'grp.org.fulfilments.show.crm.customers.show.invoices.edit',
                    'parameters' => $request->route()->originalParameters()
                ],
            ];
        }


        $actions[] =
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
            $actions[] =
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

        return $actions;
    }

}
