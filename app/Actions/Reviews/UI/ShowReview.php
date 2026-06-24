<?php

namespace App\Actions\Reviews\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\OrgAction;
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
        return Inertia::render(
            'Devel/Dummy',
            [
                'title' => __('Reviews'),
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'pageHead' => [
                    'title' => __('Reviews'),
                    'icon' => [
                        'title' => __('Reviews'),
                        'icon'  => 'fal fa-star',
                    ],
                ],
            ]
        );
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowShop::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.reviews.dashboard',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Reviews'),
                    ],
                ],
            ],
        );
    }
}
