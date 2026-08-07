<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 12:42:09 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\TrafficSource;

use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class GetTrafficSourceFromRefererHeader
{
    use AsAction;

    public function handle($url): ?string
    {
        if (!$url) {
            return null;
        }

        $urlComponents = parse_url($url);
        if (!isset($urlComponents['host'])) {
            return null;
        }

        $domain = $urlComponents['host'];


        if (preg_match('/google\.[a-z.]{2,}$/', $domain)) {
            return TrafficSourcesTypeEnum::abbr()[TrafficSourcesTypeEnum::ORGANIC_GOOGLE->value];
        }

        if (preg_match('/bing\.[a-z.]{2,}$/', $domain)) {
            return TrafficSourcesTypeEnum::abbr()[TrafficSourcesTypeEnum::ORGANIC_BING->value];
        }

        // Check for Meta platforms (Facebook, Instagram, etc.)
        if (preg_match('/facebook\.com$/', $domain) ||
            preg_match('/instagram\.com$/', $domain) ||
            preg_match('/threads\.net$/', $domain) ||
            preg_match('/messenger\.com$/', $domain) ||
            preg_match('/whatsapp\.com$/', $domain)) {
            return TrafficSourcesTypeEnum::abbr()[TrafficSourcesTypeEnum::ORGANIC_META->value];
        }

        // Check for YouTube referrals
        if (preg_match('/youtube\.com$/', $domain) ||
            preg_match('/youtu\.be$/', $domain)) {
            return TrafficSourcesTypeEnum::abbr()[TrafficSourcesTypeEnum::YOUTUBE->value];
        }

        // Check for LinkedIn referrals
        if (preg_match('/linkedin\.com$/', $domain)) {
            return TrafficSourcesTypeEnum::abbr()[TrafficSourcesTypeEnum::ORGANIC_LINKEDIN->value];
        }

        // Check for Pinterest referrals
        if (preg_match('/pinterest\.[a-z.]{2,}$/', $domain)) {
            return TrafficSourcesTypeEnum::abbr()[TrafficSourcesTypeEnum::ORGANIC_PINTEREST->value];
        }

        // Check for TikTok referrals
        if (preg_match('/tiktok\.com$/', $domain)) {
            return TrafficSourcesTypeEnum::abbr()[TrafficSourcesTypeEnum::ORGANIC_TIKTOK->value];
        }

        // Check for Twitter (X) referrals
        if (preg_match('/twitter\.com$/', $domain) ||
            preg_match('/x\.com$/', $domain)) {
            return TrafficSourcesTypeEnum::abbr()[TrafficSourcesTypeEnum::ORGANIC_TWITTER->value];
        }

        /* A click that came through Google's ad redirect is paid Google traffic, not a referral from
           some website called googleadservices. It reaches us this way whenever the gclid is missing
           from the landing URL - stripped by a redirect, or a tracking template that omits it. No
           campaign reference is available on this path; the channel is still right. */
        if (preg_match('/(^|\.)googleadservices\.com$/', $domain) ||
            preg_match('/(^|\.)googlesyndication\.com$/', $domain)) {
            return TrafficSourcesTypeEnum::abbr()[TrafficSourcesTypeEnum::GOOGLE_ADS->value];
        }

        /* Search engines we do not have a channel of their own for. They are search traffic, not
           referrals from a website, and lumping them together would have hidden them among trade
           directories and blogs. The engine's host rides along as the campaign reference, so the
           breakdown still names each one.
           Matched on the full host on purpose: `search.seznam.cz` is a search engine,
           `email.seznam.cz` is somebody's webmail and belongs nowhere near it. */
        $searchEngines = [
            '/(^|\.)duckduckgo\.com$/',
            '/(^|\.)search\.yahoo\.com$/',
            '/(^|\.)search\.brave\.com$/',
            '/(^|\.)search\.seznam\.cz$/',
            '/(^|\.)ecosia\.org$/',
            '/(^|\.)startpage\.com$/',
            '/(^|\.)qwant\.com$/',
            '/(^|\.)baidu\.com$/',
            '/(^|\.)naver\.com$/',
            '/(^|\.)yandex\.[a-z.]{2,}$/',
        ];

        foreach ($searchEngines as $pattern) {
            if (preg_match($pattern, $domain)) {
                $engine = self::normaliseHost($domain);

                return TrafficSourcesTypeEnum::abbr()[TrafficSourcesTypeEnum::ORGANIC_SEARCH->value]
                    .($engine ?? '');
            }
        }

        /* Anything else external is a referral rather than nothing: a trade directory, a blog, an AI
           assistant. Before this they were indistinguishable from someone typing the address in, and
           whole acquisition channels were invisible. The host travels as the campaign reference so
           each referrer is reportable on its own. */
        $host = self::normaliseHost($domain);

        if ($host === null) {
            return null;
        }

        return TrafficSourcesTypeEnum::abbr()[TrafficSourcesTypeEnum::REFERRAL->value].$host;
    }

    /**
     * A referrer host is only usable as a campaign reference if it is shaped like a hostname: it comes
     * from a client-controlled header, and it ends up in the customer's stored touch history.
     *
     * Returns null for our own systems, which are not marketing sources.
     */
    public static function normaliseHost(?string $domain): ?string
    {
        $host = strtolower(trim((string) $domain));
        $host = preg_replace('/^www\./', '', $host) ?? '';

        if (!preg_match('/^(?=.{4,60}$)[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $host)) {
            return null;
        }

        if ($host === strtolower((string) request()->getHost())) {
            return null;
        }

        foreach ((array) config('marketing.internal_referrer_hosts', []) as $internal) {
            if ($host === $internal || str_ends_with($host, '.'.$internal)) {
                return null;
            }
        }

        /* Our own storefronts refer each other constantly - a customer moving from awgifts.eu to
           ancientwisdom.biz is cross-shop navigation, not an acquisition. Every referral host seen in
           the first hours of capture was one of ours. */
        if (self::ourOwnDomains()->contains($host)) {
            return null;
        }

        return $host;
    }

    /**
     * Cached: this runs on the storefront first hit, and the set of domains we own changes about
     * never. Cleared naturally within the hour if a website is added.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    private static function ourOwnDomains(): \Illuminate\Support\Collection
    {
        try {
            return Cache::remember('marketing:our_own_domains', now()->addHour(), function () {
                return DB::table('websites')
                    ->whereNotNull('domain')
                    ->pluck('domain')
                    ->map(fn (string $domain) => preg_replace('/^www\./', '', strtolower(trim($domain))))
                    ->filter()
                    ->values();
            });
        } catch (\Throwable) {
            /* Never break a page view over this; the worst case is one of our own domains being
               recorded as a referral until the next request succeeds. */
            return collect();
        }
    }
}
