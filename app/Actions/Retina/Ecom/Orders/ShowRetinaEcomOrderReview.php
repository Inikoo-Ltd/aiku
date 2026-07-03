<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 Jun 2026 15:00:37 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Ecom\Orders;

use App\Actions\Ordering\Order\UI\IndexReviewProductsInOrder;
use App\Actions\Ordering\Order\UI\IndexReviewFamiliesInOrder;
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
use App\Http\Resources\Ordering\RetinaOrderReviewableResource;
use App\Http\Resources\Sales\OrderResource;
use App\Models\Helpers\Address;
use App\Models\Helpers\Media;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use App\Models\Reviews\Review;
use App\Models\Reviews\ReviewRatingLabel;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\Enums\Ordering\Order\OrderStateEnum;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\Catalogue\ReviewMediaResource;
use Illuminate\Support\Arr;

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
        $this->tab = $this->tab ?: RetinaOrderReviewTabsEnum::OVERALL_REVIEW->value;

        $ratingLabels = [
            ReviewContextEnum::ORDER->value   => $this->ratingLabelsForShop($order->shop_id, ReviewContextEnum::ORDER),
            ReviewContextEnum::PRODUCT->value => $this->ratingLabelsForShop($order->shop_id, ReviewContextEnum::PRODUCT),
            ReviewContextEnum::FAMILY->value  => $this->ratingLabelsForShop($order->shop_id, ReviewContextEnum::FAMILY),
        ];

        $navigation = RetinaOrderReviewTabsEnum::navigation();
        $tabLabels  = $this->shop->getCustomReviewCategoryLabel();

        $navigation = collect($navigation)->mapWithKeys(fn ($item, $key) => [
            $key    => [
                'title' => data_get($tabLabels, $item['scope'], $item['title']),
                'icon'  => $item['icon'],
            ]
        ])->toArray();

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
                    'actions' => [
                    [
                        'type'    => 'button',
                        'style'   => 'exitEdit',
                        'tooltip' => __('Back to order'),
                        'label'   => __('Back to order'),
                        'icon'    => 'fal fa-arrow-left',
                        'route'   => [
                            'name'       => 'retina.ecom.orders.show',
                            'parameters' => [
                                'order' => $order->slug,
                            ],
                        ],
                    ],
                ],
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => $navigation
                ],
                'summary' => $this->getOrderBoxStats($order),
                'review_summary' => $this->getReviewSummary($order),
                'currency'          => CurrencyResource::make($order->currency)->toArray(request()),
                'data'              => OrderResource::make($order),
                'review_settings' =>  Arr::get($order->shop->settings, 'reviews'),



                RetinaOrderReviewTabsEnum::OVERALL_REVIEW->value => $this->tab == RetinaOrderReviewTabsEnum::OVERALL_REVIEW->value ?
                    fn () => $this->getOverallReview($order)
                    : Inertia::lazy(fn () => $this->getOverallReview($order)),


                RetinaOrderReviewTabsEnum::FAMILY_REVIEWS->value => $this->tab == RetinaOrderReviewTabsEnum::FAMILY_REVIEWS->value
                    ? fn () => RetinaOrderReviewableResource::collection($this->withReviewMedia(IndexReviewFamiliesInOrder::run(order: $order, prefix: RetinaOrderReviewTabsEnum::FAMILY_REVIEWS->value)))
                        ->additional([
                            'pageHead' => [
                                'model' => __(''),
                                'title' => __('Family review'),
                                 'icon' => [
                                    'icon'  => 'fal fa-folder',
                                    'title' => __('Family review')
                                ],
                            ],
                            'order_id'      => $order->id,
                            'shop_id'       => $order->shop_id,
                            'context'       => ReviewContextEnum::FAMILY->value,
                            'scope'         => ReviewScopeEnum::FAMILY->value,
                            'rating_labels' => $ratingLabels[ReviewContextEnum::FAMILY->value],
                        ])
                    : Inertia::lazy(fn () => RetinaOrderReviewableResource::collection($this->withReviewMedia(IndexReviewFamiliesInOrder::run(order: $order, prefix: RetinaOrderReviewTabsEnum::FAMILY_REVIEWS->value)))
                        ->additional([
                            'pageHead' => [
                                'model' => __(''),
                                'title' => __('Family review'),
                                  'icon' => [
                                    'icon'  => 'fal fa-folder',
                                    'title' => __('Family review')
                                ],
                            ],
                            'order_id'      => $order->id,
                            'shop_id'       => $order->shop_id,
                            'context'       => ReviewContextEnum::FAMILY->value,
                            'scope'         => ReviewScopeEnum::FAMILY->value,
                            'rating_labels' => $ratingLabels[ReviewContextEnum::FAMILY->value],
                        ])),

                RetinaOrderReviewTabsEnum::PRODUCT_REVIEWS->value => $this->tab == RetinaOrderReviewTabsEnum::PRODUCT_REVIEWS->value
                    ? fn () => RetinaOrderReviewableResource::collection($this->withReviewMedia(IndexReviewProductsInOrder::run(order: $order, prefix: RetinaOrderReviewTabsEnum::PRODUCT_REVIEWS->value)))
                        ->additional([
                            'pageHead' => [
                                'model' => __(''),
                                'title' => __('Products review'),
                                  'icon' => [
                                    'icon'  => 'fal fa-cube',
                                    'title' => __('Products review')
                                ],
                            ],
                            'order_id' => $order->id,
                            'shop_id' => $order->shop_id,
                            'context' => ReviewContextEnum::PRODUCT->value,
                            'scope' => ReviewScopeEnum::PRODUCT->value,
                            'rating_labels' => $ratingLabels[ReviewContextEnum::PRODUCT->value],
                        ])
                    : Inertia::lazy(fn () => RetinaOrderReviewableResource::collection($this->withReviewMedia(IndexReviewProductsInOrder::run(order: $order, prefix: RetinaOrderReviewTabsEnum::PRODUCT_REVIEWS->value)))
                        ->additional([
                            'pageHead' => [
                                'model' => __(''),
                                'title' => __('Products review'),
                                'icon' => [
                                    'icon'  => 'fal fa-cube',
                                    'title' => __('Products review')
                                ],
                            ],
                            'order_id'      => $order->id,
                            'shop_id'       => $order->shop_id,
                            'context'       => ReviewContextEnum::PRODUCT->value,
                            'scope'         => ReviewScopeEnum::PRODUCT->value,
                            'rating_labels' => $ratingLabels[ReviewContextEnum::PRODUCT->value],
                        ])),
            ]
        )
            ->table(IndexReviewFamiliesInOrder::make()->tableStructure(prefix: RetinaOrderReviewTabsEnum::FAMILY_REVIEWS->value))
            ->table(IndexReviewProductsInOrder::make()->tableStructure(prefix: RetinaOrderReviewTabsEnum::PRODUCT_REVIEWS->value));
    }

    private function withReviewMedia(mixed $paginator): mixed
    {
        $reviewIds = collect($paginator->items())
            ->pluck('review_id')
            ->filter()
            ->unique()
            ->values();

        if ($reviewIds->isEmpty()) {
            return $paginator;
        }

        $mediaByReviewId = Media::query()
            ->where('model_type', (new Review())->getMorphClass())
            ->whereIn('model_id', $reviewIds)
            ->where('collection_name', 'review_images')
            ->get()
            ->groupBy('model_id');

        return $paginator->through(function ($item) use ($mediaByReviewId) {
            $item->review_images = $mediaByReviewId->get($item->review_id, collect());
            return $item;
        });
    }

    public function getOverallReview(Order $order): array
    {
        $existingReview = Review::query()
            ->where('order_id', $order->id)
            ->where('scope', ReviewScopeEnum::ORDER->value)
            ->first();

        $reviewImages = $existingReview
            ? ReviewMediaResource::collection($existingReview->media)->toArray(request())
            : [];

        return [
            'pageHead'    => [
                'model'         => __(''),
                'title'         => __('Overall review'),
                'icon' => [
                    'icon'  => 'fal fa-star',
                    'title' => __('Overall review')
                ],
            ],
            'review_id'       => $existingReview?->id,
            'status'          => $existingReview?->review_status?->value,
            'rating'          => $existingReview?->rating_main !== null ? (float) $existingReview->rating_main : null,
            'rating_a'        => $existingReview?->rating_a !== null ? (int) $existingReview->rating_a : null,
            'rating_b'        => $existingReview?->rating_b !== null ? (int) $existingReview->rating_b : null,
            'rating_c'        => $existingReview?->rating_c !== null ? (int) $existingReview->rating_c : null,
            'rating_d'        => $existingReview?->rating_d !== null ? (int) $existingReview->rating_d : null,
            'rating_e'        => $existingReview?->rating_e !== null ? (int) $existingReview->rating_e : null,
            'message'         => $existingReview?->message,
            'is_public'       => $existingReview ? (bool) $existingReview->is_public : true,
            'review_images'   => $reviewImages,
            'scope'         => ReviewScopeEnum::ORDER->value,
            'reviewable_id' => $order->id,
            'order_id'        => $order->id,
            'rating_labels'   => $this->ratingLabelsForShop($order->shop_id, ReviewContextEnum::ORDER),
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
            'total_likes'          => (int) $reviewStats->sum('total_likes'),
            'total_dislikes'       => (int) $reviewStats->sum('total_dislikes'),
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
