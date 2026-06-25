<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 Jun 2026 15:00:37 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Ecom\Orders;

use App\Actions\Ordering\Order\UI\IndexReviewProductsInOrder;
use App\Actions\Ordering\Order\UI\IndexReviewFamiliesInOrder;
use App\Actions\Ordering\Transaction\UI\IndexNonProductItems;
use App\Actions\Retina\UI\Layout\GetPlatformLogo;
use App\Actions\RetinaAction;
use App\Enums\Catalogue\Review\ReviewContextEnum;
use App\Enums\Catalogue\Review\ReviewScopeEnum;
use App\Enums\UI\Ordering\RetinaOrderReviewTabsEnum;
use App\Helpers\NaturalLanguage;
use App\Http\Resources\Accounting\PaymentsResource;
use App\Http\Resources\CRM\CustomerResource;
use App\Http\Resources\Helpers\AddressResource;
use App\Http\Resources\Helpers\CurrencyResource;
use App\Http\Resources\Ordering\NonProductItemsResource;
use App\Http\Resources\Ordering\RetinaOrderReviewableResource;
use App\Http\Resources\Sales\OrderResource;
use App\Models\Helpers\Address;
use App\Models\Ordering\Order;
use App\Models\Reviews\ReviewRatingLabel;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\Enums\Ordering\Order\OrderStateEnum;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\Helpers\ImageResource;
use App\Models\Helpers\Media;

class ShowRetinaEcomOrderReview extends RetinaAction
{
    use GetPlatformLogo;

    public function handle(Order $order): Order
    {
        return $order;
    }

    public function authorize(ActionRequest $request): bool
    {
        $order = $request->route('order');
        if ($order->customer_id == $this->customer->id) {
            return true;
        }

        return false;
    }

    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->initialisation($request)->withTab(RetinaOrderReviewTabsEnum::values());

