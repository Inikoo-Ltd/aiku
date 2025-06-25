<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Sep 2023 17:51:02 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use App\Actions\Web\Website\UI\DetectWebsiteFromDomain;
use App\Models\Web\Website;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class DetectIrisWebsite
{
    public function handle(Request $request, Closure $next): Response
    {
        $domain = DetectWebsiteFromDomain::make()->parseDomain($request->getHost());
        if ($domain === null) {
            abort(404, 'Not found');
        }


        if (config('iris.cache.website.ttl') == 0) {
            $websiteData = $this->getWebpageData($domain);
        } else {
            $key = config('iris.cache.website.prefix')."_$domain";
            $websiteData = Cache::remember(
                $key,
                config('iris.cache.website.ttl'),
                function () use ($domain) {
                    return $this->getWebpageData($domain);
                }
            );
        }


        $request->merge($websiteData);

        return $next($request);
    }

    public function getWebpageData(string $domain): array
    {
        /** @var Website $website */
        $website = Website::where('domain', $domain)->first();
        $shop    = $website->shop;

        return [
            'domain'        => $website->domain,
            'website'       => $website,
            'currency_data' => [
                'code'   => $shop->currency->code,
                'symbol' => $shop->currency->symbol,
                'name'   => $shop->currency->name,
            ],
            'shop_type'     => $shop->type->value,
            'favicons'      => [
                '16'  => $website->faviconSources(16, 16)['original'] ?? url('favicons/iris-favicon-16x16.png'),
                '32'  => $website->faviconSources(32, 32)['original'] ?? url('favicons/iris-favicon-32x32.png'),
                '48'  => $website->faviconSources(48, 48)['original'] ?? url('favicons/iris-favicon.ico'),
                '180' => $website->faviconSources(180, 180)['original'] ?? url('favicons/iris-apple-favicon-180x180.png')

            ]
        ];
    }


}
