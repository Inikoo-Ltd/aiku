<?php

/*
 * author Louis Perez
 * created on 19-11-2025-13h-31m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Actions\Discounts\Offer\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\Discounts\OfferCampaign\UI\ShowOfferCampaign;
use App\Actions\OrgAction;
use App\Http\Resources\Catalogue\OfferResource;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferCampaign;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowOffer extends OrgAction
{
    public function handle(Offer $offer): Offer
    {
        return $offer;
    }


    public function htmlResponse(Offer $offer, ActionRequest $request): Response
    {
        $icon      = ['fal', 'fa-badge-percent'];
        $iconRight = null;

        $editRouteName = match ($request->route()->getName()) {
            'grp.org.shops.show.discounts.campaigns.gift.show' => 'grp.org.shops.show.discounts.campaigns.gift.edit',
            'grp.org.shops.show.discounts.campaigns.amnesty.show' => 'grp.org.shops.show.discounts.campaigns.amnesty.edit',
            'grp.org.shops.show.discounts.campaigns.offer.show' => 'grp.org.shops.show.discounts.campaigns.offer.edit',
            default => 'grp.org.shops.show.discounts.offers.edit',
        };

        $actions[] = [
            'type'  => 'button',
            'style' => 'edit',
            'route' => [
                'name'       => $editRouteName,
                'parameters' => $request->route()->originalParameters()
            ],
        ];


        preg_match('/^all_products_in_product_category(?::(\d+))?:/', $offer->allowance_signature, $m);
        $productCategory = isset($m[1]) ? ProductCategory::find($m[1]) : null;


        $vueComponent = match ($offer->type) {
            'VolGr Gift' => 'Org/Discounts/VolGrGiftOffer',
            default => 'Org/Discounts/Offer'
        };


        $data = OfferResource::make($offer);

        return Inertia::render(
            $vueComponent,
            [
                'breadcrumbs'   => $this->getBreadcrumbs(
                    $offer,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'         => __('Offer').' '.$offer->code,
                'pageHead'      => [
                    'title'     => $offer->name,
                    'model'     => __('Offer'),
                    'iconRight' => $iconRight,
                    'icon'      => $icon,
                    'actions'   => app()->environment('local') ? $actions : [],
                ],
                'url_master'    => $productCategory && $offer->type === 'Category Quantity Ordered Order Interval' ? [
                    'name'       => 'grp.masters.master_shops.show.master_families.edit',
                    'parameters' => [
                        'masterShop'   => $offer->shop->masterShop->slug,
                        'masterFamily' => $productCategory->masterProductCategory->slug,
                        'section'      => '5'
                    ]
                ] : [],
                'data'          => $data,
                'currency_code' => $offer->shop->currency->code,
            ]
        );
    }

    public function asController(Organisation $organisation, Shop $shop, Offer $offer, ActionRequest $request): Offer
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($offer);
    }


    public function inOfferCampaign(Organisation $organisation, Shop $shop, OfferCampaign $offerCampaign, Offer $offer, ActionRequest $request): Offer
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($offer);
    }

    public function inGiftCampaign(Organisation $organisation, Shop $shop, OfferCampaign $offerCampaign, Offer $offer, ActionRequest $request): Offer
    {
        if ($offer->type != "VolGr Gift") {
            abort(404);
        }

        $this->initialisationFromShop($shop, $request);

        return $this->handle($offer);
    }

    public function inAmnestyCampaign(Organisation $organisation, Shop $shop, OfferCampaign $offerCampaign, Offer $offer, ActionRequest $request): Offer
    {
        if ($offer->type != "GR Amnesty") {
            abort(404);
        }


        $this->initialisationFromShop($shop, $request);

        return $this->handle($offer);
    }

    public function getBreadcrumbs(Offer $offer, string $routeName, array $routeParameters, string|null $suffix = null): array
    {
        return match ($routeName) {
            'grp.org.shops.show.discounts.campaigns.amnesty.show' =>
            array_merge(
                ShowOfferCampaign::make()->getBreadcrumbs($offer->offerCampaign, $routeName, $routeParameters),
                [
                    [
                        'type'           => 'modelWithIndex',
                        'modelWithIndex' => [
                            'index' => [
                                'route' => [
                                    'name'       => 'grp.org.shops.show.discounts.campaigns.show',
                                    'parameters' => [
                                        'tab' => 'gr_amnesty',
                                        ...$routeParameters
                                    ]
                                ],
                                'label' => __('Offers'),
                                'icon'  => 'fal fa-bars'
                            ],
                            'model' => [
                                'route' => [
                                    'name'       => $routeName,
                                    'parameters' => $routeParameters,
                                ],
                                'label' => $offer->name,
                            ],
                        ],
                        'suffix'         => $suffix,
                    ],
                ]
            ),
            'grp.org.shops.show.discounts.campaigns.gift.show' =>
            array_merge(
                ShowOfferCampaign::make()->getBreadcrumbs($offer->offerCampaign, $routeName, $routeParameters),
                [
                    [
                        'type'           => 'modelWithIndex',
                        'modelWithIndex' => [
                            'index' => [
                                'route' => [
                                    'name'       => 'grp.org.shops.show.discounts.campaigns.show',
                                    'parameters' => [
                                        'tab' => 'gr_gift',
                                        ...$routeParameters
                                    ]
                                ],
                                'label' => __('Offers'),
                                'icon'  => 'fal fa-bars'
                            ],
                            'model' => [
                                'route' => [
                                    'name'       => $routeName,
                                    'parameters' => $routeParameters,
                                ],
                                'label' => $offer->name,
                            ],
                        ],
                        'suffix'         => $suffix,
                    ],
                ]
            ),
            'grp.org.shops.show.discounts.campaigns.offer.show' =>
            array_merge(
                ShowOfferCampaign::make()->getBreadcrumbs($offer->offerCampaign, $routeName, $routeParameters),
                [
                    [
                        'type'           => 'modelWithIndex',
                        'modelWithIndex' => [
                            'index' => [
                                'route' => [
                                    'name'       => 'grp.org.shops.show.discounts.campaigns.show',
                                    'parameters' => [
                                        'tab' => 'offers',
                                        ...$routeParameters
                                    ]
                                ],
                                'label' => __('Offers'),
                                'icon'  => 'fal fa-bars'
                            ],
                            'model' => [
                                'route' => [
                                    'name'       => $routeName,
                                    'parameters' => $routeParameters,
                                ],
                                'label' => $offer->name,
                            ],
                        ],
                        'suffix'         => $suffix,
                    ],
                ]
            ),
            'grp.org.shops.show.discounts.offers.show' =>
            array_merge(
                ShowShop::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type'           => 'modelWithIndex',
                        'modelWithIndex' => [
                            'index' => [
                                'route' => [
                                    'name'       => 'grp.org.shops.show.discounts.offers.index',
                                    'parameters' => $routeParameters
                                ],
                                'label' => __('Offers'),
                                'icon'  => 'fal fa-bars'
                            ],
                            'model' => [
                                'route' => [
                                    'name'       => $routeName,
                                    'parameters' => $routeParameters,
                                ],
                                'label' => $offer->name,
                            ],
                        ],
                        'suffix'         => $suffix,
                    ],
                ]
            )
        };
    }
}
