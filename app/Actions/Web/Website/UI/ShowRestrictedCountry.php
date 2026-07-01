<?php

/*
 * Author: stewicca <wiccaalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Website\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Enums\UI\Web\WebsiteRestrictedCountryTabsEnum;
use App\Http\Resources\Web\RestrictedCountryLogResource;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use App\Models\Catalogue\Shop;
use App\Models\Web\Website;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowRestrictedCountry extends OrgAction
{
    use WithWebAuthorisation;

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(Organisation $organisation, Shop $shop, Website $website, ActionRequest $request): Website
    {
        $this->initialisationFromShop($shop, $request)->withTab(WebsiteRestrictedCountryTabsEnum::values());

        return $website;
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, Website $website, ActionRequest $request): Website
    {
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(WebsiteRestrictedCountryTabsEnum::values());

        return $website;
    }

    public function htmlResponse(Website $website, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Web/WebsiteRestrictedCountry',
            [
                'title'       => __('Restricted Countries'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $website,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    'title' => $website->name,
                    'model' => __('Restricted Countries'),
                    'icon'  => [
                        'title' => __('Restricted Countries'),
                        'icon'  => 'fal fa-ban',
                    ],
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => WebsiteRestrictedCountryTabsEnum::navigation(),
                ],

                WebsiteRestrictedCountryTabsEnum::OVERVIEW->value => $this->tab == WebsiteRestrictedCountryTabsEnum::OVERVIEW->value
                    ? fn () => GetRestrictedCountryOverview::run($website)
                    : Inertia::lazy(fn () => GetRestrictedCountryOverview::run($website)),

                WebsiteRestrictedCountryTabsEnum::LOGS->value => $this->tab == WebsiteRestrictedCountryTabsEnum::LOGS->value ?
                    fn () => RestrictedCountryLogResource::collection(IndexRestrictedCountryLogs::run($website, WebsiteRestrictedCountryTabsEnum::LOGS->value))
                    : Inertia::lazy(fn () => RestrictedCountryLogResource::collection(IndexRestrictedCountryLogs::run($website, WebsiteRestrictedCountryTabsEnum::LOGS->value))),
            ]
        )
        ->table(IndexRestrictedCountryLogs::make()->tableStructure(website: $website, prefix: WebsiteRestrictedCountryTabsEnum::LOGS->value));
    }

    public function getBreadcrumbs(Website $website, string $routeName, array $routeParameters): array
    {
        return ShowWebsite::make()->getBreadcrumbs(
            $website,
            Str::replaceLast('.restricted_country', '.show', $routeName),
            $routeParameters,
            __('Restricted countries')
        );
    }
}
