<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Aug 2026 12:45:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\OfferCampaign\UI;

use App\Actions\Discounts\Offer\UI\IndexOffers;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Enums\Discounts\OfferCampaign\OfferCampaignTypeEnum;
use App\Enums\UI\Discounts\OfferCampaignTabsEnum;
use App\Http\Resources\Catalogue\OffersResource;
use App\Http\Resources\History\HistoryResource;
use App\Models\Discounts\OfferCampaign;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

trait OfferCampaignStepOffersTrait
{
    public function getStepOffersHtmlResponse(OfferCampaign $offerCampaign, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Discounts/StepOffersCampaign',
            [
                'title'       => __('Offer Campaign'),
                'breadcrumbs' => $this->getBreadcrumbs($offerCampaign, $request->route()->getName(), $request->route()->originalParameters()),
                'navigation'  => [
                    'previous' => $this->getPreviousModel($offerCampaign, $request),
                    'next'     => $this->getNextModel($offerCampaign, $request),
                ],
                'pageHead'    => [
                    'icon'      =>
                        [
                            'icon'  => ['fal', 'comment-dollar'],
                            'title' => __('Offer campaign')
                        ],
                    'title'     => $offerCampaign->name,
                    'model'     => __('Offer Campaign'),
                    'iconRight' => OfferCampaignTypeEnum::from($offerCampaign->type->value)->icons()[$offerCampaign->type->value],
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => OfferCampaignTabsEnum::navigationExcept([
                        OfferCampaignTabsEnum::GR_AMNESTY
                    ])
                ],
                'shop_data'   => [
                    'slug'          => $offerCampaign->shop->slug,
                    'currency_code' => $offerCampaign->shop->currency->code,
                ],
                OfferCampaignTabsEnum::OVERVIEW->value => $this->tab == OfferCampaignTabsEnum::OVERVIEW->value ?
                    fn () => GetStepOffersCampaignOverview::run($offerCampaign)
                    : Inertia::optional(fn () => GetStepOffersCampaignOverview::run($offerCampaign)),
                OfferCampaignTabsEnum::OFFERS->value => $this->tab == OfferCampaignTabsEnum::OFFERS->value ?
                    fn () => OffersResource::collection(IndexOffers::run($offerCampaign, OfferCampaignTabsEnum::OFFERS->value))
                    : Inertia::optional(fn () => OffersResource::collection(IndexOffers::run($offerCampaign, OfferCampaignTabsEnum::OFFERS->value))),
                OfferCampaignTabsEnum::HISTORY->value => $this->tab == OfferCampaignTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($offerCampaign, OfferCampaignTabsEnum::HISTORY->value))
                    : Inertia::optional(fn () => HistoryResource::collection(IndexHistory::run($offerCampaign, OfferCampaignTabsEnum::HISTORY->value))),
            ]
        )->table(IndexOffers::make()->tableStructure(parent: $offerCampaign, prefix: OfferCampaignTabsEnum::OFFERS->value))
            ->table(IndexHistory::make()->tableStructure(prefix: OfferCampaignTabsEnum::HISTORY->value));
    }
}
