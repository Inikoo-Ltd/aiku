<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\TrafficSourceCampaign\GoogleAds;

use App\Models\Catalogue\Shop;
use App\Services\GoogleAds\GoogleAdsClient;
use App\Services\GoogleAds\GoogleAdsException;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * What a campaign is made of, read from Google for every campaign type the account runs.
 *
 * Search campaigns are ad groups with keywords and text ads. Performance Max has no ad groups at all:
 * its creative sits in asset groups steered by search themes and audience signals. Demand Gen, Display
 * and Video campaigns have ad groups whose ads are images and videos and whose targeting is audiences,
 * placements and topics rather than keywords. Each shape is read with its own queries and stored in
 * one structure the campaign page can walk without knowing which kind it is looking at.
 *
 * Every query here is optional: a campaign type the account does not run returns nothing, and a query
 * Google refuses costs that one section and is logged, never the fetch.
 */
class ReadGoogleAdsCampaignStructure
{
    use AsAction;

    /**
     * The ad fields for every ad format at once. Google leaves the fields of the other formats empty
     * on each row, so one query covers Search, Display, Video and Demand Gen ads alike.
     */
    private const string ADS_QUERY = "SELECT campaign.id, ad_group.id, ad_group.name, ad_group.status, ad_group.type, ad_group_ad.status, ad_group_ad.ad_strength, ad_group_ad.policy_summary.approval_status, ad_group_ad.ad.id, ad_group_ad.ad.type, ad_group_ad.ad.name, ad_group_ad.ad.final_urls, ad_group_ad.ad.responsive_search_ad.headlines, ad_group_ad.ad.responsive_search_ad.descriptions, ad_group_ad.ad.responsive_display_ad.headlines, ad_group_ad.ad.responsive_display_ad.long_headline, ad_group_ad.ad.responsive_display_ad.descriptions, ad_group_ad.ad.responsive_display_ad.business_name, ad_group_ad.ad.responsive_display_ad.marketing_images, ad_group_ad.ad.responsive_display_ad.square_marketing_images, ad_group_ad.ad.responsive_display_ad.logo_images, ad_group_ad.ad.responsive_display_ad.youtube_videos, ad_group_ad.ad.responsive_display_ad.call_to_action_text, ad_group_ad.ad.video_responsive_ad.headlines, ad_group_ad.ad.video_responsive_ad.long_headlines, ad_group_ad.ad.video_responsive_ad.descriptions, ad_group_ad.ad.video_responsive_ad.call_to_actions, ad_group_ad.ad.video_responsive_ad.videos, ad_group_ad.ad.video_responsive_ad.companion_banners, ad_group_ad.ad.demand_gen_multi_asset_ad.headlines, ad_group_ad.ad.demand_gen_multi_asset_ad.descriptions, ad_group_ad.ad.demand_gen_multi_asset_ad.business_name, ad_group_ad.ad.demand_gen_multi_asset_ad.call_to_action_text, ad_group_ad.ad.demand_gen_multi_asset_ad.marketing_images, ad_group_ad.ad.demand_gen_multi_asset_ad.square_marketing_images, ad_group_ad.ad.demand_gen_multi_asset_ad.logo_images, ad_group_ad.ad.demand_gen_carousel_ad.headline, ad_group_ad.ad.demand_gen_carousel_ad.description, ad_group_ad.ad.demand_gen_carousel_ad.business_name, ad_group_ad.ad.demand_gen_carousel_ad.call_to_action_text, ad_group_ad.ad.demand_gen_carousel_ad.logo_image, ad_group_ad.ad.demand_gen_carousel_ad.carousel_cards, ad_group_ad.ad.demand_gen_video_responsive_ad.headlines, ad_group_ad.ad.demand_gen_video_responsive_ad.long_headlines, ad_group_ad.ad.demand_gen_video_responsive_ad.descriptions, ad_group_ad.ad.demand_gen_video_responsive_ad.videos, ad_group_ad.ad.demand_gen_video_responsive_ad.logo_images, ad_group_ad.ad.demand_gen_video_responsive_ad.business_name, ad_group_ad.ad.demand_gen_video_responsive_ad.call_to_actions FROM ad_group_ad WHERE campaign.status != 'REMOVED' AND ad_group_ad.status != 'REMOVED'";

