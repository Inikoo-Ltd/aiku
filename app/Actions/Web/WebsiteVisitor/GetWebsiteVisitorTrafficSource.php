<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\WebsiteVisitor;

use App\Actions\CRM\TrafficSource\GetTrafficSourceFromRefererHeader;
use App\Actions\CRM\TrafficSource\GetTrafficSourceFromUrl;
use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Enums\Web\WebsiteVisitor\WebsiteVisitorChannelEnum;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class GetWebsiteVisitorTrafficSource
{
    use AsAction;

    private const array EMAIL_APP_HOSTS = ['com.google.android.gm'];

    /**
     * @return array{traffic_source_type: string, traffic_source_reference: string|null}
     */
    public function handle(?string $landingPage, ?string $referrer, int $websiteId, string $visitorHash): array
    {
        if ($fromLandingPage = $this->fromLandingPage($landingPage)) {
            return $fromLandingPage;
        }

        $previousVisit = DB::table('website_visitors')
            ->where('website_id', $websiteId)
            ->where('visitor_hash', $visitorHash)
            ->where('last_seen_at', '>=', now()->subSeconds(UpdateWebsiteVisitor::MAX_IDLE_SECONDS))
            ->whereNotNull('traffic_source_type')
            ->orderByDesc('last_seen_at')
            ->first(['traffic_source_type', 'traffic_source_reference']);

        return $previousVisit ? (array) $previousVisit : $this->fromReferrer($referrer);
    }

    /**
     * @return array{traffic_source_type: string, traffic_source_reference: string|null}|null
     */
    public function fromLandingPage(?string $landingPage): ?array
    {
        $landingPage = (string) $landingPage;

        if ($trafficSourceData = GetTrafficSourceFromUrl::run($landingPage)) {
            return $this->fromTrafficSourceData($trafficSourceData);
        }

        parse_str((string) parse_url($landingPage, PHP_URL_QUERY), $queryParams);

        if (strtolower((string) Arr::get($queryParams, 'utm_medium')) === 'email') {
            return $this->source(WebsiteVisitorChannelEnum::EMAIL_TYPE, Arr::get($queryParams, 'utm_campaign'));
        }

        $utmSource = (string) Arr::get($queryParams, 'utm_source');

        if (GetTrafficSourceFromRefererHeader::isAiAssistantHost($utmSource)) {
            return $this->source(TrafficSourcesTypeEnum::AI->value, GetTrafficSourceFromRefererHeader::normaliseHost($utmSource));
        }

        return null;
    }

    /**
     * @return array{traffic_source_type: string, traffic_source_reference: string|null}
     */
    public function fromReferrer(?string $referrer): array
    {
        $referrer = trim((string) $referrer);

        if ($referrer === '') {
            return $this->source(TrafficSourcesTypeEnum::DIRECT->value);
        }

        $referrerHost = strtolower((string) parse_url($referrer, PHP_URL_HOST));

        if (in_array($referrerHost, self::EMAIL_APP_HOSTS, true) || $this->isWebmail($referrerHost)) {
            return $this->source(WebsiteVisitorChannelEnum::EMAIL_TYPE, $referrerHost);
        }

        if ($trafficSourceData = GetTrafficSourceFromRefererHeader::run($referrer)) {
            return $this->fromTrafficSourceData($trafficSourceData);
        }

        return $this->source(WebsiteVisitorChannelEnum::INTERNAL_TYPE, $referrerHost ?: null);
    }

    private function fromTrafficSourceData(string $trafficSourceData): array
    {
        $type = TrafficSourcesTypeEnum::fromAbbr(substr($trafficSourceData, 0, 1)) ?? TrafficSourcesTypeEnum::REFERRAL;

        return $this->source($type->value, substr($trafficSourceData, 1));
    }

    private function isWebmail(string $host): bool
    {
        $host = preg_replace('/^www\./', '', $host) ?? '';

        if ($host === '') {
            return false;
        }

        foreach ((array) config('marketing.webmail_referrer_patterns', []) as $pattern) {
            if (preg_match($pattern, $host)) {
                return true;
            }
        }

        foreach ((array) config('marketing.webmail_referrer_domains', []) as $domain) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{traffic_source_type: string, traffic_source_reference: string|null}
     */
    private function source(string $type, ?string $reference = null): array
    {
        return [
            'traffic_source_type'      => $type,
            'traffic_source_reference' => filled($reference) ? mb_substr($reference, 0, 255) : null,
        ];
    }
}
