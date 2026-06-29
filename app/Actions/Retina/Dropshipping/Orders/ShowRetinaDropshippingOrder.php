<?php

/*
 * author Arya Permana - Kirin
 * created on 04-03-2025-13h-50m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\Ordering\Order\UI\GetOrderDeliveryAddressManagement;
use App\Actions\Ordering\Order\UI\IndexAllReviewsInOrder;
use App\Actions\Ordering\Order\UI\ShowOrder;
use App\Actions\Ordering\Transaction\UI\IndexNonProductItems;
use App\Actions\Ordering\Transaction\UI\IndexTransactions;
use App\Actions\Retina\UI\Layout\GetPlatformLogo;
use App\Actions\RetinaAction;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Catalogue\Review\ReviewScopeEnum;
use App\Enums\UI\Ordering\RetinaOrderTabsEnum;
use App\Helpers\NaturalLanguage;
use App\Http\Resources\Accounting\PaymentsResource;
use App\Http\Resources\CRM\CustomerClientResource;
use App\Http\Resources\CRM\CustomerResource;
use App\Http\Resources\Dispatching\RetinaShipmentsResource;
use App\Http\Resources\Helpers\AddressResource;
use App\Http\Resources\Helpers\CurrencyResource;
use App\Http\Resources\Ordering\NonProductItemsResource;
use App\Http\Resources\Ordering\RetinaOrderReviewListResource;
use App\Http\Resources\Ordering\TransactionsResource;
use App\Http\Resources\Sales\OrderResource;
use App\Models\Accounting\Invoice;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Helpers\Address;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use App\Models\Reviews\Review;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\Enums\Ordering\Order\OrderStateEnum;

class ShowRetinaDropshippingOrder extends RetinaAction
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
        $this->initialisation($request)->withTab(RetinaOrderTabsEnum::values());

        return $this->handle($order);
    }


    public function htmlResponse(Order $order, ActionRequest $request): Response
    {
        $finalTimeline = ShowOrder::make()->getOrderTimeline($order);


        $nonProductItems = NonProductItemsResource::collection(IndexNonProductItems::run($order));

        $reviewAvailable      = $order->state === OrderStateEnum::DISPATCHED;
        $hasReviews           = $reviewAvailable && Review::where('order_id', $order->id)->exists();

        $customerSalesChannel = $order->customerSalesChannel;

        $action = $reviewAvailable ? [
            [
                'type'    => 'button',
                'style'   => '',
                'tooltip' => __('Review your order and let us know how we can improve our service'),
                'label'   => __('Review'),
                'icon'    => 'fal fa-stars',
                'route'   => [
                    'name'       => 'retina.dropshipping.customer_sales_channels.orders.review',
                    'parameters' => [
                        'customerSalesChannel' => $customerSalesChannel->slug,
                        'order'                => $order->slug,
                    ],
                ],
            ],
        ] : [];

        $this->tab = $this->tab ?: RetinaOrderTabsEnum::TRANSACTIONS->value;

        return Inertia::render(
            'Dropshipping/RetinaDropshippingOrder',
            [
                'title'       => __('order'),
                'breadcrumbs' => $this->getBreadcrumbs($order),
                'pageHead'    => [
                    'title'   => $order->reference,
                    'model'   => __('Order'),
                    'icon'    => [
                        'icon'  => 'fal fa-shopping-cart',
                        'title' => __('Customer client')
                    ],
                    'actions' => $action,
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => $hasReviews
                        ? RetinaOrderTabsEnum::navigation()
                        : RetinaOrderTabsEnum::navigationExcept([RetinaOrderTabsEnum::REVIEWS]),
                ],

                'routes' => [
                    'update_route'        => [
                        'name'       => 'retina.models.order.update',
                        'parameters' => [
                            'order' => $order->id
                        ],
                        'method'     => 'patch'
                    ],
                    'submit_route'        => [
                        'name'       => 'retina.models.order.submit',
                        'parameters' => [
                            'order' => $order->id
                        ],
                        'method'     => 'patch'
                    ],
                    'route_to_pay_unpaid' => [
                        'name'       => 'retina.json.get_checkout_com_token_to_pay_order',
                        'parameters' => [
                            'order' => $order->id,
                        ],
                    ],


                ],

                'timelines' => $finalTimeline,

                'address_management' => GetOrderDeliveryAddressManagement::run(order: $order, isRetina: true),

                'box_stats' => $this->getOrderBoxStats($order),
                'currency'  => CurrencyResource::make($order->currency)->toArray(request()),
                'order'     => OrderResource::make($order),

                'is_notes_editable' => false,  // TODO: make it dynamic, only disable on 'after' state
                'review_settings'   => Arr::get($order->shop->settings, 'reviews'),
                'review_reactions'  => [
                    'likes'    => $this->customer->likeReactions,
                    'dislikes' => $this->customer->dislikeReactions,
                ],

                RetinaOrderTabsEnum::TRANSACTIONS->value => $this->tab == RetinaOrderTabsEnum::TRANSACTIONS->value ?
                    fn () => TransactionsResource::collection(IndexTransactions::run(parent: $order, prefix: RetinaOrderTabsEnum::TRANSACTIONS->value))
                    : Inertia::lazy(fn () => TransactionsResource::collection(IndexTransactions::run(parent: $order, prefix: RetinaOrderTabsEnum::TRANSACTIONS->value))),

                RetinaOrderTabsEnum::REVIEWS->value => $this->tab == RetinaOrderTabsEnum::REVIEWS->value
                    ? fn () => RetinaOrderReviewListResource::collection(IndexAllReviewsInOrder::run(order: $order, customer: $this->customer, prefix: RetinaOrderTabsEnum::REVIEWS->value))->additional(['summary' => $this->getReviewSummary($order), 'settings' => $this->getReviewSettings($order)])
                    : Inertia::lazy(fn () => RetinaOrderReviewListResource::collection(IndexAllReviewsInOrder::run(order: $order, customer: $this->customer, prefix: RetinaOrderTabsEnum::REVIEWS->value))->additional(['summary' => $this->getReviewSummary($order), 'settings' => $this->getReviewSettings($order)])),

            ]
        )
            ->table(
                IndexTransactions::make()->tableStructure(
                    parent: $order,
                    tableRows: $nonProductItems,
                    prefix: RetinaOrderTabsEnum::TRANSACTIONS->value
                )
            )
            ->table(
                IndexAllReviewsInOrder::make()->tableStructure(
                    prefix: RetinaOrderTabsEnum::REVIEWS->value
                )
            );
    }

    public function jsonResponse(Order $order): OrderResource
    {
        return new OrderResource($order);
    }

    private function getReviewSettings(Order $order): array
    {
        $settings = $order->shop->settings;

        return [
            'allow_reactions'       => (bool) data_get($settings, 'reviews.allow_reactions', true),
            'allow_reply_reactions' => (bool) data_get($settings, 'reviews.allow_reply_reactions', true),
        ];
    }

    private function getReviewSummary(Order $order): array
    {
        $reviewStats = Review::query()
            ->where('order_id', $order->id)
            ->selectRaw('scope, COUNT(*) as count, AVG(rating_main) as avg_rating, SUM(likes) as total_likes, SUM(dislikes) as total_dislikes')
            ->groupBy('scope')
            ->get()
            ->keyBy('scope');

        $totalProducts = Transaction::query()
            ->where('order_id', $order->id)
            ->where('model_type', 'Product')
            ->distinct('model_id')
            ->count('model_id');

        $totalFamilies = Transaction::query()
            ->where('order_id', $order->id)
            ->where('model_type', 'Product')
            ->whereNotNull('family_id')
            ->distinct('family_id')
            ->count('family_id');

        $overallAvg = Review::query()
            ->where('order_id', $order->id)
            ->avg('rating_main');

        return [
            'overall_review'       => (int) ($reviewStats->get(ReviewScopeEnum::ORDER->value)?->count ?? 0),
            'product_review'       => (int) ($reviewStats->get(ReviewScopeEnum::PRODUCT->value)?->count ?? 0),
            'total_product_review' => $totalProducts,
            'family_review'        => (int) ($reviewStats->get(ReviewScopeEnum::FAMILY->value)?->count ?? 0),
            'total_family_review'  => $totalFamilies,
            'average_review'       => $overallAvg ? round((float) $overallAvg, 1) : 0.0,
        ];
    }

    public function getBreadcrumbs(Order $order): array
    {
        return array_merge(
            IndexRetinaDropshippingOrders::make()->getBreadcrumbs($order->customerSalesChannel),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'retina.dropshipping.customer_sales_channels.orders.show',
                            'parameters' => [
                                'customerSalesChannel' => $order->customerSalesChannel->slug,
                                'order'                => $order->slug
                            ]
                        ],
                        'label' => $order->reference,
                    ]
                ]
            ]
        );
    }

    public function getOrderBoxStats(Order $order): array
    {
        $totalToPay = $order->total_amount;
        /** @var Invoice $refund */
        foreach (Invoice::where('order_id', $order->id)->where('type', InvoiceTypeEnum::REFUND)->where('in_process', false)->get() as $refund) {
            $totalToPay += $refund->total_amount;
        };

        $payAmount   = $totalToPay - $order->payment_amount;
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
            $routeShow = [
                'name'       => 'retina.dropshipping.invoices.show',
                'parameters' => [
                    'invoice' => $invoice->slug,
                ],
            ];

            $routeDownload = [
                'name'       => 'retina.dropshipping.invoices.pdf',
                'parameters' => [
                    'invoice' => $invoice->slug,
                ],
            ];

            $invoicesData[] = [
                'reference' => $invoice->reference,
                'routes'    => [
                    'show'     => $routeShow,
                    'download' => $routeDownload,
                ],
            ];
        }

        $customerClientData = null;

        if ($order->customerClient) {
            $customerClientData = array_merge(
                CustomerClientResource::make($order->customerClient)->getArray(),
                [
                    'route' => [
                        'name'       => 'grp.org.shops.show.crm.customers.show.customer_sales_channels.show.customer_clients.show',
                        'parameters' => [
                            'organisation'         => $order->organisation->slug,
                            'shop'                 => $order->shop->slug,
                            'customer'             => $order->customer->slug,
                            'customerSalesChannel' => $order->customerSalesChannel->slug,
                            'customerClient'       => $order->customerClient->ulid
                        ]
                    ],
                    'recipient_name' => Arr::get($order->data, 'woo_order.shipping.first_name') . ' ' . Arr::get($order->data, 'woo_order.shipping.last_name')
                ]
            );
        }

        $deliveryNotes     = $order->deliveryNotes;
        $deliveryNotesData = [];

        if ($deliveryNotes) {
            foreach ($deliveryNotes as $deliveryNote) {
                $routeDownload = [
                    'name'       => 'retina.dropshipping.packing_lists.pdf',
                    'parameters' => [
                        'deliveryNote' => $deliveryNote->slug,
                    ],
                ];

                $deliveryNotesData[] = [
                    'id'        => $deliveryNote->id,
                    'reference' => $deliveryNote->reference,
                    'state'     => $deliveryNote->state->stateIcon()[$deliveryNote->state->value],
                    'shipments' => $deliveryNote?->shipments ? RetinaShipmentsResource::collection($deliveryNote->shipments()->with('shipper')->get())->resolve() : null,
                    'routes'    => [
                        'download' => $routeDownload,
                    ],
                ];
            }
        }


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
                'weight' => NaturalLanguage::make()->weight($order->estimated_weight),
            ],
            'delivery_notes'   => $deliveryNotesData,
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
                'estimated_weight' => $estWeight,
            ],

            'payments' => PaymentsResource::collection($order->payments)->toArray(request()),

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
                        'label'       => __('Tax').' '.$order->taxCategory->name,
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

}
