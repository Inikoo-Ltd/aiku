<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 15:59:43 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Enums\CRM\TrafficSourceCampaign;

use App\Enums\EnumHelperTrait;

/**
 * Enum for traffic source types
 */
enum TrafficSourceCampaignTypeEnum: string
{
    use EnumHelperTrait;

    case ORGANIC_GOOGLE = 'organic-google';
    case GOOGLE_ADS = 'google-ads';
    case ORGANIC_BING = 'organic-bing';
    case BING_ADS = 'bing-ads';
    case ORGANIC_META = 'organic-meta';
    case META_ADS = 'meta-ads';
    case ORGANIC_PINTEREST = 'organic-pinterest';
    case PINTEREST_ADS = 'pinterest-ads';
    case ORGANIC_TIKTOK = 'organic-tiktok';
    case TIKTOK_ADS = 'tiktok-ads';
    case ORGANIC_LINKEDIN = 'organic-linkedin';
    case LINKEDIN_ADS = 'linkedin-ads';
    case ORGANIC_TWITTER = 'organic-twitter';
    case TWITTER_ADS = 'twitter-ads';
    case YOUTUBE = 'youtube';

    public static function labels(): array
    {
        return [
            self::ORGANIC_GOOGLE->value    => 'Organic Google',
            self::GOOGLE_ADS->value        => 'Google Ads',
            self::ORGANIC_BING->value      => 'Organic Bing',
            self::BING_ADS->value          => 'Bing Ads',
            self::ORGANIC_META->value      => 'Organic Meta',
            self::META_ADS->value          => 'Meta Ads',
            self::ORGANIC_PINTEREST->value => 'Organic Pinterest',
            self::PINTEREST_ADS->value     => 'Pinterest Ads',
            self::ORGANIC_TIKTOK->value    => 'Organic TikTok',
            self::TIKTOK_ADS->value        => 'TikTok Ads',
            self::ORGANIC_LINKEDIN->value  => 'Organic LinkedIn',
            self::LINKEDIN_ADS->value      => 'LinkedIn Ads',
            self::ORGANIC_TWITTER->value   => 'Organic Twitter',
            self::TWITTER_ADS->value       => 'Twitter Ads',
            self::YOUTUBE->value           => 'Youtube',
        ];
    }

    public static function status(): array
    {
        return [
            self::ORGANIC_GOOGLE->value    => true,
            self::GOOGLE_ADS->value        => true,
            self::ORGANIC_BING->value      => true,
            self::BING_ADS->value          => true,
            self::ORGANIC_META->value      => true,
            self::META_ADS->value          => true,
            self::ORGANIC_PINTEREST->value => true,
            self::PINTEREST_ADS->value     => false,
            self::ORGANIC_TIKTOK->value    => true,
            self::TIKTOK_ADS->value        => false,
            self::ORGANIC_LINKEDIN->value  => true,
            self::LINKEDIN_ADS->value      => false,
            self::ORGANIC_TWITTER->value   => true,
            self::TWITTER_ADS->value       => false,
            self::YOUTUBE->value           => true,
        ];
    }

    public static function abbr(): array
    {
        return [
            self::ORGANIC_GOOGLE->value    => 'a',
            self::GOOGLE_ADS->value        => 'b',
            self::ORGANIC_BING->value      => 'c',
            self::BING_ADS->value          => 'd',
            self::ORGANIC_META->value      => 'e',
            self::META_ADS->value          => 'f',
            self::ORGANIC_PINTEREST->value => 'g',
            self::PINTEREST_ADS->value     => 'h',
            self::ORGANIC_TIKTOK->value    => 'i',
            self::TIKTOK_ADS->value        => 'j',
            self::ORGANIC_LINKEDIN->value  => 'k',
            self::LINKEDIN_ADS->value      => 'l',
            self::ORGANIC_TWITTER->value   => 'm',
            self::TWITTER_ADS->value       => 'n',
            self::YOUTUBE->value           => 'o',
        ];
    }
}