    /**
     * The Search-only ad query the page ran on before the other formats were read, kept as the
     * fallback: if Google ever renames one of the fields above, the campaigns keep their ads while
     * somebody fixes the name, rather than every ad on every page vanishing overnight.
     */
    private const string BASIC_ADS_QUERY = "SELECT campaign.id, ad_group.id, ad_group.name, ad_group.status, ad_group.type, ad_group_ad.status, ad_group_ad.ad_strength, ad_group_ad.policy_summary.approval_status, ad_group_ad.ad.id, ad_group_ad.ad.type, ad_group_ad.ad.final_urls, ad_group_ad.ad.responsive_search_ad.headlines, ad_group_ad.ad.responsive_search_ad.descriptions FROM ad_group_ad WHERE campaign.status != 'REMOVED' AND ad_group_ad.status != 'REMOVED'";

    private const string KEYWORDS_QUERY = "SELECT campaign.id, ad_group.id, ad_group_criterion.criterion_id, ad_group_criterion.keyword.text, ad_group_criterion.keyword.match_type, ad_group_criterion.status, ad_group_criterion.quality_info.quality_score FROM keyword_view WHERE campaign.status != 'REMOVED'";

    private const string AD_GROUP_NEGATIVES_QUERY = "SELECT campaign.id, ad_group.id, ad_group_criterion.criterion_id, ad_group_criterion.keyword.text, ad_group_criterion.keyword.match_type FROM ad_group_criterion WHERE ad_group_criterion.type = 'KEYWORD' AND ad_group_criterion.negative = TRUE AND campaign.status != 'REMOVED'";

    /**
     * Search campaigns are left out: every Search ad group carries the full set of age and gender rows
     * as bid adjustments, and listing them as targeting would say the campaign targets everyone.
     */
    private const string AD_GROUP_CRITERIA_QUERY = "SELECT campaign.id, ad_group.id, ad_group_criterion.criterion_id, ad_group_criterion.type, ad_group_criterion.negative, ad_group_criterion.status, ad_group_criterion.placement.url, ad_group_criterion.mobile_application.name, ad_group_criterion.youtube_channel.channel_id, ad_group_criterion.youtube_video.video_id, ad_group_criterion.topic.topic_constant, ad_group_criterion.user_list.user_list, ad_group_criterion.user_interest.user_interest_category, ad_group_criterion.custom_audience.custom_audience, ad_group_criterion.combined_audience.combined_audience, ad_group_criterion.audience.audience, ad_group_criterion.age_range.type, ad_group_criterion.gender.type, ad_group_criterion.parental_status.type, ad_group_criterion.income_range.type FROM ad_group_criterion WHERE ad_group_criterion.type IN ('PLACEMENT','MOBILE_APPLICATION','YOUTUBE_CHANNEL','YOUTUBE_VIDEO','TOPIC','USER_LIST','USER_INTEREST','CUSTOM_AUDIENCE','COMBINED_AUDIENCE','AUDIENCE','AGE_RANGE','GENDER','PARENTAL_STATUS','INCOME_RANGE') AND ad_group_criterion.status != 'REMOVED' AND campaign.status != 'REMOVED' AND campaign.advertising_channel_type != 'SEARCH'";

    private const string ASSET_GROUPS_QUERY = "SELECT campaign.id, asset_group.id, asset_group.name, asset_group.status, asset_group.ad_strength, asset_group.primary_status, asset_group.final_urls FROM asset_group WHERE asset_group.status != 'REMOVED'";

    private const string ASSET_GROUP_SIGNALS_QUERY = "SELECT asset_group.id, asset_group_signal.search_theme.text, asset_group_signal.audience.audience FROM asset_group_signal";

    /**
     * `primary_status` per asset, not the performance label Google's interface shows: the label is not
     * exposed by this version of the API, and the status at least says which assets are not running.
     */
    private const string ASSET_GROUP_ASSETS_QUERY = "SELECT asset_group.id, asset_group_asset.field_type, asset_group_asset.status, asset_group_asset.primary_status, asset.resource_name, asset.type, asset.name, asset.text_asset.text, asset.image_asset.full_size.url, asset.image_asset.full_size.width_pixels, asset.image_asset.full_size.height_pixels, asset.youtube_video_asset.youtube_video_id, asset.youtube_video_asset.youtube_video_title, asset.call_to_action_asset.call_to_action FROM asset_group_asset WHERE asset_group_asset.status != 'REMOVED'";