        return $this->handle($order);
    }


    public function htmlResponse(Order $order, ActionRequest $request): Response
    {
        $nonProductItems = NonProductItemsResource::collection(IndexNonProductItems::run($order));

        $action = [];

        $this->tab = $this->tab ?: RetinaOrderReviewTabsEnum::OVERALL_REVIEW->value;

        $ratingLabels = [
            ReviewContextEnum::ORDER->value   => $this->ratingLabelsForShop($order->shop_id, ReviewContextEnum::ORDER),
            ReviewContextEnum::PRODUCT->value => $this->ratingLabelsForShop($order->shop_id, ReviewContextEnum::PRODUCT),
            ReviewContextEnum::FAMILY->value  => $this->ratingLabelsForShop($order->shop_id, ReviewContextEnum::FAMILY),
        ];


        return Inertia::render(
            'Ecom/RetinaEcomOrderReview',
            [
                'title'       => __('Review Order'),
                'breadcrumbs' => $this->getBreadcrumbs($order),
                'pageHead'    => [
                    'title'   => $order->reference,
                    'model'   => __('Review Order'),
                    'icon'    => [
                        'icon'  => 'fal fa-star',
                        'title' => __('Review Order')
                    ],
                    'actions' => $action,
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => RetinaOrderReviewTabsEnum::navigation()
                ],

                'routes'  => [


                ],
                'summary' => $this->getOrderBoxStats($order),


                'currency'          => CurrencyResource::make($order->currency)->toArray(request()),
                'data'              => OrderResource::make($order),



                RetinaOrderReviewTabsEnum::OVERALL_REVIEW->value => $this->tab == RetinaOrderReviewTabsEnum::OVERALL_REVIEW->value ?
                    fn () => $this->getOverallReview()
                    : Inertia::lazy(fn () => $this->getOverallReview()),


                RetinaOrderReviewTabsEnum::FAMILY_REVIEWS->value => $this->tab == RetinaOrderReviewTabsEnum::FAMILY_REVIEWS->value
                    ? fn () => RetinaOrderReviewableResource::collection(IndexReviewFamiliesInOrder::run(order: $order, prefix: RetinaOrderReviewTabsEnum::FAMILY_REVIEWS->value))
                        ->additional([
                            'order_id'        => $order->id,
                            'shop_id'         => $order->shop_id,
                            'context'         => ReviewContextEnum::FAMILY->value,
                            'reviewable_type' => ReviewScopeEnum::FAMILY->value,
                            'rating_labels'   => $ratingLabels[ReviewContextEnum::FAMILY->value],
                        ])
                    : Inertia::lazy(fn () => RetinaOrderReviewableResource::collection(IndexReviewFamiliesInOrder::run(order: $order, prefix: RetinaOrderReviewTabsEnum::FAMILY_REVIEWS->value))
                        ->additional([
                            'order_id'        => $order->id,
                            'shop_id'         => $order->shop_id,
                            'context'         => ReviewContextEnum::FAMILY->value,
                            'reviewable_type' => ReviewScopeEnum::FAMILY->value,
                            'rating_labels'   => $ratingLabels[ReviewContextEnum::FAMILY->value],
                        ])),

                RetinaOrderReviewTabsEnum::PRODUCT_REVIEWS->value => $this->tab == RetinaOrderReviewTabsEnum::PRODUCT_REVIEWS->value
                    ? fn () => RetinaOrderReviewableResource::collection(IndexReviewProductsInOrder::run(order: $order, prefix: RetinaOrderReviewTabsEnum::PRODUCT_REVIEWS->value))
                        ->additional([
                            'order_id'        => $order->id,
                            'shop_id'         => $order->shop_id,
                            'context'         => ReviewContextEnum::PRODUCT->value,
                            'reviewable_type' => ReviewScopeEnum::PRODUCT->value,
                            'rating_labels'   => $ratingLabels[ReviewContextEnum::PRODUCT->value],
                        ])
                    : Inertia::lazy(fn () => RetinaOrderReviewableResource::collection(IndexReviewProductsInOrder::run(order: $order, prefix: RetinaOrderReviewTabsEnum::PRODUCT_REVIEWS->value))
                        ->additional([
                            'order_id'        => $order->id,
                            'shop_id'         => $order->shop_id,
                            'context'         => ReviewContextEnum::PRODUCT->value,
                            'reviewable_type' => ReviewScopeEnum::PRODUCT->value,
                            'rating_labels'   => $ratingLabels[ReviewContextEnum::PRODUCT->value],
                        ])),
            ]
        )
            ->table(IndexReviewFamiliesInOrder::make()->tableStructure(prefix: RetinaOrderReviewTabsEnum::FAMILY_REVIEWS->value))
            ->table(IndexReviewProductsInOrder::make()->tableStructure(prefix: RetinaOrderReviewTabsEnum::PRODUCT_REVIEWS->value));
    }

    public function getOverallReview(): array
    {
        $orderId        = $this->order->id;
        $reviewableId   = (int) ($this->reviewable_id ?? 0);
        $reviewableType = ReviewScopeEnum::SHOP->value;
        $reviewMediaData = is_string($this->review_media_data)
            ? json_decode($this->review_media_data, true)
            : ($this->review_media_data ?? []);
        $reviewImages = $reviewMediaData
            ? Media::hydrate($reviewMediaData)->map(fn ($media) => ImageResource::make($media)->getArray())->values()->all()
            : [];
        $ratingLabels = [
            ReviewContextEnum::SHOP->value   => $this->ratingLabelsForShop($this->order->shop_id, ReviewContextEnum::SHOP),
        ];

        return [
                'review_id'       => $this->review_id ? (int) $this->review_id : null,
                'status'          => $this->review_status,
                'rating'          => $this->review_rating !== null ? (float) $this->review_rating : null,
                'rating_a'        => $this->review_rating_a !== null ? (int) $this->review_rating_a : null,
                'rating_b'        => $this->review_rating_b !== null ? (int) $this->review_rating_b : null,
                'rating_c'        => $this->review_rating_c !== null ? (int) $this->review_rating_c : null,
                'rating_d'        => $this->review_rating_d !== null ? (int) $this->review_rating_d : null,
                'rating_e'        => $this->review_rating_e !== null ? (int) $this->review_rating_e : null,
                'message'         => $this->review_message,
                'is_public'       => $this->review_is_public !== null ? (bool) $this->review_is_public : true,
                'review_images'   => $reviewImages,
                'reviewable_type' => $reviewableType,
                'reviewable_id'   => $reviewableId,
                'order_id'        => $orderId,
                'rating_labels'   => $ratingLabels[ReviewContextEnum::SHOP->value],
        ];
    }

    private function ratingLabelsForShop(int $shopId, ReviewContextEnum $context): array
    {
        return ReviewRatingLabel::query()
            ->whereRaw('LOWER(model_type) = ?', ['shop'])
            ->where('model_id', $shopId)
            ->whereRaw('LOWER(review_context) = ?', [$context->value])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('dimension')
            ->get(['dimension', 'label', 'is_required', 'weight'])
            ->map(fn (ReviewRatingLabel $reviewRatingLabel): array => [
                'dimension'   => $reviewRatingLabel->dimension?->value ?? (string)$reviewRatingLabel->dimension,
                'label'       => (string)$reviewRatingLabel->label,
                'is_required' => (bool)$reviewRatingLabel->is_required,
                'weight'      => (float)$reviewRatingLabel->weight,
            ])
            ->values()
            ->all();
    }

    public function jsonResponse(Order $order): OrderResource
    {
        return new OrderResource($order);
    }

    public function getBreadcrumbs(Order $order): array
    {
        return array_merge(
            IndexRetinaEcomOrders::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'retina.ecom.orders.show',
                            'parameters' => [
                                'order' => $order->slug
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
        $payAmount   = $order->total_amount - $order->payment_amount;
        $roundedDiff = round($payAmount, 2);

        $estWeight = ($order->estimated_weight ?? 0) / 1000;


        $invoicesData = [];


        $deliveryNotes     = $order->deliveryNotes;
        $deliveryNotesData = [];


        $numberOrders = DB::table('orders')->where('customer_id', $order->customer_id)
            ->whereNotIn('state', [
                OrderStateEnum::CANCELLED->value,
                OrderStateEnum::CREATING->value,
            ])->count();
        $numberOrders = $numberOrders + 1;

        return [
            'customer' => array_merge(
                CustomerResource::make($order->customer)->getArray(),
                [
                    'addresses' => [
                        'delivery' => AddressResource::make($order->deliveryAddress ?? new Address()),
                        'billing'  => AddressResource::make($order->billingAddress ?? new Address())
                    ],
                ]
            ),

            'order_properties' => [
                'weight'                         => NaturalLanguage::make()->weight($order->estimated_weight),
                'customer_order_number'          => $numberOrders,
                'customer_order_ordinal'         => ordinal($numberOrders)." ".__('order'),
                'customer_order_ordinal_tooltip' => __('This is the nth order this customer has placed with this shop.')
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
