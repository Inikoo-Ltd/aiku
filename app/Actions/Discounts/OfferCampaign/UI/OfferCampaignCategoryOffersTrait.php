<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Discounts\OfferCampaign\UI;

use App\Actions\Discounts\Offer\UI\IndexOffers;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Enums\UI\Discounts\OfferCampaignTabsEnum;
use App\Http\Resources\Catalogue\OffersResource;
use App\Http\Resources\History\HistoryResource;
use App\Models\Discounts\OfferCampaign;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

trait OfferCampaignCategoryOffersTrait
{
    public function getCategoryOffersHtmlResponse(OfferCampaign $offerCampaign, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Discounts/CategoryOffersCampaign',
            [
                'title'                                              => __('Offer Campaign'),
                'breadcrumbs'                                        => $this->getBreadcrumbs($offerCampaign, $request->route()->getName(), $request->route()->originalParameters()),
                'navigation'                                         => [
                    'previous' => $this->getPreviousModel($offerCampaign, $request),
                    'next'     => $this->getNextModel($offerCampaign, $request),
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
}
