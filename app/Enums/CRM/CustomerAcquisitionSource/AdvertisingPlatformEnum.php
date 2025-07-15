<?php

namespace App\Enums\CRM\CustomerAcquisitionSource;

enum AdvertisingPlatformEnum: string
{
    case GOOGLE_ADS = 'google_ads';
    case META_ADS = 'meta_ads';
    case MICROSOFT_ADS = 'microsoft_ads';
    case TIKTOK_ADS = 'tiktok_ads';
    case LINKEDIN_ADS = 'linkedin_ads';
    case PINTEREST_ADS = 'pinterest_ads';
    case SNAPCHAT_ADS = 'snapchat_ads';
    case TWITTER_ADS = 'twitter_ads';
    case AMAZON_ADS = 'amazon_ads';
    case YOUTUBE_ADS = 'youtube_ads';
    case ORGANIC_SEARCH = 'organic_search';
    case DIRECT = 'direct';
    case REFERRAL = 'referral';
    case EMAIL = 'email';
    case SOCIAL_ORGANIC = 'social_organic';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::GOOGLE_ADS => 'Google Ads',
            self::META_ADS => 'Meta Ads (Facebook/Instagram)',
            self::MICROSOFT_ADS => 'Microsoft Advertising (Bing)',
            self::TIKTOK_ADS => 'TikTok Ads',
            self::LINKEDIN_ADS => 'LinkedIn Ads',
            self::PINTEREST_ADS => 'Pinterest Ads',
            self::SNAPCHAT_ADS => 'Snapchat Ads',
            self::TWITTER_ADS => 'Twitter Ads',
            self::AMAZON_ADS => 'Amazon Advertising',
            self::YOUTUBE_ADS => 'YouTube Ads',
            self::ORGANIC_SEARCH => 'Organic Search',
            self::DIRECT => 'Direct Traffic',
            self::REFERRAL => 'Referral',
            self::EMAIL => 'Email',
            self::SOCIAL_ORGANIC => 'Organic Social',
            self::OTHER => 'Other',
        };
    }

    public function trackingParameter(): string
    {
        return match ($this) {
            self::GOOGLE_ADS => 'gclid',
            self::META_ADS => 'fbclid',
            self::MICROSOFT_ADS => 'msclkid',
            self::TIKTOK_ADS => 'ttclid',
            self::LINKEDIN_ADS => 'li_fat_id',
            self::PINTEREST_ADS => 'epik',
            self::SNAPCHAT_ADS => 'ScCid',
            self::TWITTER_ADS => 'twclid',
            self::AMAZON_ADS => 'aclid',
            self::YOUTUBE_ADS => 'gclid',
            default => 'utm_source',
        };
    }

    public function attributionWindowDays(): int
    {
        return match ($this) {
            self::GOOGLE_ADS => 90,
            self::META_ADS => 28,
            self::MICROSOFT_ADS => 90,
            self::TIKTOK_ADS => 28,
            self::LINKEDIN_ADS => 30,
            self::PINTEREST_ADS => 30,
            self::SNAPCHAT_ADS => 28,
            self::TWITTER_ADS => 30,
            self::AMAZON_ADS => 14,
            self::YOUTUBE_ADS => 90,
            default => 30,
        };
    }

    public function supportsConversionTracking(): bool
    {
        return match ($this) {
            self::GOOGLE_ADS,
            self::META_ADS,
            self::MICROSOFT_ADS,
            self::TIKTOK_ADS,
            self::LINKEDIN_ADS,
            self::PINTEREST_ADS,
            self::SNAPCHAT_ADS,
            self::TWITTER_ADS => true,
            default => false,
        };
    }

    public static function fromTrackingParameter(string $parameter): ?self
    {
        return match ($parameter) {
            'gclid' => self::GOOGLE_ADS,
            'fbclid', '_fbp' => self::META_ADS,
            'msclkid' => self::MICROSOFT_ADS,
            'ttclid' => self::TIKTOK_ADS,
            'li_fat_id' => self::LINKEDIN_ADS,
            'epik' => self::PINTEREST_ADS,
            'ScCid' => self::SNAPCHAT_ADS,
            'twclid' => self::TWITTER_ADS,
            'aclid' => self::AMAZON_ADS,
            default => null,
        };
    }
}
