<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 12:42:09 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\TrafficSource;

use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
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

        return null;
    }
}
