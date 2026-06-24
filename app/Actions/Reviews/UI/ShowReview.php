<?php

namespace App\Actions\Reviews\UI;

use App\Actions\Catalogue\Review\UI\IndexReviews;
use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\OrgAction;
use App\Enums\UI\Catalogue\ShopReviewsTabsEnum;
use App\Http\Resources\Catalogue\ReviewsResource;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowReview extends OrgAction
{
    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Shop
    {
        $this->initialisationFromShop($shop, $request)->withTab(ShopReviewsTabsEnum::values());

        return $shop;
    }

    public function htmlResponse(Shop $shop, ActionRequest $request): Response
    {
        $reviewableType = [
            ShopReviewsTabsEnum::PRODUCTS->value => 'product_reviews',
            ShopReviewsTabsEnum::FAMILIES->value => 'product_category_reviews',
            ShopReviewsTabsEnum::SHOP->value     => 'shop_reviews',
        ];

        $tabData = fn (ShopReviewsTabsEnum $tab): array => [
            'data'            => ReviewsResource::collection(
                IndexReviews::make()->handle($shop, $tab->value, $tab->scope())
            ),
            'stats'           => IndexReviews::make()->getStats($shop),
            'reviewable_type' => $reviewableType[$tab->value],
            'replier_type'    => 'merchant',
        ];

        return Inertia::render('Org/Catalogue/ShopReviewsTabs', [
            'title'       => __('Reviews'),
            'breadcrumbs' => $this->getBreadcrumbs(
                $request->route()->getName(),
                $request->route()->originalParameters()
            ),
            'pageHead' => [
                'title' => __('Reviews'),
                'model' => __('Shop'),
                'icon'  => [
                    'icon'  => ['fal', 'fa-star'],
                    'title' => __('Reviews'),
                ],
            ],
            'shop_id' => $shop->id,
            'tabs'    => [
                'current'    => $this->tab,
                'navigation' => ShopReviewsTabsEnum::navigation(),
            ],

            ShopReviewsTabsEnum::PRODUCTS->value => $this->tab == ShopReviewsTabsEnum::PRODUCTS->value
                ? fn () => $tabData(ShopReviewsTabsEnum::PRODUCTS)
                : Inertia::lazy(fn () => $tabData(ShopReviewsTabsEnum::PRODUCTS)),

            ShopReviewsTabsEnum::FAMILIES->value => $this->tab == ShopReviewsTabsEnum::FAMILIES->value
                ? fn () => $tabData(ShopReviewsTabsEnum::FAMILIES)
                : Inertia::lazy(fn () => $tabData(ShopReviewsTabsEnum::FAMILIES)),

            ShopReviewsTabsEnum::SHOP->value => $this->tab == ShopReviewsTabsEnum::SHOP->value
                ? fn () => $tabData(ShopReviewsTabsEnum::SHOP)
                : Inertia::lazy(fn () => $tabData(ShopReviewsTabsEnum::SHOP)),
        ])
            ->table(IndexReviews::make()->tableStructure(parent: $shop, prefix: ShopReviewsTabsEnum::PRODUCTS->value))
            ->table(IndexReviews::make()->tableStructure(parent: $shop, prefix: ShopReviewsTabsEnum::FAMILIES->value))
            ->table(IndexReviews::make()->tableStructure(parent: $shop, prefix: ShopReviewsTabsEnum::SHOP->value));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, mixed $suffix = null): array
    {
        return array_merge(
            ShowShop::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => $routeName,
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Reviews'),
                        'icon'  => 'fal fa-star',
                    ],
                    'suffix' => $suffix,
                ],
            ],
        );
    }
}