    private const string CAMPAIGN_EXCLUSIONS_QUERY = "SELECT campaign.id, campaign_criterion.criterion_id, campaign_criterion.type, campaign_criterion.placement.url, campaign_criterion.mobile_application.name, campaign_criterion.content_label.type, campaign_criterion.youtube_channel.channel_id, campaign_criterion.youtube_video.video_id, campaign_criterion.user_list.user_list, campaign_criterion.topic.topic_constant FROM campaign_criterion WHERE campaign_criterion.negative = true AND campaign_criterion.type IN ('PLACEMENT','MOBILE_APPLICATION','CONTENT_LABEL','YOUTUBE_CHANNEL','YOUTUBE_VIDEO','USER_LIST','TOPIC') AND campaign.status != 'REMOVED'";

    /**
     * Resource name prefix => [resource, field holding its human name], for the audiences and topics
     * that criteria only reference by resource name.
     */
    private const array NAME_LOOKUPS = [
        'userLists'        => ['user_list', 'user_list.name'],
        'customAudiences'  => ['custom_audience', 'custom_audience.name'],
        'combinedAudiences' => ['combined_audience', 'combined_audience.name'],
        'audiences'        => ['audience', 'audience.name'],
        'userInterests'    => ['user_interest', 'user_interest.name'],
        'topicConstants'   => ['topic_constant', 'topic_constant.path'],
    ];

    private const int LOOKUP_CHUNK = 100;

    private GoogleAdsClient $client;

    private Shop $shop;

    /** @var array<string, array> resource name => asset */
    private array $assets = [];

    /** @var array<string, string> resource name => label */
    private array $names = [];

    /**
     * @return array{ad_groups: array<string, array<int, array>>, asset_groups: array<string, array<int, array>>, exclusions: array<string, array<int, array>>, window: array{from: string, to: string}}
     */
    public function handle(GoogleAdsClient $client, Shop $shop, string $from, string $to): array
    {
        $this->client = $client;
        $this->shop   = $shop;
        $this->assets = [];
        $this->names  = [];

        $adRows = $this->searchOptional(self::ADS_QUERY, 'ads')
            ?? $this->searchOptional(self::BASIC_ADS_QUERY, 'ads (basic)')
            ?? [];

        $keywordRows       = $this->searchOptional(self::KEYWORDS_QUERY, 'keywords') ?? [];
        $keywordMetricRows = $this->searchOptional($this->keywordMetricsQuery($from, $to), 'keyword metrics') ?? [];
        $negativeRows      = $this->searchOptional(self::AD_GROUP_NEGATIVES_QUERY, 'ad group negatives') ?? [];
        $criteriaRows      = $this->searchOptional(self::AD_GROUP_CRITERIA_QUERY, 'ad group targeting') ?? [];
        $assetGroupRows    = $this->searchOptional(self::ASSET_GROUPS_QUERY, 'asset groups') ?? [];
        $assetGroupMetrics = $this->searchOptional($this->assetGroupMetricsQuery($from, $to), 'asset group metrics') ?? [];
        $signalRows        = $this->searchOptional(self::ASSET_GROUP_SIGNALS_QUERY, 'asset group signals') ?? [];
        $assetGroupAssets  = $this->searchOptional(self::ASSET_GROUP_ASSETS_QUERY, 'asset group assets') ?? [];
        $exclusionRows     = $this->searchOptional(self::CAMPAIGN_EXCLUSIONS_QUERY, 'campaign exclusions') ?? [];

        $this->resolveAssets($this->assetReferences($adRows));
        $this->resolveNames(array_merge(
            $this->nameReferences($criteriaRows, 'adGroupCriterion'),
            $this->nameReferences($exclusionRows, 'campaignCriterion'),
            array_filter(array_map(fn (array $row) => data_get($row, 'assetGroupSignal.audience.audience'), $signalRows)),
        ));

        return [
            'ad_groups'    => $this->adGroups($adRows, $keywordRows, $keywordMetricRows, $negativeRows, $criteriaRows),
            'asset_groups' => $this->assetGroups($assetGroupRows, $assetGroupMetrics, $signalRows, $assetGroupAssets),
            'exclusions'   => $this->exclusions($exclusionRows),
            'window'       => ['from' => $from, 'to' => $to],
        ];
    }

