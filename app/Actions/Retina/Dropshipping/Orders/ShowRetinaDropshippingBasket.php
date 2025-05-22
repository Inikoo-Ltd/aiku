<?php

/*
 * author Arya Permana - Kirin
 * created on 14-05-2025-13h-53m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\Accounting\Invoice\UI\IndexInvoices;
use App\Actions\Dispatching\DeliveryNote\UI\IndexDeliveryNotes;
use App\Actions\Helpers\Media\UI\IndexAttachments;
use App\Actions\Ordering\Order\UI\GetOrderAddressManagement;
use App\Actions\Ordering\Order\UI\ShowOrder;
use App\Actions\Ordering\Transaction\UI\IndexNonProductItems;
use App\Actions\Ordering\Transaction\UI\IndexTransactions;
use App\Actions\Retina\Dropshipping\Basket\UI\IndexRetinaBaskets;
use App\Actions\RetinaAction;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\UI\Ordering\OrderTabsEnum;
use App\Http\Resources\Accounting\InvoicesResource;
use App\Http\Resources\Ordering\TransactionsResource;
use App\Http\Resources\Sales\OrderResource;
use App\Models\Ordering\Order;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\Http\Resources\Dispatching\DeliveryNotesResource;
use App\Http\Resources\Helpers\Attachment\AttachmentsResource;
use App\Http\Resources\Helpers\CurrencyResource;
use App\Http\Resources\Ordering\NonProductItemsResource;
use App\Models\Dropshipping\CustomerSalesChannel;

class ShowRetinaDropshippingBasket extends RetinaAction
{
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
        $this->initialisation($request)->withTab(OrderTabsEnum::values());

        return $this->handle($order);
    }

    public function htmlResponse(Order $order, ActionRequest $request): Response
    {
        $nonProductItems = NonProductItemsResource::collection(IndexNonProductItems::run($order));

        $action = [];

        return Inertia::render(
            'Dropshipping/RetinaDropshippingBasket',
            [
                'title'       => __('order'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $order,
                    $request->route()->originalParameters(),
                ),
                'pageHead'    => [
                    'title'   => $order->reference,
                    'model'   => __('Basket'),
                    'icon'    => [
                        'icon'  => 'fal fa-shopping-basket',
                        'title' => __('customer client')
                    ],
                    'afterTitle' => [
                        'label' => ' @'.$this->platform->name
                    ],
                    'actions' => $action,
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => OrderTabsEnum::navigation()
                ],

                'routes'    => [
                    'update_route' => [
                        'name'       => 'retina.models.order.update',
                        'parameters' => [
                            'order' => $order->id
                        ],
                        'method'     => 'patch'
                    ],
                    'submit_route' => [
                        'name'       => 'retina.models.order.submit',
                        'parameters' => [
                            'order' => $order->id
                        ],
                        'method'     => 'patch'
                    ],
                    'pay_with_balance'      => [
                        'name'       => 'retina.models.order.pay_with_balance',
                        'parameters' => [
                            'order' => $order->id
                        ],
                        'method'     => 'patch'
                    ],
                ],

                'upload_spreadsheet' => [
                    'title' => [
                        'label' => __('Upload product'),
                        'information' => __('The list of column file') . ": code, quantity"
                    ],
                    'progressDescription'   => __('Adding Products'),
                    'preview_template'    => [
                        'header' => ['code', 'quantity'],
                        'rows' => [
                            [
                                'code' => 'product-001',
                                'quantity' => '1'
                            ]
                        ]
                    ],
                    'upload_spreadsheet'    => [
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
                                    'order'        => $order->slug
                                ]
                            ],
                        ],
                    ]
                ],

                'address_management' => GetOrderAddressManagement::run(order: $order, isRetina:true),

                'box_stats'      => ShowOrder::make()->getOrderBoxStats($order),
                'currency'       => CurrencyResource::make($order->currency)->toArray(request()),
                'data'           => OrderResource::make($order),
                'is_in_basket'   => OrderStateEnum::CREATING == $order->state,
                'balance'        => $order->customer?->balance,
                'total_to_pay'   => max(0, $order->total_amount - $order->customer->balance),




                OrderTabsEnum::TRANSACTIONS->value => $this->tab == OrderTabsEnum::TRANSACTIONS->value ?
                    fn () => TransactionsResource::collection(IndexTransactions::run(parent: $order, prefix: OrderTabsEnum::TRANSACTIONS->value))
                    : Inertia::lazy(fn () => TransactionsResource::collection(IndexTransactions::run(parent: $order, prefix: OrderTabsEnum::TRANSACTIONS->value))),

                 OrderTabsEnum::INVOICES->value => $this->tab == OrderTabsEnum::INVOICES->value ?
                     fn () => InvoicesResource::collection(IndexInvoices::run(parent: $order, prefix: OrderTabsEnum::TRANSACTIONS->value))
                     : Inertia::lazy(fn () => InvoicesResource::collection(IndexInvoices::run(parent: $order, prefix: OrderTabsEnum::TRANSACTIONS->value))),

                 OrderTabsEnum::DELIVERY_NOTES->value => $this->tab == OrderTabsEnum::DELIVERY_NOTES->value ?
                     fn () => DeliveryNotesResource::collection(IndexDeliveryNotes::run(parent: $order, prefix: OrderTabsEnum::DELIVERY_NOTES->value))
                     : Inertia::lazy(fn () => DeliveryNotesResource::collection(IndexDeliveryNotes::run(parent: $order, prefix: OrderTabsEnum::DELIVERY_NOTES->value))),

                 OrderTabsEnum::ATTACHMENTS->value => $this->tab == OrderTabsEnum::ATTACHMENTS->value ?
                     fn () => AttachmentsResource::collection(IndexAttachments::run(parent: $order, prefix: OrderTabsEnum::DELIVERY_NOTES->value))
                     : Inertia::lazy(fn () => AttachmentsResource::collection(IndexAttachments::run(parent: $order, prefix: OrderTabsEnum::DELIVERY_NOTES->value))),

            ]
        )
            ->table(
                IndexTransactions::make()->tableStructure(
                    parent: $order,
                    tableRows: $nonProductItems,
                    prefix: OrderTabsEnum::TRANSACTIONS->value
                )
            )
            ->table(IndexInvoices::make()->tableStructure(
                parent: $order,
                prefix: OrderTabsEnum::INVOICES->value
            ))
            ->table(IndexAttachments::make()->tableStructure(
                prefix: OrderTabsEnum::ATTACHMENTS->value
            ))
            ->table(IndexDeliveryNotes::make()->tableStructure(
                parent: $order,
                prefix: OrderTabsEnum::DELIVERY_NOTES->value
            ));
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
                        'name' => 'retina.dropshipping.customer_sales_channels.basket.index',
                        'parameters' => [
                            $customerSalesChannel->slug
                        ]
                    ],
                    'model' => [
                        'name' => 'retina.dropshipping.customer_sales_channels.basket.index',
                        'parameters' => [$customerSalesChannel->slug, $order->slug]
                    ]
                ],
                $suffix
            ),
        );
    }
}
