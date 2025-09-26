<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 28 May 2024 12:06:23 British Summer Time, Plane Manchester-Malaga
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\OfferCampaign\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\Discounts\Offer\UI\IndexOffers;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\OrgAction;
use App\Enums\UI\Discounts\OfferCampaignTabsEnum;
use App\Http\Resources\Catalogue\OfferCampaignResource;
use App\Http\Resources\Catalogue\OffersResource;
use App\Http\Resources\History\HistoryResource;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\OfferCampaign;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowOfferCampaign extends OrgAction
{
    public function handle(OfferCampaign $offerCampaign): OfferCampaign
    {
        return $offerCampaign;
    }

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->authTo("discounts.{$this->shop->id}.edit");

        return $request->user()->authTo("discounts.{$this->shop->id}.view");
    }

    public function asController(Organisation $organisation, Shop $shop, OfferCampaign $offerCampaign, ActionRequest $request): OfferCampaign
    {
        $this->initialisationFromShop($shop, $request)->withTab(OfferCampaignTabsEnum::values());

        return $this->handle($offerCampaign);
    }

    public function htmlResponse(OfferCampaign $offerCampaign, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Discounts/Campaign',
            [
                'title'                                              => __('Offer Campaign'),
                'breadcrumbs'                                        => $this->getBreadcrumbs($offerCampaign, $request->route()->getName(), $request->route()->originalParameters()),
                'navigation'                                         => [
                    'previous' => $this->getPrevious($offerCampaign, $request),
                    'next'     => $this->getNext($offerCampaign, $request),
                ],
                'pageHead'                                           => [
                    'icon'  =>
                        [
                            'icon'  => ['fal', 'comment-dollar'],
                            'title' => __('Offer campaign')
                        ],
                    'title'         => $offerCampaign->name,
                    'model'         => __('Offer Campaign'),
                ],
                'tabs'                                               => [
                    'current'    => $this->tab,
                    'navigation' => OfferCampaignTabsEnum::navigation()
                ],
                OfferCampaignTabsEnum::OVERVIEW->value => $this->tab == OfferCampaignTabsEnum::OVERVIEW->value ?
                    fn () => GetOfferCampaignOverview::run($offerCampaign)
                    : Inertia::lazy(fn () => GetOfferCampaignOverview::run($offerCampaign)),
                OfferCampaignTabsEnum::OFFERS->value => $this->tab == OfferCampaignTabsEnum::OFFERS->value ?
                    fn () => OffersResource::collection(IndexOffers::run($offerCampaign, OfferCampaignTabsEnum::OFFERS->value))
                    : Inertia::lazy(fn () => OffersResource::collection(IndexOffers::run($offerCampaign, OfferCampaignTabsEnum::OFFERS->value))),
                OfferCampaignTabsEnum::HISTORY->value => $this->tab == OfferCampaignTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($offerCampaign, OfferCampaignTabsEnum::HISTORY->value))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($offerCampaign, OfferCampaignTabsEnum::HISTORY->value))),
            ]
        )->table(IndexOffers::make()->tableStructure(parent: $offerCampaign, prefix: OfferCampaignTabsEnum::OFFERS->value))
        ->table(IndexHistory::make()->tableStructure(prefix:OfferCampaignTabsEnum::HISTORY->value));
    }


    public function jsonResponse(OfferCampaign $offerCampaign): OfferCampaignResource
    {
        return new OfferCampaignResource($offerCampaign);
    }

    public function getBreadcrumbs(OfferCampaign $offerCampaign, string $routeName, array $routeParameters, $suffix = null): array
    {
        $headCrumb = function (OfferCampaign $offerCampaign, array $routeParameters, $suffix) {
            return [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Offer campaigns')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $offerCampaign->slug,
                        ],
                    ],
                    'suffix' => $suffix,

                ],
            ];
        };

        return match ($routeName) {
            'grp.org.shops.show.discounts.campaigns.show' =>
            array_merge(
                ShowShop::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $offerCampaign,
                    [
                        'index' => [
                            'name'       => preg_replace('/show$/', 'index', $routeName),
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => $routeName,
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            default => []
        };
    }

    public function getPrevious(OfferCampaign $offerCampaign, ActionRequest $request): ?array
    {
        $previous = OfferCampaign::where('slug', '<', $offerCampaign->slug)->where('shop_id', $offerCampaign->shop_id)->orderBy('slug', 'desc')->first();
        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(OfferCampaign $offerCampaign, ActionRequest $request): ?array
    {
        $next = OfferCampaign::where('slug', '>', $offerCampaign->slug)->where('shop_id', $offerCampaign->shop_id)->orderBy('slug')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?OfferCampaign $offerCampaign, string $routeName): ?array
    {
        if (!$offerCampaign) {
            return null;
        }


        return match ($routeName) {
            'grp.org.shops.show.discounts.campaigns.show' => [
                'label' => $offerCampaign->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation'        => $offerCampaign->organisation->slug,
                        'shop'                => $offerCampaign->shop->slug,
                        'offerCampaign'       => $offerCampaign->slug
                    ]
                ]
            ],
        };
    }
}
