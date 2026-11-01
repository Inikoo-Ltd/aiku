<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 10 May 2026 20:46:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\Analytics;

use App\Actions\Retina\SysAdmin\ProcessRetinaWebUserRequest;
use App\Actions\Web\WebsiteVisitor\ProcessWebsiteVisitorTracking;
use App\Models\CRM\WebUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class RecordWebsiteHit
{
    use AsAction;

    public const string VISITOR_COOKIE = 'aiku_vid';

    public const int VISITOR_COOKIE_MINUTES = 60 * 24 * 120;

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

        $webUser = $request->user('retina');

        if (config('iris.analytics.live_visitors')) {
            TrackWebsiteVisitorActivity::dispatch(
                $website,
                $request->session()->getId(),
                [
                    'user_agent'  => $request->userAgent(),
                    'referer'     => $request->header('referer'),
                    'page'        => $request->input('analytics_webpage'),
                    'page_title'  => $request->input('analytics_page_title'),
                    'country'     => $request->header('CF-IPCountry') ?? 'XX',
                    'city'        => $request->header('CF-IPCity'),
                    'region'      => $request->header('CF-Region'),
                    'webpage_id'  => $request->input('webpage_id'),
                    'web_user_id' => $webUser instanceof WebUser ? $webUser->id : null,
                ]
            );
        }

        $geoLocation = [
            $request->header('CF-IPCountry') ?? 'XX',
            $request->header('CF-Region'),
            $request->header('CF-IPCity'),
            $request->header('CF-IPLongitude'),
            $request->header('CF-IPLatitude'),
        ];

        if ($this->shouldTrackVisitor($request)) {
            $visitorUuid = $this->rememberVisitor($request);

            ProcessWebsiteVisitorTracking::dispatch(
                sessionId: $request->session()->getId(),
                website: $request->input('website'),
                webUser: $request->user('retina'),
                userAgent: $request->userAgent(),
                ip: request()->ip(),
                currentUrl: request()->header('referer'),
                referrer: $request->input('original_referer'),
                geoLocation: $geoLocation,
                visitorUuid: $visitorUuid
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

    /**
     * Gives the browser an id that outlives its IP address, and keeps it for as long as the traffic
     * source cookie is kept. Queued on every tracked hit so the cookie is renewed while the visitor
     * is active.
     */
    protected function rememberVisitor(Request $request): string
    {
        $visitorUuid = (string) $request->cookie(self::VISITOR_COOKIE);

        if (! Str::isUuid($visitorUuid)) {
            $visitorUuid = (string) Str::uuid();
        }

        Cookie::queue(self::VISITOR_COOKIE, $visitorUuid, self::VISITOR_COOKIE_MINUTES, null, null, null, true, false, 'lax');

        return $visitorUuid;
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
}
