<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 02 May 2025 14:36:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Ecom\Basket\UI;

use App\Actions\Retina\UI\Layout\GetPlatformLogo;
use App\Http\Resources\CRM\CustomerClientResource;
use App\Http\Resources\CRM\CustomerResource;
use App\Http\Resources\Helpers\AddressResource;
use App\Http\Resources\Helpers\CurrencyResource;
use App\Models\Helpers\Address;
use App\Models\Ordering\Order;
use App\Helpers\NaturalLanguage;

trait IsOrder
{
    use GetPlatformLogo;

    public function getOrderBoxStats(Order $order): array
    {

        $payAmount   = $order->total_amount - $order->payment_amount;
        $roundedDiff = round($payAmount, 2);

        $estWeight = ($order->estimated_weight ?? 0) / 1000;

        $customerChannel = null;
        if ($order->customer_sales_channel_id) {
            $customerChannel = [
                'slug'      => $order->customerSalesChannel->slug,
                'status'    => $order->customer_sales_channel_id,
                'platform'  => [
                    'name' => $order->platform?->name,
                    'image' => $this->getPlatformLogo($order->customerSalesChannel)
                ]
            ];
        }

        $invoiceData = null;
        $invoice = $order->invoices->first();

        if ($invoice) {

            $route = [];
            match (request()->routeIs('retina.*')) {
                true => $route = [
                    'name'       => 'retina.dropshipping.invoices.show',
                    'parameters' => [
                        'invoice' => $invoice->slug,
                    ]
                ],
                default => $route = [
                    'name'       => 'grp.org.accounting.invoices.show',
                    'parameters' => [
                        'organisation'  => $order->organisation->slug,
                        'invoice'       => $invoice->slug,
                    ]
                ]
            };
            $invoiceData = [
                'reference' => $invoice->reference,
                'route'     => $route
            ];
        }
        
        return [
            'customer' => array_merge(
                $order->customerClient ? CustomerClientResource::make($order->customerClient)->getArray() : CustomerResource::make($order->customer)->getArray(),
                [
                    'addresses' => [
                        'delivery' => AddressResource::make($order->deliveryAddress ?? new Address()),
                        'billing'  => AddressResource::make($order->billingAddress ?? new Address())
                    ],
                    'route' => [
                        'name'       => 'grp.org.shops.show.crm.customers.show.customer_sales_channels.show.customer_clients.show',
                        'parameters' => [
                            'organisation'  => $order->organisation->slug,
                            'shop'          => $order->shop->slug,
                            'customer'      => $order->customer->slug,
                            'customerSalesChannel' => $order->customerSalesChannel->slug,
                            'customerClient'  => $order->customerClient->ulid
                        ]
                    ]
                ]
            ),
            'customer_client' => $order->customerClient ? CustomerClientResource::make($order->customerClient)->getArray() : [],
            'customer_channel' => $customerChannel,
            'invoice'  => $invoiceData,
            'order_properties' => [
                'weight' => NaturalLanguage::make()->weight($order->estimated_weight),
            ],
            'products' => [
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
                    'pay_status' => $order->pay_status,
                ],
                'estimated_weight' => $estWeight,
            ],

            'order_summary' => [
                [
                    [
                        'label'       => 'Items',
                        'quantity'    => $order->stats->number_item_transactions,
                        'price_base'  => 'Multiple',
                        'price_total' => $order->goods_amount
                    ],
                ],
                [
                    [
                        'label'       => 'Charges',
                        'information' => '',
                        'price_total' => $order->charges_amount
                    ],
                    [
                        'label'       => 'Shipping',
                        'information' => '',
                        'price_total' => $order->shipping_amount
                    ]
                ],
                [
                    [
                        'label'       => 'Net',
                        'information' => '',
                        'price_total' => $order->net_amount
                    ],
                    [
                        'label'       => 'Tax 20%',
                        'information' => '',
                        'price_total' => $order->tax_amount
                    ]
                ],
                [
                    [
                        'label'       => 'Total',
                        'price_total' => $order->total_amount
                    ],
                ],

                'currency' => CurrencyResource::make($order->currency),
            ],
        ];
    }
}
