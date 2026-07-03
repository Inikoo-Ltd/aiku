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

class IndexOverallReviews extends OrgAction
{
    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Shop
    {
        $this->initialisationFromShop($shop, $request);

        return $shop;
    }

    public function htmlResponse(Shop $shop, ActionRequest $request): Response
    {
        $title = __('Overall Reviews');
        $tabLabels = $shop->getCustomReviewCategoryLabel();
        $alternateName = data_get($tabLabels, ReviewContextEnum::ORDER->value);
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
                    'icon'  => ['fal', 'fa-star'],
                    'title' => $title . ($displayAlternateName ? " ($alternateName)" : ''),
                ],
            ],
            'data' => [
                'data'            => ReviewsResource::collection(IndexReviews::run(parent: $shop, scope: 'overall')),
                'reviewable_type' => 'shop_reviews',
                'replier_type'    => 'merchant',
                'rating_labels'   => ReviewsResource::ratingLabelsFor($shop),
            ],
        ])->table(IndexReviews::make()->tableStructure());
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
                        'label' => __('Overall Reviews'),
                        'icon'  => 'fal fa-star',
                    ],
                ],
            ],
        );
    }
}
