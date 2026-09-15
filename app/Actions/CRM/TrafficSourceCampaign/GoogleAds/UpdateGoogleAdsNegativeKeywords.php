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
 * Adds or removes a campaign level negative keyword, the cheapest lever there is: one bad search term
 * excluded stops paying for every click it would have brought for the rest of the campaign's life.
 *
 * Removing really removes. Unlike a paused campaign, a negative keyword has no off switch in the API,
 * so `remove` is the only way to stop excluding a term, and adding it back afterwards creates a new
 * criterion with a new id rather than restoring the old one.
 */
class UpdateGoogleAdsNegativeKeywords extends OrgAction
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

        $data      = $campaign->data ?? [];
        $negatives = $data['negative_keywords'] ?? [];

        [$negatives, $auditOld, $auditNew] = Arr::get($modelData, 'criterion_id')
            ? $this->remove($client, $campaign, $negatives, (string) Arr::get($modelData, 'criterion_id'))
            : $this->add($client, $campaign, $negatives, $modelData);

        $data['negative_keywords'] = array_values($negatives);
        $campaign->update(['data' => $data]);

        $campaign->auditEvent     = 'updated';
        $campaign->isCustomEvent  = true;
        $campaign->auditCustomOld = $auditOld;
        $campaign->auditCustomNew = $auditNew;
        Event::dispatch(new AuditCustom($campaign));
        $campaign->isCustomEvent  = false;
        $campaign->auditCustomOld = [];
        $campaign->auditCustomNew = [];

        return $campaign->refresh();
    }

    /**
     * @throws GoogleAdsException
     */
    private function add(GoogleAdsClient $client, TrafficSourceCampaign $campaign, array $negatives, array $modelData): array
    {
        $text      = trim((string) Arr::get($modelData, 'text'));
        $matchType = (string) Arr::get($modelData, 'match_type', 'PHRASE');

        /* Google matches negatives case insensitively, so adding "Free Shipping" next to an existing
           "free shipping" would create a second criterion that excludes nothing new. */
        foreach ($negatives as $negative) {
            if (mb_strtolower((string) Arr::get($negative, 'text')) === mb_strtolower($text)
                && Arr::get($negative, 'match_type') === $matchType) {
                throw ValidationException::withMessages([
                    'text' => __('This campaign already excludes that term on the same match type.'),
                ]);
            }
        }

        try {
            $results = $client->mutate('campaignCriteria', [[
                'create' => [
                    'campaign' => "customers/{$client->customerId()}/campaigns/{$campaign->reference}",
                    'negative' => true,
                    'keyword'  => ['text' => $text, 'matchType' => $matchType],
                ],
            ]]);
        } catch (GoogleAdsException $exception) {
            $this->refuse($exception, 'text');
        }

        /* The criterion id is only knowable from Google's answer, and the row is useless without it:
           nothing could be removed again until the next nightly fetch filled it in. */
        $criterionId = Arr::last(explode('~', (string) Arr::get($results, '0.resourceName')));

        $negatives[] = [
            'id'         => $criterionId,
            'text'       => $text,
            'match_type' => $matchType,
        ];

        return [
            $negatives,
            ["negative_keyword.{$criterionId}" => null],
            ["negative_keyword.{$criterionId}" => "{$matchType}: {$text}"],
        ];
    }

    /**
     * @throws GoogleAdsException
     */
    private function remove(GoogleAdsClient $client, TrafficSourceCampaign $campaign, array $negatives, string $criterionId): array
    {
        $existing = Arr::first($negatives, fn ($negative) => (string) Arr::get($negative, 'id') === $criterionId);

        if (!$existing) {
            throw ValidationException::withMessages([
                'criterion_id' => __('That negative keyword is no longer on this campaign.'),
            ]);
        }

        try {
            $client->mutate('campaignCriteria', [[
                'remove' => "customers/{$client->customerId()}/campaignCriteria/{$campaign->reference}~{$criterionId}",
            ]]);
        } catch (GoogleAdsException $exception) {
            $this->refuse($exception, 'criterion_id');
        }

        $negatives = array_filter($negatives, fn ($negative) => (string) Arr::get($negative, 'id') !== $criterionId);

        return [
            $negatives,
            ["negative_keyword.{$criterionId}" => Arr::get($existing, 'match_type').': '.Arr::get($existing, 'text')],
            ["negative_keyword.{$criterionId}" => null],
        ];
    }

    public function rules(): array
    {
        return [
            /* Present means remove, absent means add; Google's own limit on a keyword is 80 characters
               and ten words, and it rejects anything longer rather than truncating. */
            'criterion_id' => ['sometimes', 'nullable', 'string', 'max:32'],
            'text'         => ['required_without:criterion_id', 'nullable', 'string', 'max:80'],
            'match_type'   => ['sometimes', 'nullable', Rule::in(self::MATCH_TYPES)],
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
