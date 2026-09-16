<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\TrafficSourceCampaign\GoogleAds;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Services\GoogleAds\GoogleAdsClient;
use App\Services\GoogleAds\GoogleAdsException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * Builds a Search campaign in Google Ads: a budget, the campaign, its country targeting, one ad group,
 * its keywords, and a responsive search ad, created as a single transaction.
 *
 * A campaign is useless without all six. Created one call at a time, an ad refused on policy would
 * leave a funded campaign and an empty ad group behind, and the person who clicked the button would
 * have no idea which half existed. GoogleAdsService applies them together or not at all.
 *
 * It is always created paused. The alternative is a campaign that starts spending the moment the form
 * is submitted, on ads nobody has read back yet, and there is no undo for money already spent.
 */
class StoreGoogleAdsCampaign extends OrgAction
{
    use WithGoogleAdsWriteErrors;

    private const array MATCH_TYPES = ['BROAD', 'PHRASE', 'EXACT'];

    /**
     * Every new campaign must declare this since the EU rules on political advertising came in, and
     * Google rejects a create that omits it. Aiku sells goods, so the answer is always no; a shop that
     * genuinely runs political ads cannot use this form and should build it in Google Ads.
     */
    private const string EU_POLITICAL = 'DOES_NOT_CONTAIN_EU_POLITICAL_ADVERTISING';

    /**
     * @return array{validated: bool, campaign_reference: string|null, resource_name: string|null}
     * @throws GoogleAdsException
     */
    public function handle(Shop $shop, array $modelData): array
    {
        $client = GoogleAdsClient::forShop($shop);

        if (!$client) {
            throw ValidationException::withMessages([
                'name' => GoogleAdsClient::unreachableReason($shop) ?? __('Google Ads is not configured for this shop.'),
            ]);
        }

        $validateOnly = (bool) Arr::get($modelData, 'validate_only', false);

        try {
            $operations = $this->operations($client, $shop, $modelData);
            $responses  = $client->mutateOperations($operations, $validateOnly);
        } catch (GoogleAdsException $exception) {
            $this->refuse($exception, 'name');
        }

        if ($validateOnly) {
            return ['validated' => true, 'campaign_reference' => null, 'resource_name' => null];
        }

        /* The campaign is the second operation, so its response carries the id everything else in
           Aiku keys off. Pulled from the response rather than re-queried: a freshly created campaign
           can take a moment to appear in a search. */
        $resourceName = (string) Arr::get($responses, '1.campaignResult.resourceName');
        $reference    = Arr::last(explode('/', $resourceName));

        return [
            'validated'          => false,
            'campaign_reference' => $reference !== '' ? $reference : null,
            'resource_name'      => $resourceName !== '' ? $resourceName : null,
        ];
    }