    /**
     * Keyword figures for the fetch window rather than for whatever period the page is set to: they
     * live on the keyword record, and a per-keyword daily table would be built for the one question
     * "which keywords earn" that this already answers for the last month.
     */
    private function keywordMetricsQuery(string $from, string $to): string
    {
        return "SELECT campaign.id, ad_group.id, ad_group_criterion.criterion_id, metrics.impressions, metrics.clicks, metrics.cost_micros, metrics.conversions, metrics.conversions_value FROM keyword_view WHERE segments.date BETWEEN '{$from}' AND '{$to}' AND campaign.status != 'REMOVED'";
    }

    private function assetGroupMetricsQuery(string $from, string $to): string
    {
        return "SELECT asset_group.id, metrics.impressions, metrics.clicks, metrics.cost_micros, metrics.conversions, metrics.conversions_value FROM asset_group WHERE segments.date BETWEEN '{$from}' AND '{$to}'";
    }

    /**
     * @param array<int, array> $adRows
     * @param array<int, array> $keywordRows
     * @param array<int, array> $keywordMetricRows
     * @param array<int, array> $negativeRows
     * @param array<int, array> $criteriaRows
     * @return array<string, array<int, array>> campaign id => ad groups
     */
    private function adGroups(array $adRows, array $keywordRows, array $keywordMetricRows, array $negativeRows, array $criteriaRows): array
    {
        $adGroups = [];

        foreach ($adRows as $row) {
            $campaignId = (string) data_get($row, 'campaign.id');
            $adGroupId  = (string) data_get($row, 'adGroup.id');

            $adGroups[$campaignId][$adGroupId] ??= $this->emptyAdGroup($adGroupId, data_get($row, 'adGroup.name'), data_get($row, 'adGroup.status'), data_get($row, 'adGroup.type'));

            $adGroups[$campaignId][$adGroupId]['ads'][] = array_merge(
                [
                    'id'              => data_get($row, 'adGroupAd.ad.id'),
                    'type'            => data_get($row, 'adGroupAd.ad.type'),
                    'name'            => data_get($row, 'adGroupAd.ad.name'),
                    'status'          => data_get($row, 'adGroupAd.status'),
                    'strength'        => data_get($row, 'adGroupAd.adStrength'),
                    'approval_status' => data_get($row, 'adGroupAd.policySummary.approvalStatus'),
                    'final_urls'      => data_get($row, 'adGroupAd.ad.finalUrls', []),
                ],
                $this->creative(data_get($row, 'adGroupAd.ad', []))
            );
        }

        $metricsByKeyword = [];

        foreach ($keywordMetricRows as $row) {
            $cost   = ((float) data_get($row, 'metrics.costMicros', 0)) / 1_000_000;
            $clicks = (int) data_get($row, 'metrics.clicks', 0);

            $metricsByKeyword[(string) data_get($row, 'adGroup.id').'~'.data_get($row, 'adGroupCriterion.criterionId')] = [
                'impressions'       => (int) data_get($row, 'metrics.impressions', 0),
                'clicks'            => $clicks,
                'cost'              => round($cost, 2),
                'avg_cpc'           => $clicks > 0 ? round($cost / $clicks, 2) : null,
                'conversions'       => (float) data_get($row, 'metrics.conversions', 0),
                'conversions_value' => round((float) data_get($row, 'metrics.conversionsValue', 0), 2),
            ];
        }

        foreach ($keywordRows as $row) {
            $campaignId  = (string) data_get($row, 'campaign.id');
            $adGroupId   = (string) data_get($row, 'adGroup.id');
            $criterionId = (string) data_get($row, 'adGroupCriterion.criterionId');

            $adGroups[$campaignId][$adGroupId] ??= $this->emptyAdGroup($adGroupId);

            $adGroups[$campaignId][$adGroupId]['keywords'][] = [
                'id'            => $criterionId,
                'text'          => data_get($row, 'adGroupCriterion.keyword.text'),
                'match_type'    => data_get($row, 'adGroupCriterion.keyword.matchType'),
                'status'        => data_get($row, 'adGroupCriterion.status'),
                'quality_score' => data_get($row, 'adGroupCriterion.qualityInfo.qualityScore'),
                'metrics'       => $metricsByKeyword[$adGroupId.'~'.$criterionId] ?? null,
            ];
        }

        foreach ($negativeRows as $row) {
            $campaignId = (string) data_get($row, 'campaign.id');
            $adGroupId  = (string) data_get($row, 'adGroup.id');

            $adGroups[$campaignId][$adGroupId] ??= $this->emptyAdGroup($adGroupId);

            $adGroups[$campaignId][$adGroupId]['negative_keywords'][] = [
                'id'         => data_get($row, 'adGroupCriterion.criterionId'),
                'text'       => data_get($row, 'adGroupCriterion.keyword.text'),
                'match_type' => data_get($row, 'adGroupCriterion.keyword.matchType'),
            ];
        }

        foreach ($criteriaRows as $row) {
            $campaignId = (string) data_get($row, 'campaign.id');
            $adGroupId  = (string) data_get($row, 'adGroup.id');

            $adGroups[$campaignId][$adGroupId] ??= $this->emptyAdGroup($adGroupId);

            $adGroups[$campaignId][$adGroupId]['targeting'][] = $this->criterion($row['adGroupCriterion'] ?? []);
        }

        return array_map('array_values', $adGroups);
    }

