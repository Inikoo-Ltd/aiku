<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\TrafficSourceCampaign\UI;

use App\Actions\Helpers\Country\UI\GetCountriesOptions;
use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Services\GoogleAds\GoogleAdsClient;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

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
                        'type'   => 'creatingModel',
                        'creatingModel' => ['label' => __('New campaign')],
                    ]],
                ),
                'title'    => __('New Google Ads campaign'),
                'pageHead' => [
                    'title' => __('New Search campaign'),
                    'icon'  => [
                        'icon'  => ['fab', 'fa-google'],
                        'title' => __('Google Ads'),
                    ],
                    'model' => __('Google Ads'),
                ],

                'unreachable_reason' => GoogleAdsClient::unreachableReason($shop),
                'currency'           => $shop->currency->code,

                /* A suggestion can open this form with what it already knows filled in: the department
                   it found, and the site searches behind it as starting keywords. Everything stays
                   editable, and nothing is created until the form is submitted, so a weak suggestion
                   costs a glance rather than a campaign. */
                'prefill' => [
                    'name'     => $request->query('name'),
                    'keywords' => array_filter(explode("\n", (string) $request->query('keywords'))),
                ],

                /* Keyed by ISO code rather than by Aiku's country id, because that is what Google
                   translates into its own geo target constants at submit time.
                   The shop's own country is where it almost certainly wants to advertise, so the form
                   opens with it chosen rather than empty. */
                'countries' => collect(GetCountriesOptions::run())
                    ->map(fn (array $country) => ['value' => $country['code'], 'label' => $country['label']])
                    ->sortBy('label')
                    ->values()
                    ->all(),
                'default_country' => $shop->country?->code,
                'default_url'     => $shop->website?->domain ? 'https://'.$shop->website->domain : null,

                'store_route' => [
                    'name'       => 'grp.models.org.shop.google_ads.campaign.store',
                    'parameters' => [
                        'organisation' => $this->organisation->id,
                        'shop'         => $shop->id,
                    ],
                ],
                'index_route' => [
                    'name'       => 'grp.org.shops.show.marketing.google_ads.index',
                    'parameters' => $request->route()->originalParameters(),
                ],
            ]
        );
    }
}