    /**
     * @throws GoogleAdsException
     */
    private function operations(GoogleAdsClient $client, Shop $shop, array $modelData): array
    {
        $budgetResource = $client->temporaryResource('campaignBudgets', 1);
        $campaign       = $client->temporaryResource('campaigns', 2);
        $adGroup        = $client->temporaryResource('adGroups', 3);
        $name           = trim((string) Arr::get($modelData, 'name'));

        $campaignCreate = [
            'resourceName'                   => $campaign,
            'name'                           => $name,
            'status'                         => 'PAUSED',
            'advertisingChannelType'         => 'SEARCH',
            'campaignBudget'                 => $budgetResource,
            'containsEuPoliticalAdvertising' => self::EU_POLITICAL,
            'targetSpend'                    => $this->biddingStrategy($modelData),

            /* Search only. The Display network is on by default for a new Search campaign and spends
               the same budget on placements nobody chose, which is the single most common way a new
               campaign quietly wastes money. */
            'networkSettings' => [
                'targetGoogleSearch'         => true,
                'targetSearchNetwork'        => (bool) Arr::get($modelData, 'target_search_partners', false),
                'targetContentNetwork'       => false,
                'targetPartnerSearchNetwork' => false,
            ],
        ];

        $operations = [
            ['campaignBudgetOperation' => ['create' => [
                'resourceName' => $budgetResource,

                /* Budgets carry their own names and Google rejects a duplicate, so it is tied to the
                   campaign name it was made for rather than left to collide with the next one. */
                'name'             => mb_substr($name.' budget', 0, 255),
                'amountMicros'     => (string) (int) round((float) Arr::get($modelData, 'budget_amount') * 1_000_000),
                'deliveryMethod'   => 'STANDARD',
                'explicitlyShared' => false,
            ]]],
            ['campaignOperation' => ['create' => $campaignCreate]],
        ];

        foreach ($this->geoTargetConstants($client, $modelData) as $geoTargetConstant) {
            $operations[] = ['campaignCriterionOperation' => ['create' => [
                'campaign' => $campaign,
                'location' => ['geoTargetConstant' => $geoTargetConstant],
            ]]];
        }

        $operations[] = ['adGroupOperation' => ['create' => [
            'resourceName' => $adGroup,
            'name'         => trim((string) Arr::get($modelData, 'ad_group_name')),
            'campaign'     => $campaign,
            'status'       => 'ENABLED',
            'type'         => 'SEARCH_STANDARD',
        ]]];

        $matchType = (string) Arr::get($modelData, 'match_type', 'PHRASE');

        foreach ($this->keywords($modelData) as $keyword) {
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
                'finalUrls'         => [(string) Arr::get($modelData, 'final_url')],
                'responsiveSearchAd' => [
                    'headlines'    => array_map(fn ($text) => ['text' => $text], $this->lines($modelData, 'headlines')),
                    'descriptions' => array_map(fn ($text) => ['text' => $text], $this->lines($modelData, 'descriptions')),
                ],
            ],
        ]]];

        return $operations;
    }

    /**
     * Maximize clicks, which Google still calls target spend. A ceiling is optional and worth setting:
     * without one the strategy is free to pay whatever a click costs to use the budget up.
     */
    private function biddingStrategy(array $modelData): object|array
    {
        $ceiling = Arr::get($modelData, 'max_cpc');

        if (blank($ceiling)) {
            return (object) [];
        }

        return ['cpcBidCeilingMicros' => (string) (int) round((float) $ceiling * 1_000_000)];
    }

    /**
     * Aiku stores countries by ISO code; Google targets them by its own numeric constant, so the codes
     * are translated in one query rather than a table of magic numbers going stale in the repository.
     *
     * @return array<int, string>
     * @throws GoogleAdsException
     */
    private function geoTargetConstants(GoogleAdsClient $client, array $modelData): array
    {
        $codes = array_filter(array_map('strtoupper', (array) Arr::get($modelData, 'country_codes', [])));

        if ($codes === []) {
            return [];
        }

        $list = "'".implode("','", array_map(fn ($code) => preg_replace('/[^A-Z]/', '', $code), $codes))."'";

        $rows = $client->search(
            "SELECT geo_target_constant.resource_name, geo_target_constant.country_code FROM geo_target_constant
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

    /**
     * @return array<int, string>
     */
    private function keywords(array $modelData): array
    {
        return collect((array) Arr::get($modelData, 'keywords', []))
            ->map(fn ($keyword) => trim((string) $keyword))
            ->filter()
            ->unique()
            ->values()
            ->all();
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

    /**
     * Google's own limits, enforced here so a mistake comes back as a field error on the form rather
     * than as an API rejection with no field attached to it.
     */
    public function rules(): array
    {
        return [
            'name'            => ['required', 'string', 'max:255'],
            'budget_amount'   => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'max_cpc'         => ['sometimes', 'nullable', 'numeric', 'min:0.01', 'max:1000000'],
            'country_codes'   => ['required', 'array', 'min:1', 'max:50'],
            'country_codes.*' => ['required', 'string', 'size:2'],
            'ad_group_name'   => ['required', 'string', 'max:255'],
            'final_url'       => ['required', 'url', 'max:2048'],

            'keywords'   => ['required', 'array', 'min:1', 'max:100'],
            'keywords.*' => ['required', 'string', 'max:80'],
            'match_type' => ['sometimes', 'nullable', Rule::in(self::MATCH_TYPES)],

            /* A responsive search ad needs at least three headlines and two descriptions to run, and
               Google caps them at fifteen and four. */
            /* Google refuses an ad that repeats a headline or a description, so the repetition is
               caught here where it can be shown against the offending box. `ignore_case` because
               Google compares them that way too. */
            'headlines'      => ['required', 'array', 'min:3', 'max:15'],
            'headlines.*'    => ['required', 'string', 'max:30', 'distinct:ignore_case'],
            'descriptions'   => ['required', 'array', 'min:2', 'max:4'],
            'descriptions.*' => ['required', 'string', 'max:90', 'distinct:ignore_case'],

            'target_search_partners' => ['sometimes', 'boolean'],

            /* Runs the whole thing past Google's own validation and creates nothing, so the form can
               say whether it would be accepted before anyone commits to it. */
            'validate_only' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @throws GoogleAdsException
     */
    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): array
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $this->validatedData);
    }

    public function htmlResponse(array $result, ActionRequest $request): RedirectResponse
    {
        if ($result['validated']) {
            return back()->with('notification', [
                'status'      => 'success',
                'title'       => __('Google Ads accepted it'),
                'description' => __('Nothing has been created. Submit to build the campaign.'),
            ]);
        }

        return Redirect::route('grp.org.shops.show.marketing.google_ads.index', [
            $this->organisation->slug,
            $this->shop->slug,
        ])->with('notification', [
            'status'      => 'success',
            'title'       => __('Campaign created, paused'),
            'description' => __('It will not spend anything until you resume it. Read the ad back first, then resume it from its page.'),
        ]);
    }
}
