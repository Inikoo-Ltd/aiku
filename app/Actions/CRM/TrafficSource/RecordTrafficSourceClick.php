<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\TrafficSource;

use App\Actions\Web\WebsiteVisitor\IsBot;
use App\Actions\Web\WebsiteVisitor\UI\GetBrowserInfo;
use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Models\CRM\ScannerIp;
use App\Models\CRM\TrafficSourceClick;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class RecordTrafficSourceClick
{
    use AsAction;

    public string $jobQueue = 'low-priority';

    /**
     * A mail security scanner detonates every link in a message at once, so its signature is many
     * clicks from one IP on one campaign inside a burst - 20 in 25 seconds from Microsoft's ranges
     * on the first day measured, against 6 human clicks. Five different clicks from one address on
     * one campaign within the counter's lifetime is the line; a person rereading a newsletter that
     * thoroughly inside fifteen minutes is rare enough to spend.
     *
     * ponytail: threshold and window are constants; make them config the day a tenant disagrees.
     */
    public const SCANNER_CLICK_THRESHOLD = 5;
    public const SCANNER_WINDOW_MINUTES  = 15;

    public static function countScannerClick(?string $ip, ?string $campaignRef): void
    {
        if (!$ip || !$campaignRef) {
            return;
        }

        try {
            $key = 'scanner_clicks:'.$ip.':'.$campaignRef;
            Cache::add($key, 0, now()->addMinutes(self::SCANNER_WINDOW_MINUTES));
            Cache::increment($key);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public static function isScannerBurst(?string $ip, ?string $campaignRef): bool
    {
        if (!$ip || !$campaignRef) {
            return false;
        }

        try {
            if ((int) Cache::get('scanner_clicks:'.$ip.':'.$campaignRef, 0) >= self::SCANNER_CLICK_THRESHOLD) {
                /* Every detection path converges here, so this is where an IP earns its listing.
                   The write is idempotent per (ip, campaign) and only runs in queued jobs. */
                ScannerIp::recordBurst($ip, $campaignRef);

                return true;
            }

            /* A listed IP - one that has burst several different campaigns - is a scanner from its
               first click, which is the only way to catch it on a single-link email where no burst
               can ever form. */
            return ScannerIp::isListed($ip);
        } catch (\Throwable) {
            return false;
        }
    }

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

        /* Email clicks run this job two minutes delayed, after any scanner burst has finished, so
           the verdict here is conclusive. A scanner click is recorded as a bot - the fraud tab must
           see it - but counts no visit; the visit for email channels is counted here rather than at
           the tracking event exactly so the scanner can be filtered out of it. */
        $countVisit = (bool) Arr::pull($click, 'count_visit');
        $scanner    = Arr::pull($click, 'check_scanner')
            && self::isScannerBurst(Arr::get($click, 'ip'), Arr::get($click, 'campaign_ref'));

        TrafficSourceClick::create([
            ...$click,
            'webpage_id'  => $this->resolveWebpage(Arr::get($click, 'website_id'), Arr::get($click, 'url')),
            'device_type' => $userAgent !== '' ? Arr::get(GetBrowserInfo::run($userAgent), 'device') : null,
            'is_bot'      => $scanner || ($userAgent !== '' && IsBot::run($userAgent)),
            'user_agent'  => $userAgent !== '' ? substr($userAgent, 0, 1024) : null,
            'created_at'  => now(),
        ]);

        if ($countVisit && !$scanner) {
            $type = TrafficSourcesTypeEnum::tryFrom((string) Arr::get($click, 'type'));

            if ($type) {
                RecordTrafficSourceVisit::run(Arr::get($click, 'shop_id'), $type);
            }
        }
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
