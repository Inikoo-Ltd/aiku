<?php

namespace App\Actions\Reviews\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\OrgAction;
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
        $this->initialisationFromShop($shop, $request);

        return $shop;
    }

    public function htmlResponse(Shop $shop, ActionRequest $request): Response
    {
        return Inertia::render('Org/Catalogue/ShopReviewsDashboard', [
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
            'stats'         => IndexReviews::make()->getStats($shop),
            'rating_labels' => ReviewsResource::ratingLabelsFor($shop),
        ]);
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
