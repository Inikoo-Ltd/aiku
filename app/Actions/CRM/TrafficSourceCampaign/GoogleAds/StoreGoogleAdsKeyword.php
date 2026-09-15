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
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use OwenIt\Auditing\Events\AuditCustom;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Adds a keyword to one of the campaign's ad groups.
 *
 * The counterpart to excluding a term: the search terms report shows what people really typed, and
 * the two things worth doing about a row are bidding on it deliberately or refusing to bid on it
 * again. Both now happen from the same table.
 *
 * A keyword starts costing money as soon as Google accepts it, which is why the ad group it lands in
 * is chosen rather than guessed at whenever the campaign has more than one.
 */
class StoreGoogleAdsKeyword extends OrgAction
{
    use WithGoogleAdsWriteErrors;

    private const array MATCH_TYPES = ['BROAD', 'PHRASE', 'EXACT'];

    /**
     * @throws GoogleAdsException
     */
    public function handle(TrafficSourceCampaign $campaign, array $modelData): TrafficSourceCampaign
    {
        $shop   = $campaign->trafficSource->shop;
        $client = GoogleAdsClient::forShop($shop);

        if (!$client) {
            throw ValidationException::withMessages([
                'text' => GoogleAdsClient::unreachableReason($shop) ?? __('Google Ads is not configured for this shop.'),
            ]);
        }

        $adGroupId = (string) Arr::get($modelData, 'ad_group_id');
        $text      = trim((string) Arr::get($modelData, 'text'));
        $matchType = (string) Arr::get($modelData, 'match_type', 'PHRASE');

        $data       = $campaign->data ?? [];
        $adGroups   = $data['ad_groups'] ?? [];
        $groupIndex = $this->locateAdGroup($adGroups, $adGroupId);

        if ($groupIndex === null) {
            throw ValidationException::withMessages([
                'ad_group_id' => __('That ad group is not on this campaign. It may have been removed in Google Ads since the last fetch.'),
            ]);
        }

        /* Google compares keyword text without regard to case, so re-adding one that differs only in
           capitalisation would create a second criterion bidding on the same thing twice. */
        foreach (Arr::get($adGroups[$groupIndex], 'keywords', []) as $keyword) {
            if (mb_strtolower((string) Arr::get($keyword, 'text')) === mb_strtolower($text)
                && Arr::get($keyword, 'match_type') === $matchType) {
                throw ValidationException::withMessages([
                    'text' => __('This ad group already bids on that keyword on the same match type.'),
                ]);
            }
        }

        try {
            $results = $client->mutate('adGroupCriteria', [[
                'create' => [
                    'adGroup' => "customers/{$client->customerId()}/adGroups/{$adGroupId}",
                    'status'  => 'ENABLED',
                    'keyword' => ['text' => $text, 'matchType' => $matchType],
                ],
            ]]);
        } catch (GoogleAdsException $exception) {
            $this->refuse($exception, 'text');
        }

        $criterionId = Arr::last(explode('~', (string) Arr::get($results, '0.resourceName')));

        $adGroups[$groupIndex]['keywords'][] = [
            'id'         => $criterionId,
            'text'       => $text,
            'match_type' => $matchType,
            'status'     => 'ENABLED',
        ];

        $data['ad_groups'] = $adGroups;
        $campaign->update(['data' => $data]);

        $path = "ad_group.{$adGroupId}.keyword.{$criterionId}";

        $campaign->auditEvent     = 'updated';
        $campaign->isCustomEvent  = true;
        $campaign->auditCustomOld = [$path => null];
        $campaign->auditCustomNew = [$path => "{$matchType}: {$text}"];
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

    public function rules(): array
    {
        return [
            'ad_group_id' => ['required', 'string', 'max:32'],
            'text'        => ['required', 'string', 'max:80'],
            'match_type'  => ['sometimes', 'nullable', Rule::in(self::MATCH_TYPES)],
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
