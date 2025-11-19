<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 02 May 2025 14:36:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Ecom\Basket\UI;

use App\Actions\Retina\UI\Layout\GetPlatformLogo;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Http\Resources\CRM\CustomerClientResource;
use App\Http\Resources\CRM\CustomerResource;
use App\Http\Resources\Helpers\AddressResource;
use App\Http\Resources\Helpers\CurrencyResource;
use App\Models\Helpers\Address;
use App\Models\Ordering\Order;
use App\Helpers\NaturalLanguage;
use App\Http\Resources\Accounting\PaymentsResource;
use App\Http\Resources\Dispatching\ShipmentsResource;
use Illuminate\Support\Facades\DB;

trait IsOrder
{
    use GetPlatformLogo;

    public function getOrderBoxStats(Order $order): array
    {
        $taxCategory = $order->taxCategory;

        $payAmount   = $order->total_amount - $order->payment_amount;
        $roundedDiff = round($payAmount, 2);

        $estWeight = ($order->estimated_weight ?? 0) / 1000;

        $customerChannel = null;
        if ($order->customer_sales_channel_id) {
            $customerChannel = [
                'slug'     => $order->customerSalesChannel->slug,
                'status'   => $order->customer_sales_channel_id,
                'platform' => [
                    'name'  => $order->platform?->name,
                    'image' => $this->getPlatformLogo($order->customerSalesChannel->platform->code)
                ]
            ];
        }

        $invoicesData = [];
        foreach ($order->invoices as $invoice) {
            if (request()->routeIs('retina.*')) {
                $routeShow     = [
                    'name'       => 'retina.dropshipping.invoices.show',
                    'parameters' => [
                        'invoice' => $invoice->slug,
                    ]
                ];
                $routeDownload = null;
            } else {
                $routeShow = [
                    'name'       => request()->route()->getName().'.invoices.show',
                    'parameters' => array_merge(request()->route()->originalParameters(), ['invoice' => $invoice->slug])
                ];

                $routeDownload = [
                    'name'       => 'grp.org.accounting.invoices.download',
                    'parameters' => [
                        'organisation' => $order->organisation->slug,
                        'invoice'      => $invoice->slug,
                    ]
                ];
            }

            $invoicesData[] = [
                'reference' => $invoice->reference,
                'routes'    => [
                    'show'     => $routeShow,
                    'download' => $routeDownload,
                ]
            ];
        }

        $invoiceData = null;
        $invoice     = $order->invoices->first();

        //todo vika delete this
        if ($invoice) {
            if (request()->routeIs('retina.*')) {
                $routeShow     = [
                    'name'       => 'retina.dropshipping.invoices.show',
                    'parameters' => [
                        'invoice' => $invoice->slug,
                    ]
                ];
                $routeDownload = null;
            } else {
                $routeShow = [
                    'name'       => request()->route()->getName().'.invoices.show',
                    'parameters' => array_merge(request()->route()->originalParameters(), ['invoice' => $invoice->slug])
                ];

                $routeDownload = [
                    'name'       => 'grp.org.accounting.invoices.download',
                    'parameters' => [
                        'organisation' => $order->organisation->slug,
                        'invoice'      => $invoice->slug,
                    ]
                ];
            }

            $invoiceData = [
                'reference' => $invoice->reference,
                'routes'    => [
                    'show'     => $routeShow,
                    'download' => $routeDownload,
                ]
            ];
        }
        // ----------- end todo
        $customerClientData = null;

        if ($order->customerClient) {
            if ($order->customer_sales_channel_id) {
                $clientRoute = [
                    'route' => [
                        'name'       => 'grp.org.shops.show.crm.customers.show.customer_sales_channels.show.customer_clients.show',
                        'parameters' => [
                            'organisation'         => $order->organisation->slug,
                            'shop'                 => $order->shop->slug,
                            'customer'             => $order->customer->slug,
                            'customerSalesChannel' => $order->customerSalesChannel->slug,
                            'customerClient'       => $order->customerClient->ulid
                        ]
                    ]
                ];
            } else {
                $clientRoute = [];
            }


            $customerClientData = array_merge(
                CustomerClientResource::make($order->customerClient)->getArray(),
                $clientRoute
            );
        }
        $deliveryNotes     = $order->deliveryNotes;
        $deliveryNotesData = [];

        if ($deliveryNotes) {
            foreach ($deliveryNotes->sortBy('created_at') as $deliveryNote) {
                $deliveryNotesData[] = [
                    'id'        => $deliveryNote->id,
                    'slug'      => $deliveryNote->slug,
                    'reference' => $deliveryNote->reference,
                    'type'      => $deliveryNote->type,
                    'state'     => $deliveryNote->state->stateIcon()[$deliveryNote->state->value],
                    'shipments' => $deliveryNote?->shipments ? ShipmentsResource::collection($deliveryNote->shipments()->with('shipper')->get())->resolve() : null
                ];
            }
        }


        $hasDiscounts = $order->goods_amount != $order->gross_amount;

        if ($hasDiscounts) {
            $itemsData = [
                [
                    [
                        'label'       => __('Gross'),
                        'price_base'  => 'Multiple',
                        'price_total' => $order->gross_amount
                    ],
                    [
                        'label'             => __('Discounts'),
                        'label_class'       => 'text-green-600',
                        'information'       => '',
                        'price_total'       => $order->gross_amount - $order->goods_amount,
                        'price_total_class' => 'text-green-600 font-medium'
                    ],
                    [
                        'label'       => __('Items net'),
                        'information' => '',
                        'price_total' => $order->goods_amount
                    ],
                ],
            ];
        } else {
            $itemsData = [
                [
                    [
                        'label'       => __('Items'),
                        'quantity'    => $order->stats->number_item_transactions,
                        'price_base'  => 'Multiple',
                        'price_total' => $order->goods_amount
                    ],
                ]
            ];
        }


        $orderSummary = $itemsData;

        $orderSummary[] = [
            [
                'label'       => __('Charges'),
                'information' => '',
                'price_total' => $order->charges_amount
            ],
            [
                'label'       => __('Shipping'),
                'information' => '',
                'price_total' => $order->shipping_amount
            ]
        ];

        $orderSummary[] =
            [
                [
                    'label'       => __('Net'),
                    'information' => '',
                    'price_total' => $order->net_amount
                ],
                [
                    'label'       => __('Tax').' ('.$taxCategory->name.')',
                    'information' => '',
                    'price_total' => $order->tax_amount
                ]
            ];

        $orderSummary[] = [
            [
                'label'       => __('Total'),
                'price_total' => $order->total_amount
            ],
        ];


        $numberOrders = DB::table('orders')->where('customer_id', $order->customer_id)
            ->whereNotIn('state', [
                OrderStateEnum::CANCELLED->value,
                OrderStateEnum::CREATING->value,
            ])->count();
        $numberOrders = $numberOrders + 1;

        return [
            'customer_client'  => $customerClientData,
            'customer'         => array_merge(
                CustomerResource::make($order->customer)->getArray(),
                [
                    'addresses' => [
                        'delivery' => AddressResource::make($order->deliveryAddress ?? new Address()),
                        'billing'  => AddressResource::make($order->billingAddress ?? new Address())
                    ],
                    'route'     => [
                        'name'       => 'grp.org.shops.show.crm.customers.show',
                        'parameters' => [
                            'organisation' => $order->organisation->slug,
                            'shop'         => $order->shop->slug,
                            'customer'     => $order->customer->slug,
                        ]
                    ]
                ]
            ),
            'customer_channel' => $customerChannel,
            'invoices'         => $invoicesData,


            'order_properties' => [
                'weight'                 => NaturalLanguage::make()->weight($order->estimated_weight),
                'customer_order_number'  => $numberOrders,
                'customer_order_ordinal' => ordinal($numberOrders)." ".__('order'),

            ],
            'delivery_notes'   => $deliveryNotesData,
            'shipping_notes'   => $order->shipping_notes,
            'products'         => [
                'payment'          => [
                    'routes'       => [
                        'fetch_payment_accounts' => [
                            'name'       => 'grp.json.shop.payment-accounts',
                            'parameters' => [
                                'shop' => $order->shop->slug
                            ]
                        ],
                        'submit_payment'         => [
                            'name'       => 'grp.models.order.payment.store',
                            'parameters' => [
                                'order' => $order->id
                            ]
                        ]

                    ],
                    'total_amount' => (float)$order->total_amount,
                    'paid_amount'  => (float)$order->payment_amount,
                    'pay_amount'   => $roundedDiff,
                    'pay_status'   => $order->pay_status,
                ],
                'excesses_payment' => [
                    'amount'               => round($order->payment_amount - $order->total_amount, 2),
                    'route_to_add_balance' => [
                        'name'       => 'grp.models.order.return_excess_payment',
                        'parameters' => [
                            'order' => $order->id
                        ],
                        'method'     => 'post'
                    ]
                ],
                'estimated_weight' => $estWeight,
            ],

            'payments' => PaymentsResource::collection($order->payments)->toArray(request()),


            'order_summary' => $orderSummary,
            'currency'      => CurrencyResource::make($order->currency)

        ];
    }


}
