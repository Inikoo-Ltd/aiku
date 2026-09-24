<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\TrafficSourceCampaign\GoogleAds;

use App\Actions\OrgAction;
use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\TrafficSourceCampaign;
use App\Models\SysAdmin\Organisation;
use App\Services\GoogleAds\GoogleAdsClient;
use App\Services\GoogleAds\GoogleAdsException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use OwenIt\Auditing\Events\AuditCustom;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Adds a second responsive search ad to an ad group that has only one.
 *
 * Google rotates the ads in an ad group and learns which wins, which it cannot do where there is
 * nothing to rotate. On one live account 105 of 130 serving ad groups carry a single ad, so the
 * comparison Google is built around is simply not running.
 *
 * This is deliberately duplication and not composition. The form opens on a copy of an ad Google has
 * already rated and approved, and the marketer changes the lines they want to test. Writing an ad from
 * nothing belongs in Google Ads, where the strength meter scores it as you type, headlines can be
 * pinned to positions and the result can be previewed. Aiku would offer text boxes and silence.
 *
 * Created enabled rather than paused, unlike a new campaign. A paused variant never rotates, so the
 * test it exists for never happens, and the person clicking the button has just read every line.
 */
class StoreGoogleAdsAd extends OrgAction
{
    use WithGoogleAdsWriteErrors;

    private const int HEADLINE_LENGTH = 30;

    private const int DESCRIPTION_LENGTH = 90;

    /**
     * @throws GoogleAdsException
     */
    public function handle(TrafficSourceCampaign $campaign, array $modelData): TrafficSourceCampaign
    {
        $shop   = $campaign->trafficSource->shop;
        $client = GoogleAdsClient::forShop($shop);

        if (!$client) {
            throw ValidationException::withMessages([
                'headlines' => GoogleAdsClient::unreachableReason($shop) ?? __('Google Ads is not configured for this shop.'),
            ]);
        }

        $adGroupId  = (string) Arr::get($modelData, 'ad_group_id');
        $data       = $campaign->data ?? [];
        $adGroups   = $data['ad_groups'] ?? [];
        $groupIndex = $this->locateAdGroup($adGroups, $adGroupId);

        if ($groupIndex === null) {
            throw ValidationException::withMessages([
                'ad_group_id' => __('That ad group is not on this campaign. It may have been removed in Google Ads since the last fetch.'),
            ]);
        }

        $headlines    = $this->lines($modelData, 'headlines');
        $descriptions = $this->lines($modelData, 'descriptions');
        $finalUrl     = trim((string) Arr::get($modelData, 'final_url'));

        try {
            $results = $client->mutate('adGroupAds', [[
                'create' => [
                    'adGroup' => "customers/{$client->customerId()}/adGroups/{$adGroupId}",
                    'status'  => 'ENABLED',
                    'ad'      => [
                        'finalUrls'          => [$finalUrl],
                        'responsiveSearchAd' => [
                            'headlines'    => array_map(fn ($text) => ['text' => $text], $headlines),
                            'descriptions' => array_map(fn ($text) => ['text' => $text], $descriptions),
                        ],
                    ],
                ],
            ]]);
        } catch (GoogleAdsException $exception) {
            $this->refuse($exception, 'headlines');
        }

        /* `adGroupAds` resources are addressed as adGroupId~adId, so the ad's own id is the tail. */
        $adId = Arr::last(explode('~', (string) Arr::get($results, '0.resourceName')));

        $adGroups[$groupIndex]['ads'][] = [
            'id'           => $adId,
            'type'         => 'RESPONSIVE_SEARCH_AD',
            'status'       => 'ENABLED',
            'final_urls'   => [$finalUrl],
            'headlines'    => $headlines,
            'descriptions' => $descriptions,

            /* Google rates an ad and reviews it on its own schedule, so both are unknown until the
               next fetch. Left null rather than guessed: the page prints "not rated yet". */
            'strength'        => null,
            'approval_status' => null,
        ];

        $data['ad_groups'] = $adGroups;
        $campaign->update(['data' => $data]);

        $path = "ad_group.{$adGroupId}.ad.{$adId}";

        $campaign->auditEvent     = 'updated';
        $campaign->isCustomEvent  = true;
        $campaign->auditCustomOld = [$path => null];
        $campaign->auditCustomNew = [$path => implode(' | ', $headlines)];
        Event::dispatch(new AuditCustom($campaign));
        $campaign->isCustomEvent  = false;
        $campaign->auditCustomOld = [];
        $campaign->auditCustomNew = [];

        return $campaign->refresh();
    }

    private function locateAdGroup(array $adGroups, string $adGroupId): ?int
    {
        foreach ($adGroups as $index => $group) {
            if ((string) Arr::get($group, 'id') === $adGroupId) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function lines(array $modelData, string $key): array
    {
        return collect((array) Arr::get($modelData, $key, []))
            ->map(fn ($line) => trim((string) $line))
            ->filter()
            ->values()
            ->all();
    }

    public function rules(): array
    {
        return [
            'ad_group_id' => ['required', 'string', 'max:32'],
            'final_url'   => ['required', 'url', 'max:2048'],

            /* Google's own shape for a responsive search ad, and its refusal to accept an asset twice
               within one ad, compared without regard to case. */
            'headlines'      => ['required', 'array', 'min:3', 'max:15'],
            'headlines.*'    => ['required', 'string', 'max:'.self::HEADLINE_LENGTH, 'distinct:ignore_case'],
            'descriptions'   => ['required', 'array', 'min:2', 'max:4'],
            'descriptions.*' => ['required', 'string', 'max:'.self::DESCRIPTION_LENGTH, 'distinct:ignore_case'],
        ];
    }

    /**
     * @throws GoogleAdsException
     */
    public function asController(Organisation $organisation, Shop $shop, TrafficSourceCampaign $trafficSourceCampaign, ActionRequest $request): TrafficSourceCampaign
    {
        if (
            $trafficSourceCampaign->trafficSource->shop_id !== $shop->id
            || $trafficSourceCampaign->trafficSource->type !== TrafficSourcesTypeEnum::GOOGLE_ADS->value
        ) {
            throw new NotFoundHttpException();
        }

        $this->initialisationFromShop($shop, $request);

        return $this->handle($trafficSourceCampaign, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back()->with('notification', [
            'status'      => 'success',
            'title'       => __('Ad created and running'),
            'description' => __('Google reviews it before it shows, and rates it overnight. Pause it here if you change your mind.'),
        ]);
    }
}
