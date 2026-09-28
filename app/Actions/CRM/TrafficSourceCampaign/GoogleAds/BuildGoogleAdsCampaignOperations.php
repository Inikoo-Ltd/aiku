<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\TrafficSourceCampaign\GoogleAds;

use App\Services\GoogleAds\GoogleAdsClient;
use App\Services\GoogleAds\GoogleAdsException;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Turns the fields of a campaign into the list of operations that creates it at Google.
 *
 * Each campaign type is a different shape rather than a variation on one: a Search campaign is an ad
 * group of keywords and a text ad, a Display or Demand Gen campaign is an ad group of images, and a
 * Performance Max campaign has no ad group at all, only an asset group Google assembles its own ads
 * from. What they share is the budget, the campaign row and the geo targeting, so that much is built
 * once and the rest is built per type.
 *
 * Every operation goes in one list because Google applies them atomically: a campaign whose ad was
 * rejected must not be left running with no ad, and the draft must not be marked live when half of it
 * exists. Assets are the exception, and are uploaded before this runs, because Google's brand
 * guidelines check cannot see a logo created in the same request.
 *
 * Campaigns are always created paused. There is no undo for money already spent.
 */
class BuildGoogleAdsCampaignOperations
{
    use AsAction;

    /**
     * Required of every new campaign since the EU rules on political advertising came in, and Aiku
     * sells goods, so the answer is always no.
     */
    private const string EU_POLITICAL = 'DOES_NOT_CONTAIN_EU_POLITICAL_ADVERTISING';