    private function emptyAdGroup(string $id, ?string $name = null, ?string $status = null, ?string $type = null): array
    {
        return [
            'id'                => $id,
            'name'              => $name,
            'status'            => $status,
            'type'              => $type,
            'ads'               => [],
            'keywords'          => [],
            'negative_keywords' => [],
            'targeting'         => [],
        ];
    }

    /**
     * The parts of an ad that are worth looking at, in one shape for every format. A format that has
     * no videos gets an empty list rather than a missing key, so the page never has to ask which
     * format it is holding.
     *
     * @param array<string, mixed> $ad
     * @return array{headlines: array<int, string>, long_headlines: array<int, string>, descriptions: array<int, string>, business_name: string|null, call_to_action: string|null, images: array<int, array>, logos: array<int, array>, videos: array<int, array>, carousel_cards: array<int, array>}
     */
    private function creative(array $ad): array
    {
        $spec = $ad['responsiveSearchAd']
            ?? $ad['responsiveDisplayAd']
            ?? $ad['videoResponsiveAd']
            ?? $ad['demandGenMultiAssetAd']
            ?? $ad['demandGenCarouselAd']
            ?? $ad['demandGenVideoResponsiveAd']
            ?? [];

        $single = fn (?array $one) => $one ? [$one] : [];

        return [
            'headlines'      => $this->texts($spec['headlines'] ?? $single($spec['headline'] ?? null)),
            'long_headlines' => $this->texts($spec['longHeadlines'] ?? $single($spec['longHeadline'] ?? null)),
            'descriptions'   => $this->texts($spec['descriptions'] ?? $single($spec['description'] ?? null)),
            'business_name'  => $spec['businessName'] ?? null,
            'call_to_action' => $spec['callToActionText'] ?? $this->callToAction($spec['callToActions'] ?? []),
            'images'         => $this->assetList(array_merge($spec['marketingImages'] ?? [], $spec['squareMarketingImages'] ?? [], $spec['companionBanners'] ?? [])),
            'logos'          => $this->assetList(array_merge($spec['logoImages'] ?? [], $single($spec['logoImage'] ?? null))),
            'videos'         => $this->assetList(array_merge($spec['videos'] ?? [], $spec['youtubeVideos'] ?? [])),
            'carousel_cards' => array_map(fn (array $card) => [
                'headline'    => $card['headline'] ?? null,
                'description' => $card['description'] ?? null,
                'image'       => $this->asset($card['marketingImageAsset'] ?? $card['squareMarketingImageAsset'] ?? $card['portraitMarketingImageAsset'] ?? null),
            ], $spec['carouselCards'] ?? []),
        ];
    }

    /**
     * @param array<int, array> $textAssets
     * @return array<int, string>
     */
    private function texts(array $textAssets): array
    {
        return array_values(array_filter(array_map(fn ($asset) => is_array($asset) ? ($asset['text'] ?? null) : $asset, $textAssets)));
    }

