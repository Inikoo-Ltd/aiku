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
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowOffer extends OrgAction
{
    protected Group|Shop|OfferCampaign $parent;
    protected Offer $offer;

    public function handle(Group|Shop|OfferCampaign $parent, Offer $offer, $prefix = null): Offer
    {
        $this->offer = $offer;
        return $offer;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->parent instanceof Group) {
            return $request->user()->authTo("group-overview");
        }
        $this->canEdit = $request->user()->authTo("discounts.{$this->shop->id}.edit");

        return $request->user()->authTo("discounts.{$this->shop->id}.view");
    }

    public function inGroup(ActionRequest $request): Offer
    {
        // $this->parent = group();
        // $this->initialisationFromGroup(group(), $request);

        // return $this->handle(parent: group());
    }

    public function jsonResponse(Offer $offer): AnonymousResourceCollection
    {
        return $offer;
    }

    public function htmlResponse(Offer $offer, ActionRequest $request): Response
    {
        $afterTitle      = $offer->code;
        $icon       = ['fal', 'fa-badge-percent'];
        $iconRight  = null;

        if ($this->parent instanceof Shop) {
            $actions[] = [
                'type'  => 'button',
                'style' => 'edit',
                'route' => [
                    'name'       => 'grp.org.shops.show.discounts.campaigns.offer.edit',
                    'parameters' => $request->route()->originalParameters()
                ],
            ];
        }

        preg_match('/^all_products_in_product_category(?::(\d+))?:/', $offer->allowance_signature, $m);
        $productCategory = isset($m[1]) ? ProductCategory::find($m[1]) : null;

        return Inertia::render(
            'Org/Discounts/Offer',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $offer,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Offer') . ' ' . $offer->code,
                'pageHead'    => [
                    'title'      => $offer->name,
                    'model'      => __('Offer'),
                    // 'titleRight'    => $afterTitle,
                    // 'afterTitle' => [
                    //     'label' => $afterTitle
                    // ],
                    'iconRight'  => $iconRight,
                    'icon'       => $icon,
                    'actions'    => app()->environment('local') ? $actions : [],
                ],
                'url_master'    =>  $productCategory && $offer->type === 'Category Quantity Ordered Order Interval' ? [
                    'name'  => 'grp.masters.master_shops.show.master_families.edit',
                    'parameters' => [
                        'masterShop'        => $offer->shop->masterShop->slug,
                        'masterFamily'     => $productCategory->masterProductCategory->slug,
                        'section'           => '5'
                    ]
                ] : [],
                'data'        => OfferResource::make($offer),
                'currency_code' => $offer->shop->currency->code,
            ]
        );
    }

    public function asController(Organisation $organisation, Shop $shop, Offer $offer, ActionRequest $request): Offer
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);
        return $this->handle($shop, $offer);
    }

    public function inOfferCampaign(Organisation $organisation, Shop $shop, OfferCampaign $offerCampaign, Offer $offer, ActionRequest $request): Offer
    {
        if($offer->type != "Category Quantity Ordered") {
            abort(404);
        }

        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $offer);
    }

    public function inGiftCampaign(Organisation $organisation, Shop $shop, OfferCampaign $offerCampaign, Offer $offer, ActionRequest $request): Offer
    {
        if($offer->type != "VolGr Gift") {
            abort(404);
        }

        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $offer);
    }

    public function inAmnestyCampaign(Organisation $organisation, Shop $shop, OfferCampaign $offerCampaign, Offer $offer, ActionRequest $request): Offer{
        if($offer->type != "GR Amnesty"){
            abort(404);
        }

        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $offer);
    } 

    public function getBreadcrumbs(Offer $offer, string $routeName, array $routeParameters, string|null $suffix = null): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Orders'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.shops.show.discounts.campaigns.amnesty.show'  => 
            array_merge(
                ShowOfferCampaign::make()->getBreadcrumbs($offer->offerCampaign, $routeName, $routeParameters, $suffix),
                [
                    [
                        'type'           => 'modelWithIndex',
                        'modelWithIndex' => [
                            'index' => [
                                'route' => [
                                    'name'       => 'grp.org.shops.show.discounts.campaigns.show',
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
                        'suffix' => $suffix,
                    ],
                ]
            ),
            'grp.org.shops.show.discounts.campaigns.gift.show'  => 
            array_merge(
                ShowOfferCampaign::make()->getBreadcrumbs($offer->offerCampaign, $routeName, $routeParameters, $suffix),
                [
                    [
                        'type'           => 'modelWithIndex',
                        'modelWithIndex' => [
                            'index' => [
                                'route' => [
                                    'name'       => 'grp.org.shops.show.discounts.campaigns.show',
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
                        'suffix' => $suffix,
                    ],
                ]
            ),
            'grp.org.shops.show.discounts.campaigns.offer.show'  => 
            array_merge(
                ShowOfferCampaign::make()->getBreadcrumbs($offer->offerCampaign, $routeName, $routeParameters, $suffix),
                [
                    [
                        'type'           => 'modelWithIndex',
                        'modelWithIndex' => [
                            'index' => [
                                'route' => [
                                    'name'       => 'grp.org.shops.show.discounts.campaigns.show',
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
                        'suffix' => $suffix,
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
                        'suffix' => $suffix,
                    ],
                ]
            )
        };
    }
}
