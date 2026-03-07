<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 28 May 2024 12:06:23 British Summer Time, Plane Manchester-Malaga
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\OfferCampaign\UI;

use App\Actions\Discounts\Offer\UI\IndexOffers;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Enums\Discounts\OfferCampaign\OfferCampaignTypeEnum;
use App\Enums\UI\Discounts\OfferCampaignTabsEnum;
use App\Http\Resources\Catalogue\OffersResource;
use App\Http\Resources\History\HistoryResource;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferCampaign;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

trait OfferCampaignVolumeDiscountTrait
{
    public function getVolumeDiscountHtmlResponse(OfferCampaign $offerCampaign, ActionRequest $request): Response
    {
        $giftOffer   = null;
        $giftOfferId = Arr::get($offerCampaign->data, 'vol_gr_gift_offer_id');
        if ($giftOfferId) {
            $giftOffer = Offer::find($giftOfferId);
        }


        return Inertia::render(
            'Org/Discounts/VolumeDiscountCampaign',
            [
                'title'                                => __('Offer Campaign'),
                'breadcrumbs'                          => $this->getBreadcrumbs($offerCampaign, $request->route()->getName(), $request->route()->originalParameters()),
                'navigation'                           => [
                    'previous' => $this->getPreviousModel($offerCampaign, $request),
                    'next'     => $this->getNextModel($offerCampaign, $request),
                ],
                'pageHead'                             => [
                    'icon'      =>
                        [
                            'icon'  => ['fal', 'comment-dollar'],
                            'title' => __('Offer campaign')
                        ],
                    'title'     => $offerCampaign->name,
                    'model'     => __('Offer Campaign'),
                    'iconRight' => OfferCampaignTypeEnum::from($offerCampaign->type->value)->icons()[$offerCampaign->type->value],
                    'actions'   => [
                        $giftOffer
                            ? [
                            'type'  => 'button',
                            'icon'  => 'fal fa-gift',
                            'label' => __('Edit Vol/GR Gift'),
                            'route' => [
                                'name'       => preg_replace('/show$/', 'edit_vol_gr_gift', request()->route()->getName()),
                                'parameters' => array_values(request()->route()->originalParameters())

                            ]
                        ]
                            : [
                            'type'  => 'button',
                            'icon'  => 'fal fa-gift',
                            'label' => __('Set up Vol/GR Gift'),
                            'route' => [
                                'name'       => preg_replace('/show$/', 'create_vol_gr_gift', request()->route()->getName()),
                                'parameters' => array_values(request()->route()->originalParameters())

                            ]
                        ]
                    ],
                ],
                'tabs'                                 => [
                    'current'    => $this->tab,
                    'navigation' => OfferCampaignTabsEnum::navigation()
                ],
                OfferCampaignTabsEnum::OVERVIEW->value => $this->tab == OfferCampaignTabsEnum::OVERVIEW->value ?
                    fn() => GetOfferCampaignOverview::run($offerCampaign)
                    : Inertia::lazy(fn() => GetOfferCampaignOverview::run($offerCampaign)),
                OfferCampaignTabsEnum::OFFERS->value   => $this->tab == OfferCampaignTabsEnum::OFFERS->value ?
                    fn() => OffersResource::collection(IndexOffers::run($offerCampaign, OfferCampaignTabsEnum::OFFERS->value))
                    : Inertia::lazy(fn() => OffersResource::collection(IndexOffers::run($offerCampaign, OfferCampaignTabsEnum::OFFERS->value))),
                OfferCampaignTabsEnum::HISTORY->value  => $this->tab == OfferCampaignTabsEnum::HISTORY->value ?
                    fn() => HistoryResource::collection(IndexHistory::run($offerCampaign, OfferCampaignTabsEnum::HISTORY->value))
                    : Inertia::lazy(fn() => HistoryResource::collection(IndexHistory::run($offerCampaign, OfferCampaignTabsEnum::HISTORY->value))),
            ]
        )->table(IndexOffers::make()->tableStructure(parent: $offerCampaign, prefix: OfferCampaignTabsEnum::OFFERS->value))
            ->table(IndexHistory::make()->tableStructure(prefix: OfferCampaignTabsEnum::HISTORY->value));
    }
}
