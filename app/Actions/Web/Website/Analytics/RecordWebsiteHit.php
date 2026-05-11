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
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class RecordWebsiteHit
{
    use AsAction;

    public function asController(ActionRequest $request): void
    {
        $website = request()->website;

        $appName = request()->header('X-Analytics-App');

        if (!$appName) {
            return;
        }

        $metrics = [
            'org'       => $website->organisation_id,
            'website'   => $website->slug,
            'type'      => $website->type->value,
            'webpage'   => request()->header('X-Analytics-Webpage'),
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
                $request->fullUrl(),
                $request->header('referer'),
                $geoLocation
            )->delay(now()->addSeconds(5));
        }

        if ($this->shouldLogWebUserRequest($request)) {
            ProcessRetinaWebUserRequest::dispatch(
                $request->user(),
                now(),
                [
                    'name'      => $request->route()->getName(),
                    'arguments' => $request->route()->originalParameters(),
                    'url'       => $request->path(),
                ],
                $request->ip(),
                $request->header('User-Agent'),
                $geoLocation
            )->delay(now()->addSeconds(5));
        }
    }

    protected function shouldTrackVisitor(Request $request): bool
    {
        if (app()->isLocal()) {
            return false;
        }

        if (!$request->input('website')) {
            return false;
        }

        if ($request->expectsJson() && !$request->header('X-Inertia')) {
            return false;
        }

        $routeName = $request->route()?->getName();

        if (!$routeName) {
            return false;
        }

        $excludedRoutes = [
            'iris.models',
            'retina.models',
            'iris.json',
            'retina.json',
            'iris.webhooks',
            'retina.webhooks',
        ];

        return array_all($excludedRoutes, fn ($excluded) => !str_starts_with($routeName, $excluded));
    }

    protected function shouldLogWebUserRequest(Request $request): bool
    {
        if (!$request->user()) {
            return false;
        }

        if (!config('app.log_user_requests')) {
            return false;
        }

        $webUser = $request->user();

        if (!($webUser instanceof WebUser)) {
            return false;
        }

        $routeName = $request->route()->getName();

        if (!str_starts_with($routeName, 'retina.') && !str_starts_with($routeName, 'iris.')) {
            return false;
        }

        $skipPrefixes = ['retina.models', 'iris.models', 'retina.webhooks', 'iris.json', 'retina.json', 'iris.catalogue'];

        if ($routeName == 'retina.logout') {
            return false;
        }

        if (array_any($skipPrefixes, fn ($prefix) => str_starts_with($routeName, $prefix))) {
            return false;
        }

        if ($request->route() instanceof Route && $request->route()->getAction('uses') instanceof Closure) {
            return false;
        }

        if (app()->runningUnitTests()) {
            return false;
        }

        return true;
    }
}
