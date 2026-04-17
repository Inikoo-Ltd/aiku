<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Web\WebsiteVisitor;

use App\Models\CRM\WebUser;
use App\Models\Web\Website;
use App\Models\Web\WebsiteVisitor;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreWebsiteVisitor
{
    use AsAction;


    public function handle(
        Website $website,
        string $sessionId,
        ?WebUser $webUser,
        string $ip,
        string $device,
        string $browser,
        string $os,
        string $userAgent,
        string $currentUrl,
        ?string $referrer,
        array $geoLocation
    ): WebsiteVisitor {

        $ipHash      = hash('sha256', $ip.config('app.key'));
        $visitorHash = hash('sha256', $ipHash.$userAgent);

        $isNewVisitor = !WebsiteVisitor::where('visitor_hash', $visitorHash)
            ->where('website_id', $website->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->exists();

        $now = now();

        $visitor = WebsiteVisitor::create([
            'group_id'         => $website->group_id,
            'organisation_id'  => $website->organisation_id,
            'shop_id'          => $website->shop_id,
            'website_id'       => $website->id,
            'session_id'       => $sessionId,
            'web_user_id'      => $webUser?->id,
            'visitor_hash'     => $visitorHash,
            'device_type'      => $device,
            'os'               => $os,
            'browser'          => $browser,
            'user_agent'       => $userAgent,
            'ip_hash'          => $ipHash,
            'country_code'     => $geoLocation[0],
            'city'             => $geoLocation[2],
            'page_views'       => 1,
            'duration_seconds' => 0,
            'first_seen_at'    => $now,
            'last_seen_at'     => $now,
            'landing_page'     => $currentUrl,
            'exit_page'        => $currentUrl,
            'referrer_url'     => $referrer,
            'is_bounce'        => true,
            'is_new_visitor'   => $isNewVisitor,
        ]);

        $cacheKey = "visitor:session:$sessionId:$website->id";
        Cache::put($cacheKey, $visitor, 1800);

        return $visitor;
    }
}
