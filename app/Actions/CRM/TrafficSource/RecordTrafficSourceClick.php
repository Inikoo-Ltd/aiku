<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\TrafficSource;

use App\Actions\Web\WebsiteVisitor\IsBot;
use App\Actions\Web\WebsiteVisitor\UI\GetBrowserInfo;
use App\Models\CRM\TrafficSourceClick;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class RecordTrafficSourceClick
{
    use AsAction;

    public string $jobQueue = 'low-priority';

    /**
     * The click-level record the aggregates deliberately drop: who arrived, from where, on what
     * device, onto which page. Fraud forensics needs the IP and the ad platform's click id; webpage
     * analytics needs the landing page; both need the bot verdict. Runs queued so the storefront
     * first hit pays nothing beyond the dispatch.
     *
     * @param array{shop_id: int, website_id: int|null, type: string, campaign_ref: string|null, click_id: string|null, ip: string|null, country_code: string|null, user_agent: string|null, url: string|null, is_repeat: bool} $click
     */
    public function handle(array $click): void
    {
        $userAgent = (string) Arr::get($click, 'user_agent');

        TrafficSourceClick::create([
            ...$click,
            'webpage_id'  => $this->resolveWebpage(Arr::get($click, 'website_id'), Arr::get($click, 'url')),
            'device_type' => $userAgent !== '' ? Arr::get(GetBrowserInfo::run($userAgent), 'device') : null,
            'is_bot'      => $userAgent !== '' && IsBot::run($userAgent),
            'user_agent'  => $userAgent !== '' ? substr($userAgent, 0, 1024) : null,
            'created_at'  => now(),
        ]);
    }

    /**
     * Webpage urls are stored as the final slug alone ("csfh", "tcrystal-110"), never the nested
     * storefront path, so only the last segment of the landing path can match. No match is fine -
     * the search page, retired slugs - the raw url column keeps the evidence either way.
     */
    private function resolveWebpage(?int $websiteId, ?string $url): ?int
    {
        if (!$websiteId || !$url) {
            return null;
        }

        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $slug = $path === '' ? '' : basename($path);

        return DB::table('webpages')
            ->where('website_id', $websiteId)
            ->where('url', $slug)
            ->value('id');
    }

}
