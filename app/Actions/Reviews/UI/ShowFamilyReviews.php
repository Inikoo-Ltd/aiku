<?php

namespace App\Actions\Reviews\UI;

use App\Actions\Catalogue\Review\UI\IndexReviews;
use App\Actions\OrgAction;
use App\Http\Resources\Catalogue\ReviewsResource;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowFamilyReviews extends OrgAction
{
    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Shop
    {
        $this->initialisationFromShop($shop, $request);

        return $shop;
    }

    public function htmlResponse(Shop $shop, ActionRequest $request): Response
    {
        return Inertia::render('Org/Catalogue/ShopReviews', [
            'title'       => __('Family Reviews'),
            'breadcrumbs' => $this->getBreadcrumbs(
                $request->route()->getName(),
                $request->route()->originalParameters()
            ),
            'pageHead' => [
                'title' => __('Family Reviews'),
                'model' => __('Shop'),
                'icon'  => [
                    'icon'  => ['fal', 'fa-folder'],
                    'title' => __('Family Reviews'),
                ],
            ],
            'data' => [
                'data'            => ReviewsResource::collection(
                    IndexReviews::make()->handle($shop, scope: 'family')
                ),
                'reviewable_type' => 'product_category_reviews',
                'replier_type'    => 'merchant',
                'rating_labels'   => ReviewsResource::ratingLabelsFor($shop),
            ],
        ])->table(IndexReviews::make()->tableStructure(parent: $shop));
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
                        'label' => __('Family Reviews'),
                        'icon'  => 'fal fa-folder',
                    ],
                ],
            ],
        );
    }
}
