<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2025 15:59:43 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Enums\CRM\TrafficSource;

use App\Enums\EnumHelperTrait;

/**
 * Enum for traffic source types
 */
enum TrafficSourcesTypeEnum: string
{
    use EnumHelperTrait;

    /**
     * Instagram spend and Instagram clicks both name a Meta campaign by the same numeric id, and a
     * campaign `reference` is unique across every traffic source, so the Instagram row has to claim a
     * different string: otherwise the two halves of one Meta campaign fight over a single campaign row
     * and whichever arrives second loses its breakdown.
     */
    public const string INSTAGRAM_CAMPAIGN_PREFIX = 'ig-';

    case ORGANIC_GOOGLE = 'organic-google';
    case GOOGLE_ADS = 'google-ads';
    case ORGANIC_BING = 'organic-bing';
    case BING_ADS = 'bing-ads';
    case ORGANIC_META = 'organic-meta';
    case META_ADS = 'meta-ads';
    case INSTAGRAM_ADS = 'instagram-ads';
    case ORGANIC_PINTEREST = 'organic-pinterest';
    case PINTEREST_ADS = 'pinterest-ads';
    case ORGANIC_TIKTOK = 'organic-tiktok';
    case TIKTOK_ADS = 'tiktok-ads';
    case ORGANIC_LINKEDIN = 'organic-linkedin';
    case LINKEDIN_ADS = 'linkedin-ads';
    case ORGANIC_TWITTER = 'organic-twitter';
    case TWITTER_ADS = 'twitter-ads';
    case YOUTUBE = 'youtube';
    case NEWSLETTER = 'newsletter';
    case MARKETING_MAILSHOT = 'marketing-mailshot';
    case EMAIL_AUTOMATED = 'email-automated';
    case ORGANIC_SEARCH = 'organic-search';
    case REFERRAL = 'referral';

    public static function labels(): array
    {
        return [
            self::ORGANIC_GOOGLE->value    => 'Organic Google',
            self::GOOGLE_ADS->value        => 'Google Ads',
            self::ORGANIC_BING->value      => 'Organic Bing',
            self::BING_ADS->value          => 'Bing Ads',
            self::ORGANIC_META->value      => 'Organic Meta',
            self::META_ADS->value          => 'Meta Ads',
            self::INSTAGRAM_ADS->value     => 'Instagram Ads',
            self::ORGANIC_PINTEREST->value => 'Organic Pinterest',
            self::PINTEREST_ADS->value     => 'Pinterest Ads',
            self::ORGANIC_TIKTOK->value    => 'Organic TikTok',
            self::TIKTOK_ADS->value        => 'TikTok Ads',
            self::ORGANIC_LINKEDIN->value  => 'Organic LinkedIn',
            self::LINKEDIN_ADS->value      => 'LinkedIn Ads',
            self::ORGANIC_TWITTER->value   => 'Organic Twitter',
            self::TWITTER_ADS->value       => 'Twitter Ads',
            self::YOUTUBE->value           => 'YouTube',
            self::NEWSLETTER->value        => 'Newsletter',
            self::MARKETING_MAILSHOT->value => 'Marketing Mailshots',
            self::EMAIL_AUTOMATED->value   => 'Automatic Marketing',
            self::ORGANIC_SEARCH->value    => 'Organic Search (other)',
            self::REFERRAL->value          => 'Referral',
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
            self::INSTAGRAM_ADS->value     => true,
            self::ORGANIC_PINTEREST->value => true,
            self::PINTEREST_ADS->value     => false,
            self::ORGANIC_TIKTOK->value    => true,
            self::TIKTOK_ADS->value        => false,
            self::ORGANIC_LINKEDIN->value  => true,
            self::LINKEDIN_ADS->value      => false,
            self::ORGANIC_TWITTER->value   => true,
            self::TWITTER_ADS->value       => false,
            self::YOUTUBE->value           => true,
            self::NEWSLETTER->value        => true,
            self::MARKETING_MAILSHOT->value => true,
            self::EMAIL_AUTOMATED->value   => true,
            self::ORGANIC_SEARCH->value    => true,
            self::REFERRAL->value          => true,
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
            self::INSTAGRAM_ADS->value     => 'u',
            self::ORGANIC_PINTEREST->value => 'g',
            self::PINTEREST_ADS->value     => 'h',
            self::ORGANIC_TIKTOK->value    => 'i',
            self::TIKTOK_ADS->value        => 'j',
            self::ORGANIC_LINKEDIN->value  => 'k',
            self::LINKEDIN_ADS->value      => 'l',
            self::ORGANIC_TWITTER->value   => 'm',
            self::TWITTER_ADS->value       => 'n',
            self::YOUTUBE->value           => 'o',
            self::NEWSLETTER->value        => 'p',
            self::MARKETING_MAILSHOT->value => 't',
            self::EMAIL_AUTOMATED->value   => 's',
            self::ORGANIC_SEARCH->value    => 'r',
            self::REFERRAL->value          => 'q',
        ];
    }

    public static function fromAbbr(string $abbreviation): ?self
    {
        $map = array_flip(self::abbr());
        $typeValue = $map[$abbreviation] ?? null;

        if ($typeValue === null) {
            return null;
        }

        return self::tryFrom($typeValue);
    }

    /**
     * Which channel a mailshot's clicks belong to. A newsletter and a promotional mailshot are
     * different instruments - one keeps a list warm, the other pushes an offer - and averaging them
     * hides which of the two is working.
     */
    public static function fromMailshotType(?string $mailshotType): self
    {
        return $mailshotType === 'newsletter' ? self::NEWSLETTER : self::MARKETING_MAILSHOT;
    }

    /**
     * How the channels group on a dashboard. Nineteen rows is a list nobody reads; four groups is a
     * question anybody can answer - did the paid stuff work, is search bringing people, is email
     * carrying us.
     *
     * @return array{key: string, label: string, position: int}
     */
    public function group(): array
    {
        return match (true) {
            $this->isPaid()                                        => ['key' => 'paid', 'label' => __('Paid ads'), 'position' => 1],
            str_starts_with($this->value, 'organic-')              => ['key' => 'organic', 'label' => __('Organic'), 'position' => 2],
            in_array($this, [self::NEWSLETTER, self::MARKETING_MAILSHOT, self::EMAIL_AUTOMATED], true)
                                                                   => ['key' => 'email', 'label' => __('Email'), 'position' => 3],
            default                                                => ['key' => 'other', 'label' => __('Other'), 'position' => 4],
        };
    }

    /**
     * Paid traffic types are the ones an advertiser is actually billed for (the `-ads` types),
     * as opposed to the `organic-*` types. Used by the last-paid-touch attribution model.
     */
    public function isPaid(): bool
    {
        return str_ends_with($this->value, '-ads');
    }





}