    /**
     * @param array<int, array> $callToActions
     */
    private function callToAction(array $callToActions): ?string
    {
        foreach ($callToActions as $reference) {
            $asset = $this->asset($reference['asset'] ?? null);

            if ($asset && ($asset['call_to_action'] ?? null)) {
                return $asset['call_to_action'];
            }
        }

        return null;
    }

    /**
     * @param array<int, array> $references
     * @return array<int, array>
     */
    private function assetList(array $references): array
    {
        return array_values(array_filter(array_map(fn (array $reference) => $this->asset($reference['asset'] ?? null), $references)));
    }

    private function asset(?string $resourceName): ?array
    {
        if (!$resourceName) {
            return null;
        }

        return $this->compact($this->assets[$resourceName] ?? ['resource_name' => $resourceName]);
    }

    /**
     * An image has no video id and a headline has no pixel size, and a campaign with sixty asset
     * groups stores thousands of these, so the empty fields are dropped before the JSON is written.
     *
     * @param array<string, mixed> $asset
     * @return array<string, mixed>
     */
    private function compact(array $asset): array
    {
        unset($asset['resource_name']);

        return array_filter($asset, fn ($value) => $value !== null && $value !== '');
    }

    /**
     * Every asset resource name an ad row points at, so they can be looked up in one go.
     *
     * @param array<int, array> $adRows
     * @return array<int, string>
     */
    private function assetReferences(array $adRows): array
    {
        $references = [];

        array_walk_recursive($adRows, function ($value, $key) use (&$references) {
            if ($key === 'asset' && is_string($value) && str_contains($value, '/assets/')) {
                $references[$value] = true;
            }
        });

        return array_keys($references);
    }

    /**
     * @param array<int, string> $references
     */
    private function resolveAssets(array $references): void
    {
        foreach (array_chunk($references, self::LOOKUP_CHUNK) as $chunk) {
            $list  = implode(',', array_map(fn (string $name) => "'{$name}'", $chunk));
            $query = "SELECT asset.resource_name, asset.name, asset.type, asset.text_asset.text, asset.image_asset.full_size.url, asset.image_asset.full_size.width_pixels, asset.image_asset.full_size.height_pixels, asset.youtube_video_asset.youtube_video_id, asset.youtube_video_asset.youtube_video_title, asset.call_to_action_asset.call_to_action FROM asset WHERE asset.resource_name IN ({$list})";

            foreach ($this->searchOptional($query, 'assets') ?? [] as $row) {
                $asset = $this->shapeAsset($row['asset'] ?? []);

                $this->assets[$asset['resource_name']] = $asset;
            }
        }
    }

    /**
     * @param array<string, mixed> $asset
     * @return array{resource_name: string, name: string|null, type: string|null, text: string|null, url: string|null, width: int|null, height: int|null, video_id: string|null, video_title: string|null, call_to_action: string|null}
     */
    private function shapeAsset(array $asset): array
    {
        $videoId = data_get($asset, 'youtubeVideoAsset.youtubeVideoId');

        return [
            'resource_name'  => (string) ($asset['resourceName'] ?? ''),
            'name'           => $asset['name'] ?? null,
            'type'           => $asset['type'] ?? null,
            'text'           => data_get($asset, 'textAsset.text'),
            'url'            => data_get($asset, 'imageAsset.fullSize.url'),
            'width'          => data_get($asset, 'imageAsset.fullSize.widthPixels') !== null ? (int) data_get($asset, 'imageAsset.fullSize.widthPixels') : null,
            'height'         => data_get($asset, 'imageAsset.fullSize.heightPixels') !== null ? (int) data_get($asset, 'imageAsset.fullSize.heightPixels') : null,
            'video_id'       => $videoId,
            'video_title'    => data_get($asset, 'youtubeVideoAsset.youtubeVideoTitle'),
            'call_to_action' => data_get($asset, 'callToActionAsset.callToAction'),
        ];
    }

    /**
     * @param array<int, array> $rows
     * @return array<int, string>
     */
    private function nameReferences(array $rows, string $criterionKey): array
    {
        $references = [];

        foreach ($rows as $row) {
            foreach (['userList.userList', 'customAudience.customAudience', 'combinedAudience.combinedAudience', 'audience.audience', 'userInterest.userInterestCategory', 'topic.topicConstant'] as $path) {
                $reference = data_get($row, "{$criterionKey}.{$path}");

                if (is_string($reference) && $reference !== '') {
                    $references[] = $reference;
                }
            }
        }

        return array_values(array_unique($references));
    }