    /**
     * @param array<string, mixed> $data every field the form collected
     * @param array<string, array<int, string>> $assets role => asset resource names, uploaded beforehand
     * @return array<int, array>
     * @throws GoogleAdsException
     */
    public function handle(GoogleAdsClient $client, string $name, string $channelType, array $data, array $assets = []): array
    {
        $budget = $client->temporaryResource('campaignBudgets', 1);
        $campaign = $client->temporaryResource('campaigns', 2);

        $operations = [
            ['campaignBudgetOperation' => ['create' => [
                'resourceName' => $budget,

                /* Budgets carry their own names and Google rejects a duplicate, so it is tied to the
                   campaign name it was made for rather than left to collide with the next one. */
                'name'             => mb_substr($name.' budget', 0, 255),
                'amountMicros'     => (string) (int) round((float) Arr::get($data, 'budget_amount') * 1_000_000),
                'deliveryMethod'   => 'STANDARD',
                'explicitlyShared' => false,
            ]]],
            ['campaignOperation' => ['create' => $this->campaign($name, $channelType, $budget, $campaign, $data)]],
        ];

        foreach ($this->geoTargetConstants($client, $data) as $geoTargetConstant) {
            $operations[] = ['campaignCriterionOperation' => ['create' => [
                'campaign' => $campaign,
                'location' => ['geoTargetConstant' => $geoTargetConstant],
            ]]];
        }

        return array_merge($operations, match ($channelType) {
            'SEARCH'          => $this->searchOperations($client, $campaign, $data),
            'DISPLAY'         => $this->displayOperations($client, $campaign, $data, $assets),
            'DEMAND_GEN'      => $this->demandGenOperations($client, $campaign, $data, $assets),
            'PERFORMANCE_MAX' => $this->performanceMaxOperations($client, $campaign, $data, $assets),
            default           => throw ValidationException::withMessages([
                'channel_type' => __('Aiku cannot create a :type campaign.', ['type' => $channelType]),
            ]),
        });
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function campaign(string $name, string $channelType, string $budget, string $campaign, array $data): array
    {
        $create = [
            'resourceName'                   => $campaign,
            'name'                           => $name,
            'status'                         => 'PAUSED',
            'advertisingChannelType'         => $channelType,
            'campaignBudget'                 => $budget,
            'containsEuPoliticalAdvertising' => self::EU_POLITICAL,
        ];

        /* Each type accepts a different set of bidding strategies and refuses the rest outright. */
        $create += match ($channelType) {
            'SEARCH'          => ['targetSpend' => $this->targetSpend($data)],
            'DISPLAY'         => ['manualCpc' => new \stdClass()],
            'DEMAND_GEN'      => ['targetCpa' => ['targetCpaMicros' => (string) (int) round((float) Arr::get($data, 'target_cpa', 5) * 1_000_000)]],
            'PERFORMANCE_MAX' => ['maximizeConversionValue' => new \stdClass()],
            default           => [],
        };

        if ($channelType === 'SEARCH') {
            /* The Display network is on by default for a new Search campaign and spends the same
               budget on placements nobody chose, which is the commonest way a new campaign wastes
               money quietly. */
            $create['networkSettings'] = [
                'targetGoogleSearch'         => true,
                'targetSearchNetwork'        => (bool) Arr::get($data, 'target_search_partners', false),
                'targetContentNetwork'       => false,
                'targetPartnerSearchNetwork' => false,
            ];
        }

        return $create;
    }

    /**
     * An empty bidding strategy has to reach Google as `{}`, and an empty PHP array serialises as
     * `[]`, which it rejects. Hence the object when there is no ceiling to set.
     *
     * @param array<string, mixed> $data
     */
    private function targetSpend(array $data): array|\stdClass
    {
        $maxCpc = Arr::get($data, 'max_cpc');

        return $maxCpc ? ['cpcBidCeilingMicros' => (string) (int) round((float) $maxCpc * 1_000_000)] : new \stdClass();
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, array>
     */
    private function searchOperations(GoogleAdsClient $client, string $campaign, array $data): array
    {
        $adGroup    = $client->temporaryResource('adGroups', 3);
        $operations = [
            ['adGroupOperation' => ['create' => [
                'resourceName' => $adGroup,
                'name'         => trim((string) Arr::get($data, 'ad_group_name')),
                'campaign'     => $campaign,
                'status'       => 'ENABLED',
                'type'         => 'SEARCH_STANDARD',
            ]]],
        ];

        $matchType = (string) Arr::get($data, 'match_type', 'PHRASE');

        foreach ($this->lines($data, 'keywords') as $keyword) {
            $operations[] = ['adGroupCriterionOperation' => ['create' => [
                'adGroup' => $adGroup,
                'status'  => 'ENABLED',
                'keyword' => ['text' => $keyword, 'matchType' => $matchType],
            ]]];
        }

        $operations[] = ['adGroupAdOperation' => ['create' => [
            'adGroup' => $adGroup,
            'status'  => 'ENABLED',
            'ad'      => [
                'finalUrls'          => [Arr::get($data, 'final_url')],
                'responsiveSearchAd' => [
                    'headlines'    => $this->textAssets($data, 'headlines'),
                    'descriptions' => $this->textAssets($data, 'descriptions'),
                ],
            ],
        ]]];

        return $operations;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $assets
     * @return array<int, array>
     */
    private function displayOperations(GoogleAdsClient $client, string $campaign, array $data, array $assets): array
    {
        $adGroup = $client->temporaryResource('adGroups', 3);

        return [
            ['adGroupOperation' => ['create' => [
                'resourceName' => $adGroup,
                'name'         => trim((string) Arr::get($data, 'ad_group_name')),
                'campaign'     => $campaign,
                'status'       => 'ENABLED',
                'type'         => 'DISPLAY_STANDARD',
                'cpcBidMicros' => (string) (int) round((float) Arr::get($data, 'max_cpc', 0.5) * 1_000_000),
            ]]],
            ['adGroupAdOperation' => ['create' => [
                'adGroup' => $adGroup,
                'status'  => 'ENABLED',
                'ad'      => [
                    'finalUrls'           => [Arr::get($data, 'final_url')],
                    'responsiveDisplayAd' => array_filter([
                        'headlines'             => $this->textAssets($data, 'headlines'),
                        'longHeadline'          => ['text' => (string) Arr::get($data, 'long_headline')],
                        'descriptions'          => $this->textAssets($data, 'descriptions'),
                        'businessName'          => (string) Arr::get($data, 'business_name'),
                        'marketingImages'       => $this->imageAssets($assets, 'marketing_images'),
                        'squareMarketingImages' => $this->imageAssets($assets, 'square_marketing_images'),
                        'logoImages'            => $this->imageAssets($assets, 'logos'),
                    ]),
                ],
            ]]],
        ];
    }

    /**
     * Demand Gen ad groups must carry no type at all. `DEMAND_GEN_AD_GROUP` reads like the right
     * value and is rejected as an unknown enum, which is worth a line here because the next person to
     * look will reach for it too.
     *
     * @param array<string, mixed> $data
     * @param array<string, string> $assets
     * @return array<int, array>
     */
    private function demandGenOperations(GoogleAdsClient $client, string $campaign, array $data, array $assets): array
    {
        $adGroup = $client->temporaryResource('adGroups', 3);

        return [
            ['adGroupOperation' => ['create' => [
                'resourceName' => $adGroup,
                'name'         => trim((string) Arr::get($data, 'ad_group_name')),
                'campaign'     => $campaign,
                'status'       => 'ENABLED',
            ]]],
            ['adGroupAdOperation' => ['create' => [
                'adGroup' => $adGroup,
                'status'  => 'ENABLED',
                'ad'      => [
                    'finalUrls'             => [Arr::get($data, 'final_url')],
                    'demandGenMultiAssetAd' => array_filter([
                        'headlines'             => $this->textAssets($data, 'headlines'),
                        'descriptions'          => $this->textAssets($data, 'descriptions'),
                        'businessName'          => (string) Arr::get($data, 'business_name'),
                        'marketingImages'       => $this->imageAssets($assets, 'marketing_images'),
                        'squareMarketingImages' => $this->imageAssets($assets, 'square_marketing_images'),
                        'logoImages'            => $this->imageAssets($assets, 'logos'),
                    ]),
                ],
            ]]],
        ];
    }

    /**
     * Performance Max has no ad group. Its creative lives in an asset group, and where the account has
     * brand guidelines switched on Google also demands a business name and a square logo attached to
     * the campaign itself, against assets that already exist.
     *
     * @param array<string, mixed> $data
     * @param array<string, string> $assets
     * @return array<int, array>
     */
    private function performanceMaxOperations(GoogleAdsClient $client, string $campaign, array $data, array $assets): array
    {
        $assetGroup = $client->temporaryResource('assetGroups', 3);
        $businessName = $client->temporaryResource('assets', 4);
        $operations = [];

        $operations[] = ['assetOperation' => ['create' => [
            'resourceName' => $businessName,
            'name'         => mb_substr(Arr::get($data, 'business_name', '').' '.uniqid(), 0, 120),
            'textAsset'    => ['text' => (string) Arr::get($data, 'business_name')],
        ]]];

        $operations[] = ['campaignAssetOperation' => ['create' => [
            'campaign'  => $campaign,
            'asset'     => $businessName,
            'fieldType' => 'BUSINESS_NAME',
        ]]];

        foreach ($this->imageAssets($assets, 'logos') as $logo) {
            $operations[] = ['campaignAssetOperation' => ['create' => [
                'campaign'  => $campaign,
                'asset'     => $logo['asset'],
                'fieldType' => 'LOGO',
            ]]];
        }

        $operations[] = ['assetGroupOperation' => ['create' => [
            'resourceName' => $assetGroup,
            'name'         => trim((string) Arr::get($data, 'asset_group_name', $data['ad_group_name'] ?? 'Asset group')),
            'campaign'     => $campaign,
            'finalUrls'    => [Arr::get($data, 'final_url')],
            'status'       => 'PAUSED',
        ]]];

        foreach ($this->assetGroupAssets($client, $data, $assets, $assetGroup) as $operation) {
            $operations[] = $operation;
        }

        foreach ($this->lines($data, 'search_themes') as $theme) {
            $operations[] = ['assetGroupSignalOperation' => ['create' => [
                'assetGroup'  => $assetGroup,
                'searchTheme' => ['text' => $theme],
            ]]];
        }

        return $operations;
    }

    /**
     * An asset group holds its text as assets rather than inline fields, so each headline and
     * description is created and then linked under the field type it fills.
     *
     * @param array<string, mixed> $data
     * @param array<string, string> $assets
     * @return array<int, array>
     */
    private function assetGroupAssets(GoogleAdsClient $client, array $data, array $assets, string $assetGroup): array
    {
        $operations = [];
        $index      = 10;

        $texts = [
            'HEADLINE'      => $this->lines($data, 'headlines'),
            'LONG_HEADLINE' => array_filter([Arr::get($data, 'long_headline')]),
            'DESCRIPTION'   => $this->lines($data, 'descriptions'),
        ];

        foreach ($texts as $fieldType => $values) {
            foreach ($values as $value) {
                $resource = $client->temporaryResource('assets', $index++);

                $operations[] = ['assetOperation' => ['create' => [
                    'resourceName' => $resource,
                    'textAsset'    => ['text' => $value],
                ]]];

                $operations[] = ['assetGroupAssetOperation' => ['create' => [
                    'assetGroup' => $assetGroup,
                    'asset'      => $resource,
                    'fieldType'  => $fieldType,
                ]]];
            }
        }

        $images = [
            'MARKETING_IMAGE'        => 'marketing_images',
            'SQUARE_MARKETING_IMAGE' => 'square_marketing_images',
            'LOGO'                   => 'logos',
        ];

        foreach ($images as $fieldType => $role) {
            foreach ($this->imageAssets($assets, $role) as $image) {
                $operations[] = ['assetGroupAssetOperation' => ['create' => [
                    'assetGroup' => $assetGroup,
                    'asset'      => $image['asset'],
                    'fieldType'  => $fieldType,
                ]]];
            }
        }

        return $operations;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, array{text: string}>
     */
    private function textAssets(array $data, string $key): array
    {
        return array_map(fn (string $text) => ['text' => $text], $this->lines($data, $key));
    }

    /**
     * @param array<string, string> $assets
     * @return array<int, array{asset: string}>
     */
    private function imageAssets(array $assets, string $role): array
    {
        return array_values(array_map(
            fn (string $resourceName) => ['asset' => $resourceName],
            Arr::get($assets, $role, [])
        ));
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, string>
     */
    private function lines(array $data, string $key): array
    {
        return array_values(array_filter(array_map('trim', (array) Arr::get($data, $key, []))));
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, string>
     * @throws GoogleAdsException
     */
    private function geoTargetConstants(GoogleAdsClient $client, array $data): array
    {
        $codes = array_filter(array_map('strtoupper', (array) Arr::get($data, 'country_codes', [])));

        if ($codes === []) {
            return [];
        }

        $list = "'".implode("','", array_map(fn ($code) => preg_replace('/[^A-Z]/', '', $code), $codes))."'";

        $rows = $client->search(
            "SELECT geo_target_constant.resource_name FROM geo_target_constant
             WHERE geo_target_constant.country_code IN ({$list})
               AND geo_target_constant.target_type = 'Country'
               AND geo_target_constant.status = 'ENABLED'"
        );

        $resources = collect($rows)->pluck('geoTargetConstant.resourceName')->filter()->unique()->values()->all();

        if ($resources === []) {
            throw ValidationException::withMessages([
                'country_codes' => __('Google Ads does not recognise those countries as targetable.'),
            ]);
        }

        return $resources;
    }
}
