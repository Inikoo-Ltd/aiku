<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Web\WebsiteVisitor;

use App\Actions\CRM\Customer\SyncCustomerWebActivities;
use App\Actions\Web\WebsiteVisitor\UI\GetBrowserInfo;
use App\Actions\Web\WebsitePageView\StoreWebsitePageView;
use App\Models\CRM\WebUser;
use App\Models\Web\Website;
use App\Models\Web\WebsiteVisitor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessWebsiteVisitorTracking implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'analytics';
    public int $jobTimeout = 120;
    public int $jobTries = 1;

    public function handle(
        string $sessionId,
        Website $website,
        ?WebUser $webUser,
        string $userAgent,
        array $ips,
        string $currentUrl,
        ?string $referrer
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
        } else {

            $browserData = GetBrowserInfo::run($userAgent);

            $visitor = StoreWebsiteVisitor::run(
                website: $website,
                sessionId: $sessionId,
                webUser: $webUser,
                ips: $ips,
                device: $browserData['device'],
                browser: $browserData['browser'],
                os: $browserData['os'],
                userAgent: $userAgent,
                currentUrl: $currentUrl,
                referrer: $referrer,
            );
        }

        StoreWebsitePageView::dispatch($visitor, $website, $currentUrl);

        if ($visitor->web_user_id) {
            $customer = $visitor->webUser?->customer;

            if ($customer) {
                SyncCustomerWebActivities::dispatch($customer, now()->startOfDay());
            }
        }
    }
}
