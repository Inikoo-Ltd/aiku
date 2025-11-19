<?php

/*
 * author Louis Perez
 * created on 19-11-2025-13h-31m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Actions\Discounts\Offer\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\OrgAction;
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
        $title      = $this->offer->slug;
        $icon       = ['fal', 'fa-badge-percent'];
        $afterTitle = null;
        $iconRight  = null;

        if ($this->parent instanceof Shop) {
            $actions[] = [
                'type'  => 'button',
                'style' => 'edit',
                'label' => __('Edit'),
                'route' => [
                    'name'       => 'grp.org.shops.show.discounts.offers.edit',
                    'parameters' => $request->route()->originalParameters()
                ],
                'icon' => ['fal', 'fa-sliders-h']
            ];
        }

        return Inertia::render(
            'Org/Discounts/Offer',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $offer,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Offers'),
                'pageHead'    => [
                    'title'      => $title,
                    'model'      => __('Offers'),
                    'afterTitle' => $afterTitle,
                    'iconRight'  => $iconRight,
                    'icon'       => $icon,
                    'actions'    => $actions
                ],
                'data'        => $offer,
            ]
        );
    }

    public function asController(Organisation $organisation, Shop $shop, Offer $offer, ActionRequest $request): Offer
    {
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
