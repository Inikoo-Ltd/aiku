<?php

namespace App\Enums\CRM\Poll;

use App\Enums\EnumHelperTrait;

enum PollOptionReferralSourcesEnum: string
{
    use EnumHelperTrait;

    case ORGANIC_GOOGLE     = 'organic_google';
    case GOOGLE_ADS         = 'google_ads';
    case ORGANIC_BING       = 'organic_bing';
    case BING_ADS           = 'bing_ads';
    case ORGANIC_FACEBOOK   = 'organic_facebook';
    case META_ADS           = 'meta_ads';
    case ORGANIC_INSTAGRAM  = 'organic_instagram';
    case ORGANIC_PINTEREST  = 'organic_pinterest';
    case PINTEREST_ADS      = 'pinterest_ads';
    case ORGANIC_TIKTOK     = 'organic_tiktok';
    case TIKTOK_ADS         = 'tiktok_ads';
    case ORGANIC_LINKEDIN   = 'organic_linkedin';
    case LINKEDIN_ADS       = 'linkedin_ads';

    public function label(): string
    {
        return match ($this) {
            self::ORGANIC_GOOGLE    => __('Organic Google'),
            self::GOOGLE_ADS        => __('Google Ads'),
            self::ORGANIC_BING      => __('Organic Bing'),
            self::BING_ADS          => __('Bing Ads'),
            self::ORGANIC_FACEBOOK  => __('Organic Facebook'),
            self::META_ADS          => __('Meta Ads'),
            self::ORGANIC_INSTAGRAM => __('Organic Instagram'),
            self::ORGANIC_PINTEREST => __('Organic Pinterest'),
            self::PINTEREST_ADS     => __('Pinterest Ads'),
            self::ORGANIC_TIKTOK    => __('Organic TikTok'),
            self::TIKTOK_ADS        => __('TikTok Ads'),
            self::ORGANIC_LINKEDIN  => __('Organic LinkedIn'),
            self::LINKEDIN_ADS      => __('LinkedIn Ads'),
        };
    }

    public static function detectFromUrl(string $url): ?self
    {
        $url = strtolower($url);

        $patterns = [
            // Ads first (more specific)
            'gad_source'    => self::GOOGLE_ADS,
            'gclid'         => self::GOOGLE_ADS,
            'msclkid'       => self::BING_ADS,
            'fbclid'        => self::META_ADS,
            'pp=0'          => self::PINTEREST_ADS,
            'pp=1'          => self::PINTEREST_ADS,
            'ttclid'        => self::TIKTOK_ADS,
            'li_fat_id'     => self::LINKEDIN_ADS,

            // Organic
            'google'        => self::ORGANIC_GOOGLE,
            'bing'          => self::ORGANIC_BING,
            'facebook'      => self::ORGANIC_FACEBOOK,
            'instagram'     => self::ORGANIC_INSTAGRAM,
            'pin'           => self::ORGANIC_PINTEREST,
            'pinterest'     => self::ORGANIC_PINTEREST,
            'tiktok'        => self::ORGANIC_TIKTOK,
            'linkedin'      => self::ORGANIC_LINKEDIN,
        ];

        foreach ($patterns as $needle => $source) {
            if (str_contains($url, $needle)) {
                return $source;
            }
        }

        return null;
    }

    public static function stateIcon(): array
    {
        return [
            self::ORGANIC_GOOGLE->value => [
                'tooltip' => __('Organic Google'),
                'icon'    => 'fab fa-google',
                'class'   => 'text-blue-500'
            ],
            self::GOOGLE_ADS->value => [
                'tooltip' => __('Google Ads'),
                'icon'    => 'fab fa-google',
                'class'   => 'text-yellow-500'
            ],
            self::ORGANIC_BING->value => [
                'tooltip' => __('Organic Bing'),
                'icon'    => 'fab fa-microsoft',
                'class'   => 'text-blue-500'
            ],
            self::BING_ADS->value => [
                'tooltip' => __('Bing Ads'),
                'icon'    => 'fab fa-microsoft',
                'class'   => 'text-yellow-500'
            ],
            self::ORGANIC_FACEBOOK->value => [
                'tooltip' => __('Organic Facebook'),
                'icon'    => 'fab fa-facebook',
                'class'   => 'text-blue-600'
            ],
            self::META_ADS->value => [
                'tooltip' => __('Meta Ads'),
                'icon'    => 'fab fa-meta',
                'class'   => 'text-yellow-500'
            ],
            self::ORGANIC_INSTAGRAM->value => [
                'tooltip' => __('Organic Instagram'),
                'icon'    => 'fab fa-instagram',
                'class'   => 'text-pink-500'
            ],
            self::ORGANIC_PINTEREST->value => [
                'tooltip' => __('Organic Pinterest'),
                'icon'    => 'fab fa-pinterest',
                'class'   => 'text-red-500'
            ],
            self::PINTEREST_ADS->value => [
                'tooltip' => __('Pinterest Ads'),
                'icon'    => 'fab fa-pinterest',
                'class'   => 'text-yellow-500'
            ],
            self::ORGANIC_TIKTOK->value => [
                'tooltip' => __('Organic TikTok'),
                'icon'    => 'fab fa-tiktok',
                'class'   => 'text-black'
            ],
            self::TIKTOK_ADS->value => [
                'tooltip' => __('TikTok Ads'),
                'icon'    => 'fab fa-tiktok',
                'class'   => 'text-yellow-500'
            ],
            self::ORGANIC_LINKEDIN->value => [
                'tooltip' => __('Organic LinkedIn'),
                'icon'    => 'fab fa-linkedin',
                'class'   => 'text-blue-700'
            ],
            self::LINKEDIN_ADS->value => [
                'tooltip' => __('LinkedIn Ads'),
                'icon'    => 'fab fa-linkedin',
                'class'   => 'text-yellow-500'
            ]
        ];
    }
}
