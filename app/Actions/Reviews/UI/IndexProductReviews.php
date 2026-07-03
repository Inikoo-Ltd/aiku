<?php

namespace App\Actions\Reviews\UI;

use App\Actions\Catalogue\Review\UI\IndexReviews;
use App\Actions\OrgAction;
use App\Enums\Catalogue\Review\ReviewContextEnum;
use App\Http\Resources\Catalogue\ReviewsResource;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexProductReviews extends OrgAction
{
    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Shop
    {
        $this->initialisationFromShop($shop, $request);

        return $shop;
    }

    public function htmlResponse(Shop $shop, ActionRequest $request): Response
    {
        $title = __('Product Reviews');
        $tabLabels = $shop->getCustomReviewCategoryLabel();
        $alternateName = data_get($tabLabels, ReviewContextEnum::PRODUCT->value);
        $displayAlternateName = $alternateName && $title != $alternateName;

        return Inertia::render('Org/Catalogue/ShopReviews', [
            'title'       => $title,
            'breadcrumbs' => $this->getBreadcrumbs(
                $request->route()->getName(),
                $request->route()->originalParameters()
            ),
            'pageHead' => [
                'title' => $title . ($displayAlternateName ? " ($alternateName)" : ''),
                'model' => __('Shop'),
                'icon'  => [
                    'icon'  => ['fal', 'fa-cube'],
                    'title' => $title . ($displayAlternateName ? " ($alternateName)" : ''),
                ],
            ],
            'data' => [
                'data'            => ReviewsResource::collection(IndexReviews::run(parent: $shop, scope: 'product')),
                'reviewable_type' => 'product_reviews',
                'replier_type'    => 'merchant',
                'rating_labels'   => ReviewsResource::ratingLabelsFor($shop),
            ],
        ])->table(IndexReviews::make()->tableStructure(withProduct: true));
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
                        'label' => __('Product Reviews'),
                        'icon'  => 'fal fa-cube',
                    ],
                ],
            ],
        );
    }
}
