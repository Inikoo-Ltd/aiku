<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 10 May 2026 20:46:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\Analytics;

use App\Actions\Retina\SysAdmin\ProcessRetinaWebUserRequest;
use App\Actions\Web\WebsiteVisitor\ProcessWebsiteVisitorTracking;
use App\Actions\Web\WebsiteVisitor\UI\GetBrowserInfo;
use App\Models\CRM\WebUser;
use Illuminate\Http\Request;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class RecordWebsiteHit
{
    use AsAction;

    public function asController(ActionRequest $request): void
    {
        $website = request()->website;

        $appName = $request->input('analytics_app');

        if (!$appName) {
            return;
        }

        $metrics = [
            'org'       => $website->organisation_id,
            'website'   => $website->slug,
            'type'      => $website->type->value,
            'webpage'   => $request->input('analytics_webpage'),
            'device'    => request()->header('sec-ch-ua-form-factors'),
            'country'   => request()->header('CF-IPCountry') ?? 'XX',
            'logged_in' => $request->user() !== null,
            'url'       => request()->header('referer'),
            'app'       => $appName
        ];

        ProcessWebsiteHit::dispatch($metrics, $request->userAgent());

        $browserInfo = GetBrowserInfo::run($request->userAgent());

        $referer = request()->header('referer');
        $searchInfo = $this->extractSearchInfo($referer);

        TrackWebsiteVisitorActivity::run(
            $website,
            $request->session()->getId(),
            [
                'url'           => $referer,
                'page'          => $request->input('analytics_webpage'),
                'page_title'    => $request->input('analytics_page_title'),
                'country'       => request()->header('CF-IPCountry') ?? 'XX',
                'logged_in'     => $request->user() !== null,
                'device'        => $browserInfo['device'],
                'browser'       => $browserInfo['browser'],
                'os'            => $browserInfo['os'],
                'search_engine' => $searchInfo['engine'],
                'search_term'   => $searchInfo['term'],
                'last_active'   => time(),
            ]
        );

        $geoLocation = [
            $request->header('CF-IPCountry') ?? 'XX',
            $request->header('CF-Region'),
            $request->header('CF-IPCity'),
            $request->header('CF-IPLongitude'),
            $request->header('CF-IPLatitude'),
        ];

        if ($this->shouldTrackVisitor($request)) {
            ProcessWebsiteVisitorTracking::dispatch(
                sessionId: $request->session()->getId(),
                website: $request->input('website'),
                webUser: $request->user('retina'),
                userAgent: $request->userAgent(),
                ip: request()->ip(),
                currentUrl: request()->header('referer'),
                referrer: $request->input('original_referer'),
                geoLocation: $geoLocation
            )->delay(now()->addSeconds(5));
        }

        if ($this->shouldLogWebUserRequest($request)) {
            ProcessRetinaWebUserRequest::dispatch(
                $request->user(),
                now(),
                $request->input('webpage_id'),
                [
                    'name'      => $request->input('original_route'),
                    'arguments' => $request->input('original_params'),
                    'url'       => request()->header('referer'),
                ],
                $request->ip(),
                $request->header('User-Agent'),
                $geoLocation
            )->delay(now()->addSeconds(5));
        }
    }

    protected function shouldTrackVisitor(Request $request): bool
    {
        if (!config('iris.analytics.web_visits')) {
            return false;
        }

        if (!$request->input('website')) {
            return false;
        }

        $country = $request->header('CF-IPCountry');
        if (!$country || $country === 'XX' || $country === 'T1') {
            return false;
        }

        if ($request->header('referer') == null) {
            return false;
        }


        return true;
    }

    protected function shouldLogWebUserRequest(Request $request): bool
    {
        if (!$request->user()) {
            return false;
        }

        if (!config('iris.analytics.web_users')) {
            return false;
        }

        $webUser = $request->user();

        if (!($webUser instanceof WebUser)) {
            return false;
        }

        if (app()->runningUnitTests()) {
            return false;
        }

        return true;
    }

    protected function extractSearchInfo(?string $referer): array
    {
        if (!$referer) {
            return ['engine' => null, 'term' => null];
        }

        $url = parse_url($referer);
        $host = $url['host'] ?? '';
        $query = $url['query'] ?? '';

        parse_str($query, $params);

        $engines = [
            'google'     => ['host' => 'google', 'param' => 'q'],
            'bing'       => ['host' => 'bing', 'param' => 'q'],
            'yahoo'      => ['host' => 'yahoo', 'param' => 'p'],
            'duckduckgo' => ['host' => 'duckduckgo', 'param' => 'q'],
            'baidu'      => ['host' => 'baidu', 'param' => 'wd'],
        ];

        foreach ($engines as $name => $data) {
            if (str_contains($host, $data['host'])) {
                return [
                    'engine' => ucfirst($name),
                    'term'   => $params[$data['param']] ?? null
                ];
            }
        }

        return ['engine' => null, 'term' => null];
    }
}
