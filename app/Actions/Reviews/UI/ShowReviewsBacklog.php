<?php

namespace App\Actions\Reviews\UI;

use App\Actions\Catalogue\Review\UI\IndexReviews;
use App\Actions\OrgAction;
use App\Enums\Catalogue\Review\ReviewStateEnum;
use App\Enums\Catalogue\Review\ReviewStatusEnum;
use App\Enums\UI\Reviews\ReviewsBacklogTabsEnum;
use App\Http\Resources\Catalogue\ReviewsResource;
use App\Models\Catalogue\Shop;
use App\Models\Reviews\Review;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowReviewsBacklog extends OrgAction
{
    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Shop
    {
        $this->initialisationFromShop($shop, $request)->withTab(ReviewsBacklogTabsEnum::values());

        return $shop;
    }

    public function htmlResponse(Shop $shop, ActionRequest $request): Response
    {
        $waiting = ReviewsBacklogTabsEnum::WAITING->value;

        return Inertia::render('Org/Catalogue/ShopReviewsBacklog', [
            'title'       => __('Backlog Review'),
            'breadcrumbs' => $this->getBreadcrumbs(
                $request->route()->getName(),
                $request->route()->originalParameters()
            ),
            'pageHead' => [
                'title' => __('Backlog Review'),
                'model' => __('Shop'),
                'icon'  => [
                    'icon'  => ['fal', 'fa-tasks-alt'],
                    'title' => __('Backlog Review'),
                ],
            ],
            'tabs' => [
                'current'    => $this->tab,
                'navigation' => $this->getTabsBox($shop),
            ],

            $waiting => $this->tab == $waiting ?
                fn () => $this->getReviewsData($shop, $waiting)
                : Inertia::lazy(fn () => $this->getReviewsData($shop, $waiting)),
        ])->table(IndexReviews::make()->tableStructure(prefix: $waiting));
    }

    private function getReviewsData(Shop $shop, string $bucket): array
    {
        return [
            'data'            => ReviewsResource::collection(IndexReviews::run(parent: $shop, prefix: $bucket, bucket: $bucket)),
            'reviewable_type' => 'shop_reviews',
            'replier_type'    => 'merchant',
            'rating_labels'   => ReviewsResource::ratingLabelsFor($shop),
        ];
    }

    private function getTabsBox(Shop $shop): array
    {
        $counts = Review::query()
            ->where('shop_id', $shop->id)
            ->selectRaw(
                '
                COUNT(*) FILTER (WHERE review_status = ?) as waiting,
                COUNT(*) FILTER (WHERE state = ? AND replied = false) as unanswered,
                COUNT(*) FILTER (WHERE state = ?) as published,
                COUNT(*) FILTER (WHERE state = ? AND published_at >= ?) as published_last_24h,
                COUNT(*) FILTER (WHERE review_status = ?) as rejected
            ',
                [
                    ReviewStatusEnum::PENDING->value,
                    ReviewStateEnum::PUBLISHED->value,
                    ReviewStateEnum::PUBLISHED->value,
                    ReviewStateEnum::PUBLISHED->value,
                    now()->subDay()->toDateTimeString(),
                    ReviewStatusEnum::REJECTED->value,
                ]
            )
            ->first();

        return [
            [
                'label' => __('Waiting'),
                'tabs'  => [
                    [
                        'tab_slug'  => ReviewsBacklogTabsEnum::WAITING->value,
                        'label'     => __('Waiting'),
                        'value'     => (int) $counts->waiting,
                        'type'      => 'number',
                        'icon_data' => [
                            'icon'    => 'fal fa-clock',
                            'tooltip' => __('Waiting for approval'),
                        ],
                    ],
                ],
            ],
            [
                'label' => __('Unanswered'),
                'tabs'  => [
                    [
                        'tab_slug'  => ReviewsBacklogTabsEnum::UNANSWERED->value,
                        'label'     => __('Unanswered'),
                        'value'     => (int) $counts->unanswered,
                        'type'      => 'number',
                        'icon_data' => [
                            'icon'    => 'fal fa-reply',
                            'tooltip' => __('Published, not replied'),
                        ],
                    ],
                ],
            ],
            [
                'label' => __('History'),
                'tabs'  => [
                    [
                        'tab_slug'  => ReviewsBacklogTabsEnum::PUBLISHED->value,
                        'label'     => __('Published'),
                        'value'     => (int) $counts->published,
                        'type'      => 'number',
                        'icon_data' => [
                            'icon'    => 'fal fa-broadcast-tower',
                            'tooltip' => __('Published'),
                            'class'   => 'text-green-500',
                        ],
                    ],
                    [
                        'tab_slug'  => ReviewsBacklogTabsEnum::PUBLISHED_LAST_24H->value,
                        'label'     => __('Last 24h'),
                        'value'     => (int) $counts->published_last_24h,
                        'type'      => 'number',
                        'icon_data' => [
                            'icon'    => 'fal fa-broadcast-tower',
                            'tooltip' => __('Published in last 24 hours'),
                            'class'   => 'text-amber-500',
                        ],
                    ],
                    [
                        'tab_slug'  => ReviewsBacklogTabsEnum::REJECTED->value,
                        'label'     => __('Rejected'),
                        'value'     => (int) $counts->rejected,
                        'type'      => 'number',
                        'icon_data' => [
                            'icon'    => 'fal fa-times-circle',
                            'tooltip' => __('Rejected'),
                            'class'   => 'text-red-500',
                        ],
                    ],
                ],
            ],
        ];
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            ShowReview::make()->getBreadcrumbs('grp.org.shops.show.reviews.dashboard', $routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => $routeName,
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Backlog Review'),
                        'icon'  => 'fal fa-tasks-alt',
                    ],
                ],
            ],
        );
    }
}
