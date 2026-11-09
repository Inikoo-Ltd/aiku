<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Web\WebsiteVisitor;

use App\Actions\CRM\Customer\SyncCustomerWebActivities;
use App\Actions\Web\Website\Hydrators\WebsiteHydrateNumberHitsLast24Hours;
use App\Actions\Web\Website\Hydrators\WebsiteHydrateNumberVisitorsLast24Hours;
use App\Actions\Web\WebsiteVisitor\UI\GetBrowserInfo;
use App\Actions\Web\WebsitePageView\StoreWebsitePageView;
use App\Models\CRM\WebUser;
use App\Models\Web\Website;
use App\Models\Web\WebsiteVisitor;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessWebsiteVisitorTracking
{
    use AsAction;

    public string $jobQueue = 'analytics';
    public int $jobTimeout = 120;
    public int $jobTries = 1;

    public const int MAX_NEW_VISITORS_PER_HOUR = 60;


    public function handle(
        string $sessionId,
        Website $website,
        ?WebUser $webUser,
        string $userAgent,
        string $ip,
        string $currentUrl,
        ?string $referrer,
        array $geoLocation,
        ?string $visitorUuid = null
    ): void {
        if (IsBot::run($userAgent)) {
            return;
        }

        $cacheKey = "visitor:session:$sessionId:$website->id";
        $visitor  = Cache::remember($cacheKey, 1800, function () use ($sessionId, $website) {
            return WebsiteVisitor::where('session_id', $sessionId)
                ->where('website_id', $website->id)
                ->first();
        });

        if ($visitor) {
            $visitor = UpdateWebsiteVisitor::run($visitor, $currentUrl);

            if ($visitorUuid && ! $visitor->visitor_uuid) {
                $visitor->update(['visitor_uuid' => $visitorUuid]);
            }
        } else {
            if (! $webUser && $this->isCookielessFlood($ip, $userAgent)) {
                return;
            }

            $browserData = GetBrowserInfo::run($userAgent);

            $visitor = StoreWebsiteVisitor::run(
                website: $website,
                sessionId: $sessionId,
                webUser: $webUser,
                ip: $ip,
                device: $browserData['device'],
                browser: $browserData['browser'],
                os: $browserData['os'],
                userAgent: $userAgent,
                currentUrl: $currentUrl,
                referrer: $referrer,
                geoLocation: $geoLocation,
                visitorUuid: $visitorUuid
            );
        }

        StoreWebsitePageView::dispatch($visitor, $website, $currentUrl)->delay(5);

        WebsiteHydrateNumberVisitorsLast24Hours::dispatch($website->id)->delay(900);

        WebsiteHydrateNumberHitsLast24Hours::dispatch($website->id)->delay(900);

        if ($visitor->web_user_id) {
            $customer = $visitor->webUser?->customer;

            if ($customer) {
                SyncCustomerWebActivities::dispatch($customer, now()->startOfDay())->delay(10);
            }
        }
    }

    /**
     * A browser that drops its cookies starts a new visitor on every page. Real shoppers keep the
     * cookie; the busiest real ip and browser pair made 51 new visitors in an hour, while the bot
     * on 8 Nov 2026 made up to 1,800 an hour from each ip with a normal Chrome user agent.
     */
    protected function isCookielessFlood(string $ip, string $userAgent): bool
    {
        $key = 'visitor:new:'.StoreWebsiteVisitor::hashVisitor($ip, $userAgent)['visitor_hash'].':'.now()->format('YmdH');
        Cache::add($key, 0, 3600);

        return Cache::increment($key) > self::MAX_NEW_VISITORS_PER_HOUR;
    }
}
