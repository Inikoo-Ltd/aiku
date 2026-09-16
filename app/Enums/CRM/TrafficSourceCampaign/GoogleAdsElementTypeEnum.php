<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Enums\CRM\TrafficSourceCampaign;

use App\Enums\EnumHelperTrait;

/**
 * The parts of a campaign Aiku can switch on and off, and where each one lives in the Google Ads API.
 *
 * Ads and keywords are addressed by a compound id, `adGroupId~ownId`, because neither is unique on
 * its own: Google identifies them by the ad group they hang off. Ad groups are not.
 */
enum GoogleAdsElementTypeEnum: string
{
    use EnumHelperTrait;

    case AD_GROUP = 'ad_group';
    case AD = 'ad';
    case KEYWORD = 'keyword';

    public static function labels(): array
    {
        return [
            self::AD_GROUP->value => 'Ad group',
            self::AD->value       => 'Ad',
            self::KEYWORD->value  => 'Keyword',
        ];
    }

    /** The mutate service, i.e. the path segment before `:mutate`. */
    public function service(): string
    {
        return match ($this) {
            self::AD_GROUP => 'adGroups',
            self::AD       => 'adGroupAds',
            self::KEYWORD  => 'adGroupCriteria',
        };
    }

    public function resourceName(string $customerId, string $adGroupId, string $id): string
    {
        return match ($this) {
            self::AD_GROUP => "customers/{$customerId}/adGroups/{$adGroupId}",
            self::AD       => "customers/{$customerId}/adGroupAds/{$adGroupId}~{$id}",
            self::KEYWORD  => "customers/{$customerId}/adGroupCriteria/{$adGroupId}~{$id}",
        };
    }

    /**
     * Where this element's stored copy sits inside the campaign's `ad_groups` blob, so a pushed change
     * can be written back to the row the page reads without re-fetching the whole account.
     */
    public function collectionKey(): ?string
    {
        return match ($this) {
            self::AD_GROUP => null,
            self::AD       => 'ads',
            self::KEYWORD  => 'keywords',
        };
    }
}
