<?php

/*
 * author Arya Permana - Kirin
 * created on 14-05-2025-13h-53m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\Ordering\Order\UI\GetOrderAddressManagement;
use App\Actions\Ordering\Transaction\UI\IndexNonProductItems;
use App\Actions\Ordering\Transaction\UI\IndexIndexTransactionsInBasket;
use App\Actions\Retina\Dropshipping\Basket\UI\IndexRetinaBaskets;
use App\Actions\Retina\UI\Layout\GetPlatformLogo;
use App\Actions\RetinaAction;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\UI\Ordering\BasketTabsEnum;
use App\Http\Resources\CRM\CustomerClientResource;
use App\Http\Resources\CRM\CustomerResource;
use App\Http\Resources\Helpers\AddressResource;
use App\Http\Resources\Ordering\RetinaTransactionsInBasketResource;
use App\Http\Resources\Sales\OrderResource;
use App\Http\Resources\Sales\RetinaDropshippingBasketResource;
use App\Models\Helpers\Address;
use App\Models\Ordering\Order;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\Http\Resources\Helpers\CurrencyResource;
use App\Http\Resources\Ordering\NonProductItemsResource;
use App\Models\Dropshipping\CustomerSalesChannel;

class ShowRetinaDropshippingBasket extends RetinaAction
{
    use GetPlatformLogo;

    public function handle(Order $order): Order
    {
        return $order;
    }

    public function authorize(ActionRequest $request): bool
    {
        $customerSalesChannel = $request->route('customerSalesChannel');
        if ($customerSalesChannel->customer_id == $this->customer->id) {
            return true;
        }

        return false;
    }

    public function asController(CustomerSalesChannel $customerSalesChannel, Order $order, ActionRequest $request): Order
    {
        $this->platform = $customerSalesChannel->platform;
        $this->initialisation($request)->withTab(BasketTabsEnum::values());

        return $this->handle($order);
    }

    public function htmlResponse(Order $order, ActionRequest $request): Response
    {
        $nonProductItems = NonProductItemsResource::collection(IndexNonProductItems::run($order));


        return Inertia::render(
            'Dropshipping/RetinaDropshippingBasket',
            [
                'title'       => __('Basket'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $order,
                    $request->route()->originalParameters(),
                ),
                'pageHead'    => [
                    'title'      => $order->reference,
                    'model'      => $this->platform->name,
                    'icon'       => [
                        'icon'  => 'fal fa-shopping-basket',
                        'title' => __('customer client')
                    ],
                    'afterTitle' => [
                        'label' => __('Basket')
                    ],
                    'actions'   => [
                        [
                            'type'   => 'buttonGroup',
                            'button' => [
                                [
                                    'type'    => 'button',
                                    'key'     => 'upload-add',
                                    'icon'      => 'fal fa-upload',
                                ],
                            ],
                        ],
                    ]
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => BasketTabsEnum::navigation()
                ],

                'routes' => [

                    'select_products'     => [
                        'name'       => 'retina.dropshipping.select_products_for_basket',
                        'parameters' => [
                            'order' => $order->id
                        ],
                        'method'     => 'patch'
                    ],
                    'update_route'     => [
                        'name'       => 'retina.models.order.update',
                        'parameters' => [
                            'order' => $order->id
                        ],
                        'method'     => 'patch'
                    ],
                    'submit_route'     => [
                        'name'       => 'retina.models.order.submit',
                        'parameters' => [
                            'order' => $order->id
                        ],
                        'method'     => 'patch'
                    ],
                    'pay_with_balance' => [
                        'name'       => 'retina.models.order.pay_with_balance',
                        'parameters' => [
                            'order' => $order->id
                        ],
                        'method'     => 'patch'
                    ],
                ],

                'upload_spreadsheet' => [
                    'title'               => [
                        'label'       => __('Upload product'),
                        'information' => __('The list of column file').": code, quantity"
                    ],
                    'progressDescription' => __('Adding Products'),
                    'preview_template'    => [
                        'header' => ['code', 'quantity'],
                        'rows'   => [
                            [
                                'code'     => 'product-001',
                                'quantity' => '1'
                            ]
                        ]
                    ],
                    'upload_spreadsheet'  => [
                        'event'           => 'action-progress',
                        'channel'         => 'grp.personal.'.$this->organisation->id,
                        'required_fields' => ['code', 'quantity'],
                        'template'        => [
                            'label' => 'Download template (.xlsx)'
                        ],
                        'route'           => [
                            'upload'   => [
                                'name'       => 'retina.models.order.transaction.upload',
                                'parameters' => [
                                    'order' => $order->id
                                ]
                            ],
                            'history'  => [
                                'name'       => 'retina.dropshipping.orders.recent_uploads',
                                'parameters' => [
                                    'order' => $order->slug
                                ]
                            ],
                            'download' => [
                                'name'       => 'retina.dropshipping.orders.upload_templates',
                                'parameters' => [
                                    'order' => $order->slug
                                ]
                            ],
                        ],
                    ]
                ],

                'address_management' => GetOrderAddressManagement::run(order: $order, isRetina: true),

                'box_stats'    => $this->getDropshippingBasketBoxStats($order),
                'currency'     => CurrencyResource::make($order->currency)->toArray(request()),
                'data'         => RetinaDropshippingBasketResource::make($order),
                'is_in_basket' => OrderStateEnum::CREATING == $order->state,
                'balance'      => $order->customer?->balance,
                'total_to_pay' => max(0, $order->total_amount - $order->customer->balance),
                'total_products'    => $order->transactions->whereIn('model_type', ['Product', 'Service'])->count(),

                BasketTabsEnum::TRANSACTIONS->value => $this->tab == BasketTabsEnum::TRANSACTIONS->value ?
                    fn () => RetinaTransactionsInBasketResource::collection(IndexIndexTransactionsInBasket::run(order: $order, prefix: BasketTabsEnum::TRANSACTIONS->value))
                    : Inertia::lazy(fn () => RetinaTransactionsInBasketResource::collection(IndexIndexTransactionsInBasket::run(order: $order, prefix: BasketTabsEnum::TRANSACTIONS->value))),


            ]
        )
            ->table(
                IndexIndexTransactionsInBasket::make()->tableStructure(
                    order: $order,
                    tableRows: $nonProductItems,
                    prefix: BasketTabsEnum::TRANSACTIONS->value
                )
            );
    }

    public function getDropshippingBasketBoxStats(Order $order): array
    {
        $payAmount   = $order->total_amount - $order->payment_amount;
        $roundedDiff = round($payAmount, 2);

        $estWeight = ($order->estimated_weight ?? 0) / 1000;


        $customerChannel = $order->customerSalesChannel;


        $taxCategory = $order->taxCategory;

        return [
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
            'customer_client'  => CustomerClientResource::make($order->customerClient)->getArray(),
            'customer_channel' => [
                'slug'     => $customerChannel->slug,
                'status'   => $order->customer_sales_channel_id,
                'platform' => [
                    'name'  => $customerChannel->platform->name,
                    'image' => $this->getPlatformLogo($customerChannel)
                ]
            ],
            'products'         => [
                'payment'          => [
                    'total_amount' => (float)$order->total_amount,
                    'paid_amount'  => (float)$order->payment_amount,
                    'pay_amount'   => $roundedDiff,
                ],
                'estimated_weight' => $estWeight
            ],



            'order_summary' => [
                [
                    [
                        'label'       => __('Items'),
                        'quantity'    => $order->stats->number_item_transactions,
                        'price_base'  => 'Multiple',
                        'price_total' => $order->goods_amount
                    ],
                ],
                [
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
                ],
                [
                    [
                        'label'       => __('Net'),
                        'information' => '',
                        'price_total' => $order->net_amount
                    ],
                    [
                        'label'       => $taxCategory->name,
                        'information' => '',
                        'price_total' => $order->tax_amount
                    ]
                ],
                [
                    [
                        'label'       => __('Total'),
                        'price_total' => $order->total_amount
                    ],
                ],
                'currency' => CurrencyResource::make($order->currency),
            ],
        ];
    }

    public function jsonResponse(Order $order): OrderResource
    {
        return new OrderResource($order);
    }

    public function getBreadcrumbs(Order $order, array $routeParameters, $suffix = ''): array
    {
        $headCrumb = function (Order $order, array $routeParameters, string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __($order->slug),
                    ],
                    'suffix' => $suffix,
                ],
            ];
        };

        $customerSalesChannel = CustomerSalesChannel::where('slug', $routeParameters['customerSalesChannel'])->first();

        return array_merge(
            IndexRetinaBaskets::make()->getBreadcrumbs($customerSalesChannel),
            $headCrumb(
                $order,
                [
                    'index' => [
                        'name'       => 'retina.dropshipping.customer_sales_channels.basket.index',
                        'parameters' => [
                            $customerSalesChannel->slug
                        ]
                    ],
                    'model' => [
                        'name'       => 'retina.dropshipping.customer_sales_channels.basket.index',
                        'parameters' => [$customerSalesChannel->slug, $order->slug]
                    ]
                ],
                $suffix
            ),
        );
    }
}
