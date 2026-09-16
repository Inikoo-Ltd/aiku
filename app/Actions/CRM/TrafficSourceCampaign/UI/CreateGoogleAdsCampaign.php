<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\TrafficSourceCampaign\UI;

use App\Actions\CRM\TrafficSourceCampaign\GoogleAds\StoreGoogleAdsCampaign;
use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Services\GoogleAds\GoogleAdsClient;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * Starting a campaign asks two questions: what it is called, and what kind it is.
 *
 * Everything else depends on the answer to the second, and is asked on the campaign's own page. A
 * campaign is not something anybody fills in correctly in one sitting, and a form that demands
 * keywords, images and search themes before anything exists gives you nothing to come back to.
 */
class CreateGoogleAdsCampaign extends OrgAction
{
    public function handle(Shop $shop): Shop
    {
        return $shop;
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Shop
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    public function htmlResponse(Shop $shop, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Shop/CRM/CreateGoogleAdsCampaign',
            [
                'breadcrumbs' => array_merge(
                    IndexGoogleAdsCampaigns::make()->getBreadcrumbs($request->route()->originalParameters()),
                    [[
                        'type'          => 'creatingModel',
                        'creatingModel' => ['label' => __('New campaign')],
                    ]],
                ),
                'title'    => __('New Google Ads campaign'),
                'pageHead' => [
                    'title' => __('New campaign'),
                    'icon'  => ['icon' => ['fab', 'fa-google'], 'title' => __('Google Ads')],
                    'model' => __('Google Ads'),
                ],

                'unreachable_reason' => GoogleAdsClient::unreachableReason($shop),
                'campaign_types'     => $this->campaignTypes(),

                /* A suggestion arrives asking for a Search campaign, which is the only type its rules
                   can propose, and brings the name it worked out. */
                'prefill' => [
                    'name'         => $request->query('name'),
                    'channel_type' => $request->query('channel_type', 'SEARCH'),
                ],

                'store_route' => [
                    'name'       => 'grp.models.org.shop.google_ads.campaign.store',
                    'parameters' => ['organisation' => $this->organisation->id, 'shop' => $shop->id],
                ],
                'index_route' => [
                    'name'       => 'grp.org.shops.show.marketing.google_ads.index',
                    'parameters' => $request->route()->originalParameters(),
                ],
            ]
        );
    }

    /**
     * The types Google's API will create, and what each is for in one line.
     *
     * Video and Shopping are absent and stay absent: Google refuses to create a video campaign through
     * the API whatever is sent, and a Shopping campaign needs a Merchant Center feed to sell from.
     *
     * @return array<int, array{value: string, label: string, description: string}>
     */
    private function campaignTypes(): array
    {
        $types = [
            'SEARCH' => [
                'label'       => __('Search'),
                'description' => __('Text ads against what people type into Google. The only type that bids on keywords.'),
            ],
            'PERFORMANCE_MAX' => [
                'label'       => __('Performance Max'),
                'description' => __('Google assembles its own ads from what you give it and shows them everywhere. Needs images and a logo.'),
            ],
            'DISPLAY' => [
                'label'       => __('Display'),
                'description' => __('Image ads on the websites and apps in Google\'s display network.'),
            ],
            'DEMAND_GEN' => [
                'label'       => __('Demand Gen'),
                'description' => __('Image ads on YouTube, Discover and Gmail, aimed at people not yet looking for you.'),
            ],
        ];

        return collect(StoreGoogleAdsCampaign::CHANNEL_TYPES)
            ->map(fn (string $type) => array_merge(['value' => $type], $types[$type]))
            ->values()
            ->all();
    }
}
