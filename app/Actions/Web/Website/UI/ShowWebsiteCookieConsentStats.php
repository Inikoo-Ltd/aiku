<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Fri, 27 Nov 2026 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Website\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Web\Website\WithWebsiteAnalyticsSubNavigation;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * Accept / reject / ignore rates of our own cookie banner for a date range (INI-572), from the
 * daily counts RecordCookieConsentStat keeps. Marketing copies them into its monthly report, as it
 * did from the CookieScript dashboard. Rates are out of the times the banner was shown; ignored is
 * shown and no choice made.
 */
class ShowWebsiteCookieConsentStats extends OrgAction
{
    use WithWebAuthorisation;
    use WithWebsiteAnalyticsSubNavigation;

    private Website $website;

    public function handle(Website $website, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $totals = DB::table('website_cookie_consent_stats')
            ->where('website_id', $website->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('coalesce(sum(shown), 0) as shown, coalesce(sum(accepted_all), 0) as accepted_all, coalesce(sum(rejected_all), 0) as rejected_all, coalesce(sum(partial), 0) as partial, coalesce(sum(analytics), 0) as analytics, coalesce(sum(marketing), 0) as marketing, coalesce(sum(preferences), 0) as preferences')
            ->first();

        $counts  = array_map('intval', (array) $totals);
        $choices = $counts['accepted_all'] + $counts['rejected_all'] + $counts['partial'];

        // A choice can land on a day, or in a session, whose "shown" was not counted (e.g. just after
        // midnight, or sessionStorage blocked), so the base is never below the choices made.
        $shown   = max($counts['shown'], $choices);
        $ignored = $shown - $choices;
        $rate    = fn (int $count) => $shown ? round($count * 100 / $shown, 2) : null;

        return [
            'shown'   => $shown,
            'choices' => $choices,
            'counts'  => Arr::only($counts, ['accepted_all', 'rejected_all', 'partial']) + ['ignored' => $ignored],
            'rates'   => [
                'accepted_all' => $rate($counts['accepted_all']),
                'rejected_all' => $rate($counts['rejected_all']),
                'partial'      => $rate($counts['partial']),
                'ignored'      => $rate($ignored),
            ],
            'categories' => [
                'analytics'   => $rate($counts['analytics']),
                'marketing'   => $rate($counts['marketing']),
                'preferences' => $rate($counts['preferences']),
            ],
            'first_date' => DB::table('website_cookie_consent_stats')->where('website_id', $website->id)->min('date'),
        ];
    }

    public function asController(Organisation $organisation, Shop $shop, Website $website, ActionRequest $request): ActionRequest
    {
        $this->website = $website;
        $this->initialisationFromShop($shop, $request);

        return $request;
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public function range(ActionRequest $request): array
    {
        $date = function (mixed $value): ?CarbonImmutable {
            if (!is_string($value) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts) || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
                return null;
            }

            return CarbonImmutable::createFromFormat('!Y-m-d', $value, config('app.timezone'));
        };

        $to   = $date($request->query('to')) ?? CarbonImmutable::today();
        $from = $date($request->query('from'));
        if (!$from || $from->gt($to)) {
            $from = $to->startOfMonth();
        }

        return [$from, $to];
    }

    public function htmlResponse(ActionRequest $request): Response
    {
        [$from, $to] = $this->range($request);
        $title       = __('Cookie consent');

        return Inertia::render(
            'Org/Web/WebsiteCookieConsentStats',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => $title,
                'pageHead'    => [
                    'icon'          => [
                        'icon'  => ['fal', 'fa-cookie-bite'],
                        'title' => $title,
                    ],
                    'title'         => $title,
                    'model'         => $this->website->name,
                    'subNavigation' => $this->getWebsiteAnalyticsNavigation($this->website),
                ],
                'filters'     => [
                    'from' => $from->toDateString(),
                    'to'   => $to->toDateString(),
                ],
                'is_enabled'  => (bool) Arr::get($this->website->settings, 'own_cookie_banner', false),
                'stats'       => $this->handle($this->website, $from, $to),
                'route'       => [
                    'name'       => 'grp.org.shops.show.web.analytics.cookie_consent',
                    'parameters' => $request->route()->originalParameters(),
                ],
            ]
        );
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        /** @var Website $website */
        $website = request()->route()->parameter('website');

        return array_merge(
            ShowWebsite::make()->getBreadcrumbs($website, 'grp.org.shops.show.web.websites.show', $routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.web.analytics.cookie_consent',
                            'parameters' => $routeParameters
                        ],
                        'label' => __('Cookie consent'),
                    ]
                ]
            ]
        );
    }
}
