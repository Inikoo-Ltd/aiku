<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Web\WebsiteVisitor;

use App\Actions\Web\WebsitePageView\StoreWebsitePageView;
use App\Actions\Utils\GetOsFromUserAgent;
use App\Models\CRM\WebUser;
use App\Models\Web\Website;
use App\Models\Web\WebsiteVisitor;
use hisorange\BrowserDetect\Parser as Browser;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessWebsiteVisitorTracking
{
    use AsAction;

    public string $jobQueue = 'analytics';

    public function handle(
        string $sessionId,
        Website $website,
        ?WebUser $webUser,
        string $userAgent,
        array $ips,
        string $currentUrl,
        ?string $referrer
    ): void {
        $cacheKey = "visitor:session:$sessionId:$website->id";
        $visitor = Cache::remember($cacheKey, 1800, function () use ($sessionId, $website) {
            return WebsiteVisitor::where('session_id', $sessionId)
                ->where('website_id', $website->id)
                ->first();
        });

        if ($visitor) {
            $visitor = UpdateWebsiteVisitor::run($visitor, $currentUrl);
        } else {
            $parsedUserAgent = (new Browser())->parse($userAgent);
            $device = $parsedUserAgent->deviceType();
            $browser = explode(' ', $parsedUserAgent->browserName())[0] ?: 'Unknown';
            $os = GetOsFromUserAgent::run($parsedUserAgent);

            $visitor = StoreWebsiteVisitor::run(
                website: $website,
                sessionId: $sessionId,
                webUser: $webUser,
                ips: $ips,
                device: $device,
                browser: $browser,
                os: $os,
                userAgent: $userAgent,
                currentUrl: $currentUrl,
                referrer: $referrer,
            );
        }

        StoreWebsitePageView::run($visitor, $website, $currentUrl);
    }
}
