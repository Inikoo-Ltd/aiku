<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Mar 2026, Kuala Lumpur, Malaysia
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

trait OfferCampaignGiftTrait
{
    public function getGiftHtmlResponse(OfferCampaign $offerCampaign, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Discounts/GiftCampaign',
            [
                'title'                                => __('Offer Campaign'),
                'breadcrumbs'                          => $this->getBreadcrumbs($offerCampaign, $request->route()->getName(), $request->route()->originalParameters()),
                'navigation'                           => [
                    'previous' => $this->getPreviousModel($offerCampaign, $request),
                    'next'     => $this->getNextModel($offerCampaign, $request),
                ],
                'pageHead'                             => [
                    'icon'      => [
                        'icon'  => ['fal', 'comment-dollar'],
                        'title' => __('Offer campaign')
                    ],
                    'title'     => $offerCampaign->name,
                    'model'     => __('Offer Campaign'),
                    'iconRight' => OfferCampaignTypeEnum::from($offerCampaign->type->value)->icons()[$offerCampaign->type->value],
                    'actions'   => app()->environment('local') ? [
                        [
                            'type' => 'button',
                            'key'  => 'gift_create_discount',
                            // 'route' => [
                            //     'name'       => preg_replace('/show$/', 'create_family_offer', request()->route()->getName()),
                            //     'parameters' => array_values(request()->route()->originalParameters())
                            // ]
                        ]
                    ] : [],
                ],
                'tabs'                                 => [
                    'current'    => $this->tab,
                    'navigation' => OfferCampaignTabsEnum::navigationExcept([
                        OfferCampaignTabsEnum::GR_AMNESTY
                    ])
                ],
                'shop_data'                            => [
                    'id'            => $offerCampaign->shop_id,
                    'slug'          => $offerCampaign->shop->slug,
                    'currency_code' => $offerCampaign->shop->currency->code,
                ],
                OfferCampaignTabsEnum::OVERVIEW->value => $this->tab == OfferCampaignTabsEnum::OVERVIEW->value ?
                    fn () => GetOfferCampaignOverview::run($offerCampaign)
                    : Inertia::lazy(fn () => GetOfferCampaignOverview::run($offerCampaign)),
                OfferCampaignTabsEnum::OFFERS->value   => $this->tab == OfferCampaignTabsEnum::OFFERS->value ?
                    fn () => OffersResource::collection(IndexOffers::run($offerCampaign, OfferCampaignTabsEnum::OFFERS->value))
                    : Inertia::lazy(fn () => OffersResource::collection(IndexOffers::run($offerCampaign, OfferCampaignTabsEnum::OFFERS->value))),
                OfferCampaignTabsEnum::HISTORY->value  => $this->tab == OfferCampaignTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($offerCampaign, OfferCampaignTabsEnum::HISTORY->value))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($offerCampaign, OfferCampaignTabsEnum::HISTORY->value))),
            ]
        )->table(IndexOffers::make()->tableStructure(parent: $offerCampaign, prefix: OfferCampaignTabsEnum::OFFERS->value))
            ->table(IndexHistory::make()->tableStructure(prefix: OfferCampaignTabsEnum::HISTORY->value));
    }
}