    /**
     * Audiences, lists, interests and topics are referenced by resource name and named by a lookup on
     * their own resource; the prefix of the name says which one.
     *
     * @param array<int, string> $references
     */
    private function resolveNames(array $references): void
    {
        $byLookup = [];

        foreach (array_unique($references) as $reference) {
            foreach (self::NAME_LOOKUPS as $prefix => $lookup) {
                if (str_contains($reference, "/{$prefix}/") || str_starts_with($reference, "{$prefix}/")) {
                    $byLookup[$prefix][] = $reference;

                    break;
                }
            }
        }

        foreach ($byLookup as $prefix => $names) {
            [$resource, $field] = self::NAME_LOOKUPS[$prefix];

            foreach (array_chunk($names, self::LOOKUP_CHUNK) as $chunk) {
                $list  = implode(',', array_map(fn (string $name) => "'{$name}'", $chunk));
                $query = "SELECT {$resource}.resource_name, {$field} FROM {$resource} WHERE {$resource}.resource_name IN ({$list})";

                foreach ($this->searchOptional($query, $resource.' names') ?? [] as $row) {
                    $entity = reset($row);
                    $label  = $entity['name'] ?? $entity['path'] ?? null;

                    if (is_array($label)) {
                        $label = implode(' / ', array_filter($label));
                    }

                    if (isset($entity['resourceName']) && $label) {
                        $this->names[$entity['resourceName']] = $label;
                    }
                }
            }
        }
    }

    private function name(?string $reference): ?string
    {
        if (!$reference) {
            return null;
        }

        return $this->names[$reference] ?? basename($reference);
    }

    /**
     * One targeting or exclusion criterion as a label a person can read, whatever its type.
     *
     * @param array<string, mixed> $criterion
     * @return array{id: string|null, type: string|null, negative: bool, status: string|null, label: string|null}
     */
    private function criterion(array $criterion): array
    {
        $type = $criterion['type'] ?? null;

        $label = match ($type) {
            'KEYWORD'            => data_get($criterion, 'keyword.text'),
            'PLACEMENT'          => data_get($criterion, 'placement.url'),
            'MOBILE_APPLICATION' => data_get($criterion, 'mobileApplication.name'),
            'YOUTUBE_CHANNEL'    => data_get($criterion, 'youtubeChannel.channelId'),
            'YOUTUBE_VIDEO'      => data_get($criterion, 'youtubeVideo.videoId'),
            'CONTENT_LABEL'      => data_get($criterion, 'contentLabel.type'),
            'TOPIC'              => $this->name(data_get($criterion, 'topic.topicConstant')),
            'USER_LIST'          => $this->name(data_get($criterion, 'userList.userList')),
            'USER_INTEREST'      => $this->name(data_get($criterion, 'userInterest.userInterestCategory')),
            'CUSTOM_AUDIENCE'    => $this->name(data_get($criterion, 'customAudience.customAudience')),
            'COMBINED_AUDIENCE'  => $this->name(data_get($criterion, 'combinedAudience.combinedAudience')),
            'AUDIENCE'           => $this->name(data_get($criterion, 'audience.audience')),
            'AGE_RANGE'          => data_get($criterion, 'ageRange.type'),
            'GENDER'             => data_get($criterion, 'gender.type'),
            'PARENTAL_STATUS'    => data_get($criterion, 'parentalStatus.type'),
            'INCOME_RANGE'       => data_get($criterion, 'incomeRange.type'),
            default              => null,
        };

        return [
            'id'       => isset($criterion['criterionId']) ? (string) $criterion['criterionId'] : null,
            'type'     => $type,
            'negative' => (bool) ($criterion['negative'] ?? false),
            'status'   => $criterion['status'] ?? null,
            'label'    => $label,
        ];
    }

