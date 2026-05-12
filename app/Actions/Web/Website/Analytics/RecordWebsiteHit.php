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

        $geoLocation = [
            $request->header('CF-IPCountry') ?? 'XX',
            $request->header('CF-Region'),
            $request->header('CF-IPCity'),
            $request->header('CF-IPLongitude'),
            $request->header('CF-IPLatitude'),
        ];

        if ($this->shouldTrackVisitor($request)) {
            ProcessWebsiteVisitorTracking::dispatch(
                $request->session()->getId(),
                $request->input('website'),
                $request->user('retina'),
                $request->userAgent(),
                request()->ip(),
                request()->header('referer'),
                $request->input('original_referer'),
                $geoLocation
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
