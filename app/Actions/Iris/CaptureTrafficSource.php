<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 Oct 2025 11:43:05 Central Indonesia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Iris;

use App\Actions\CRM\TrafficSource\GetTrafficSourceFromRefererHeader;
use App\Actions\CRM\TrafficSource\GetTrafficSourceFromUrl;
use App\Enums\Web\Website\WebsiteTypeEnum;
use Lorisleiva\Actions\Concerns\AsAction;

class CaptureTrafficSource
{
    use AsAction;

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function handle(): array
    {
        if (!$this->canCaptureTrafficSource()) {
            return [];
        }

        return $this->getCookies();
    }


    public function getCookies(): array
    {
        $cookies = [];
        $referer = request()->headers->get('referer', '');

        // Check both referer and current full URL
        $trafficSourceData = GetTrafficSourceFromUrl::run(request()->fullUrl());

        /* When this runs from the first-hit AJAX endpoint the ad click ids are not on the request URL,
           they are on the storefront page that issued the fetch, which is exactly what the browser
           puts in the Referer header. Without this the paid capture is silently a no-op behind Varnish,
           where the AJAX endpoint is the only capture path left. */
        if ($trafficSourceData === null) {
            $trafficSourceData = GetTrafficSourceFromUrl::run($referer);
        }

        if ($trafficSourceData === null) {
            $trafficSourceData = GetTrafficSourceFromRefererHeader::run($referer);
        }

        if ($trafficSourceData) {
            $lastTrafficSource = request()->cookie('aiku_lts');

            if ($lastTrafficSource == $trafficSourceData) {
                return $cookies;
            }


            // Check if the cookie already exists
            $existingCookieData = request()->cookie('aiku_tsd');
            if ($existingCookieData) {
                $appendedTrafficSourceData = $existingCookieData.'|'.now()->utc()->timestamp.$trafficSourceData;
                $cookieSize                = (4 + strlen('aiku_tsd'.$appendedTrafficSourceData)) / 1024;

                if ($cookieSize > 3.9) {
                    $appendedTrafficSourceData = $this->trimOldestTrafficSource($appendedTrafficSourceData);
                }

                $cookies['aiku_tsd'] = [
                    'value'    => $appendedTrafficSourceData,
                    'duration' => 60 * 24 * 120,
                ];
            } else {
                $cookies['aiku_tsd'] = [
                    'value'    => now()->utc()->timestamp.$trafficSourceData,
                    'duration' => 60 * 24 * 120,
                ];
            }
            $cookies['aiku_lts'] = [
                'value'    => $trafficSourceData,
                'duration' => 60 * 24 * 120,
            ];
        }

        return $cookies;
    }


    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function canCaptureTrafficSource(): bool
    {
        $routeName = request()->route() ? request()->route()->getName() : null;

        // Allow only if the route name starts with 'iris' or 'retina', these are the storefront and
        // customer-portal routes served behind Varnish, where CaptureTrafficSourceMiddleWare cannot run,
        // so this action is invoked directly from the AJAX first-hit endpoints and registration actions instead.
        if (!($routeName && (str_starts_with($routeName, 'iris') || str_starts_with($routeName, 'retina')))) {
            return false;
        }
        $website = request()->input('website');


        if (auth()->check() && $website->type == WebsiteTypeEnum::DROPSHIPPING) {
            return false;
        }


        return true;
    }

    public function trimOldestTrafficSource($trafficSourceData): string
    {
        $trafficSourceData = explode(',', $trafficSourceData);
        $trafficSourceData = array_slice($trafficSourceData, 1);

        return implode('|', $trafficSourceData);
    }

}