    /**
     * @param array<int, array> $assetGroupRows
     * @param array<int, array> $metricRows
     * @param array<int, array> $signalRows
     * @param array<int, array> $assetRows
     * @return array<string, array<int, array>> campaign id => asset groups
     */
    private function assetGroups(array $assetGroupRows, array $metricRows, array $signalRows, array $assetRows): array
    {
        $metrics = [];

        foreach ($metricRows as $row) {
            $cost  = ((float) data_get($row, 'metrics.costMicros', 0)) / 1_000_000;
            $value = (float) data_get($row, 'metrics.conversionsValue', 0);

            $metrics[(string) data_get($row, 'assetGroup.id')] = [
                'impressions'       => (int) data_get($row, 'metrics.impressions', 0),
                'clicks'            => (int) data_get($row, 'metrics.clicks', 0),
                'cost'              => round($cost, 2),
                'conversions'       => (float) data_get($row, 'metrics.conversions', 0),
                'conversions_value' => round($value, 2),
                'roas'              => $cost > 0 ? round($value / $cost, 2) : null,
            ];
        }

        $signals = [];

        foreach ($signalRows as $row) {
            $assetGroupId = (string) data_get($row, 'assetGroup.id');
            $theme        = data_get($row, 'assetGroupSignal.searchTheme.text');
            $audience     = data_get($row, 'assetGroupSignal.audience.audience');

            if ($theme) {
                $signals[$assetGroupId]['search_themes'][] = $theme;
            } elseif ($audience) {
                $signals[$assetGroupId]['audience_signals'][] = $this->name($audience);
            }
        }

        $assets = [];

        foreach ($assetRows as $row) {
            $assetGroupId = (string) data_get($row, 'assetGroup.id');
            $fieldType    = (string) data_get($row, 'assetGroupAsset.fieldType');
            $asset        = $this->compact($this->shapeAsset($row['asset'] ?? []));
            $entry        = array_merge($asset, [
                'field_type'     => $fieldType,
                'primary_status' => data_get($row, 'assetGroupAsset.primaryStatus'),
            ]);

            $bucket = match ($fieldType) {
                'HEADLINE', 'LONG_HEADLINE', 'DESCRIPTION', 'BUSINESS_NAME', 'CALL_TO_ACTION_SELECTION' => 'texts',
                'MARKETING_IMAGE', 'SQUARE_MARKETING_IMAGE', 'PORTRAIT_MARKETING_IMAGE'                => 'images',
                'LOGO', 'LANDSCAPE_LOGO'                                                                => 'logos',
                'YOUTUBE_VIDEO'                                                                         => 'videos',
                default                                                                                 => 'other',
            };

            $assets[$assetGroupId][$bucket][] = $entry;
        }

        $assetGroups = [];

        foreach ($assetGroupRows as $row) {
            $id = (string) data_get($row, 'assetGroup.id');

            $assetGroups[(string) data_get($row, 'campaign.id')][] = [
                'id'               => $id,
                'name'             => data_get($row, 'assetGroup.name'),
                'status'           => data_get($row, 'assetGroup.status'),
                'ad_strength'      => data_get($row, 'assetGroup.adStrength'),
                'primary_status'   => data_get($row, 'assetGroup.primaryStatus'),
                'final_urls'       => data_get($row, 'assetGroup.finalUrls', []),
                'search_themes'    => $signals[$id]['search_themes'] ?? [],
                'audience_signals' => array_values(array_unique($signals[$id]['audience_signals'] ?? [])),
                'assets'           => [
                    'texts'  => $assets[$id]['texts'] ?? [],
                    'images' => $assets[$id]['images'] ?? [],
                    'logos'  => $assets[$id]['logos'] ?? [],
                    'videos' => $assets[$id]['videos'] ?? [],
                    'other'  => $assets[$id]['other'] ?? [],
                ],
                'metrics' => $metrics[$id] ?? null,
            ];
        }

        return $assetGroups;
    }

    /**
     * @param array<int, array> $rows
     * @return array<string, array<int, array>> campaign id => exclusions
     */
    private function exclusions(array $rows): array
    {
        $exclusions = [];

        foreach ($rows as $row) {
            $exclusions[(string) data_get($row, 'campaign.id')][] = array_merge(
                $this->criterion($row['campaignCriterion'] ?? []),
                ['negative' => true]
            );
        }

        return $exclusions;
    }

    /**
     * @return array<int, array>|null
     */
    private function searchOptional(string $query, string $label): ?array
    {
        try {
            return $this->client->search($query);
        } catch (GoogleAdsException $e) {
            Log::warning('Google Ads optional fetch failed', ['shop' => $this->shop->slug, 'label' => $label, 'error' => $e->describe()]);

            return null;
        }
    }
}
