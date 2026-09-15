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
 * Adds headlines to a responsive search ad that is running on fewer than it could.
 *
 * Google builds each impression by picking from the headlines an ad carries, so an ad with four of a
 * possible fifteen has far less to test and a weaker ad strength than one with fifteen. Adding to it
 * costs nothing and needs no new ad.
 *
 * The whole list is sent, not the additions: `responsive_search_ad.headlines` is replaced wholesale by
 * an update, so the existing ones travel with the new and a caller that sent only the additions would
 * silently delete the ad's original copy.
 *
 * Duplicates are refused before sending. Google rejects an ad whose assets repeat, and it compares
 * them without regard to case, so `Fast UK Delivery` next to `fast uk delivery` fails the whole write.
 */
class UpdateGoogleAdsAdAssets extends OrgAction
{
    use WithGoogleAdsWriteErrors;

    private const int MAX_HEADLINES = 15;

    private const int HEADLINE_LENGTH = 30;

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

        $adGroupId = (string) Arr::get($modelData, 'ad_group_id');
        $adId      = (string) Arr::get($modelData, 'ad_id');
        $data      = $campaign->data ?? [];
        $adGroups  = $data['ad_groups'] ?? [];

        [$groupIndex, $adIndex] = $this->locate($adGroups, $adGroupId, $adId) ?? [null, null];

        if ($groupIndex === null) {
            throw ValidationException::withMessages([
                'ad_id' => __('That ad is not on this campaign. It may have been removed in Google Ads since the last fetch.'),
            ]);
        }

        $existing = Arr::get($adGroups[$groupIndex]['ads'][$adIndex], 'headlines', []);
        $combined = $this->combine($existing, (array) Arr::get($modelData, 'headlines', []));

        if (count($combined) === count($existing)) {
            throw ValidationException::withMessages([
                'headlines' => __('Those headlines are already on this ad, or duplicate each other.'),
            ]);
        }

        $descriptions = Arr::get($adGroups[$groupIndex]['ads'][$adIndex], 'descriptions', []);

        try {
            $client->mutate('ads', [[
                'update' => [
                    'resourceName'       => "customers/{$client->customerId()}/ads/{$adId}",
                    'responsiveSearchAd' => [
                        'headlines'    => array_map(fn ($text) => ['text' => $text], $combined),
                        'descriptions' => array_map(fn ($text) => ['text' => $text], $descriptions),
                    ],
                ],
                'updateMask' => 'responsive_search_ad.headlines',
            ]]);
        } catch (GoogleAdsException $exception) {
            $this->refuse($exception, 'headlines');
        }

        $adGroups[$groupIndex]['ads'][$adIndex]['headlines'] = $combined;
        $data['ad_groups']                                   = $adGroups;
        $campaign->update(['data' => $data]);

        $path = "ad_group.{$adGroupId}.ad.{$adId}.headlines";

        $campaign->auditEvent     = 'updated';
        $campaign->isCustomEvent  = true;
        $campaign->auditCustomOld = [$path => implode(' | ', $existing)];
        $campaign->auditCustomNew = [$path => implode(' | ', $combined)];
        Event::dispatch(new AuditCustom($campaign));
        $campaign->isCustomEvent  = false;
        $campaign->auditCustomOld = [];
        $campaign->auditCustomNew = [];

        return $campaign->refresh();
    }

    /**
     * The ad's existing headlines followed by whichever additions are new, trimmed to what Google
     * accepts. Order matters: the originals keep their place so an ad that was performing is added to
     * rather than rewritten.
     *
     * @param array<int, string> $existing
     * @param array<int, string> $additions
     * @return array<int, string>
     */
    private function combine(array $existing, array $additions): array
    {
        $seen     = collect($existing)->map(fn ($text) => mb_strtolower(trim($text)))->flip();
        $combined = $existing;

        foreach ($additions as $addition) {
            $addition = trim((string) $addition);
            $key      = mb_strtolower($addition);

            if ($addition === '' || mb_strlen($addition) > self::HEADLINE_LENGTH || $seen->has($key)) {
                continue;
            }

            if (count($combined) >= self::MAX_HEADLINES) {
                break;
            }

            $combined[] = $addition;
            $seen->put($key, true);
        }

        return $combined;
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    private function locate(array $adGroups, string $adGroupId, string $adId): ?array
    {
        foreach ($adGroups as $groupIndex => $group) {
            if ((string) Arr::get($group, 'id') !== $adGroupId) {
                continue;
            }

            foreach (Arr::get($group, 'ads', []) as $adIndex => $ad) {
                if ((string) Arr::get($ad, 'id') === $adId) {
                    return [$groupIndex, $adIndex];
                }
            }

            return null;
        }

        return null;
    }

    public function rules(): array
    {
        return [
            'ad_group_id' => ['required', 'string', 'max:32'],
            'ad_id'       => ['required', 'string', 'max:32'],
            'headlines'   => ['required', 'array', 'min:1', 'max:15'],
            'headlines.*' => ['required', 'string', 'max:'.self::HEADLINE_LENGTH, 'distinct:ignore_case'],
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
        return back();
    }
}
