<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\TrafficSourceCampaign\GoogleAds;

use App\Actions\OrgAction;
use App\Models\CRM\TrafficSourceCampaign;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * Saves what has been filled in on a campaign that is still only in Aiku.
 *
 * Nothing here is required. A campaign in process is a thing somebody comes back to over several
 * sittings, so a half-filled one saves exactly as it is; what it still needs is reported separately
 * and shown on the page, rather than refused at the point of typing.
 *
 * The strict rules live in the publish, where they belong: that is the moment the campaign has to be
 * whole, and Google is the one that decides.
 */
class UpdateInProcessGoogleAdsCampaign extends OrgAction
{
    private const array MATCH_TYPES = ['BROAD', 'PHRASE', 'EXACT'];

    /**
     * What Google will not create a campaign of each type without. Its own minimums, kept here so the
     * page can say what is outstanding while it is being written rather than at the end.
     */
    private const array REQUIRED_TEXT = [
        'SEARCH'          => ['headlines' => 3, 'descriptions' => 2, 'keywords' => 1],
        'DISPLAY'         => ['headlines' => 1, 'descriptions' => 1],
        'DEMAND_GEN'      => ['headlines' => 1, 'descriptions' => 1],
        'PERFORMANCE_MAX' => ['headlines' => 3, 'descriptions' => 2],
    ];

    private const array REQUIRED_IMAGES = [
        'DISPLAY'         => ['marketing_images', 'square_marketing_images', 'logos'],
        'DEMAND_GEN'      => ['marketing_images', 'logos'],
        'PERFORMANCE_MAX' => ['marketing_images', 'square_marketing_images', 'logos'],
    ];

    public function handle(TrafficSourceCampaign $campaign, array $modelData): TrafficSourceCampaign
    {
        if (!$campaign->state->isInProcess()) {
            throw ValidationException::withMessages([
                'name' => __('This campaign already exists at Google, so it is changed there rather than here.'),
            ]);
        }

        $name = Arr::pull($modelData, 'name');

        $campaign->update(array_filter([
            'name' => $name ? trim($name) : null,
            'data' => array_merge($campaign->data ?? [], $modelData),
        ]));

        return $campaign->refresh();
    }

    /**
     * What is still needed before Google would look at this, in words somebody can act on.
     *
     * @return array<int, string>
     */
    public static function missing(string $channelType, array $data): array
    {
        $missing = [];

        foreach (['budget_amount' => __('a daily budget'), 'final_url' => __('a landing page')] as $key => $label) {
            if (blank(Arr::get($data, $key))) {
                $missing[] = $label;
            }
        }

        $groupKey = $channelType === 'PERFORMANCE_MAX' ? 'asset_group_name' : 'ad_group_name';

        if (blank(Arr::get($data, $groupKey))) {
            $missing[] = $channelType === 'PERFORMANCE_MAX' ? __('an asset group name') : __('an ad group name');
        }

        if ($channelType !== 'SEARCH' && blank(Arr::get($data, 'business_name'))) {
            $missing[] = __('a business name');
        }

        $labels = [
            'headlines'    => __('headlines'),
            'descriptions' => __('descriptions'),
            'keywords'     => __('keywords'),
        ];

        foreach (Arr::get(self::REQUIRED_TEXT, $channelType, []) as $key => $least) {
            if (count(array_filter((array) Arr::get($data, $key, []))) < $least) {
                $missing[] = __(':count :thing', ['count' => $least, 'thing' => $labels[$key]]);
            }
        }

        $imageLabels = [
            'marketing_images'        => __('a landscape image'),
            'square_marketing_images' => __('a square image'),
            'logos'                   => __('a logo'),
        ];

        foreach (Arr::get(self::REQUIRED_IMAGES, $channelType, []) as $key) {
            if (count((array) Arr::get($data, $key, [])) === 0) {
                $missing[] = $imageLabels[$key];
            }
        }

        return $missing;
    }

    public function asController(TrafficSourceCampaign $trafficSourceCampaign, ActionRequest $request): TrafficSourceCampaign
    {
        $this->initialisationFromShop($trafficSourceCampaign->trafficSource->shop, $request);

        return $this->handle($trafficSourceCampaign, $this->validatedData);
    }

    /**
     * Google's own limits on each field, so an over-long headline is caught against its own box. The
     * counts are not enforced here, only the shape of each value.
     */
    public function rules(): array
    {
        return [
            'name'          => ['sometimes', 'string', 'max:255'],
            'budget_amount' => ['sometimes', 'nullable', 'numeric', 'min:0.01', 'max:1000000'],
            'max_cpc'       => ['sometimes', 'nullable', 'numeric', 'min:0.01', 'max:1000000'],
            'target_cpa'    => ['sometimes', 'nullable', 'numeric', 'min:0.01', 'max:1000000'],
            'final_url'     => ['sometimes', 'nullable', 'url', 'max:2048'],
            'business_name' => ['sometimes', 'nullable', 'string', 'max:25'],

            'ad_group_name'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'asset_group_name' => ['sometimes', 'nullable', 'string', 'max:255'],

            'country_codes'   => ['sometimes', 'array', 'max:50'],
            'country_codes.*' => ['string', 'size:2'],

            'headlines'      => ['sometimes', 'array', 'max:15'],
            'headlines.*'    => ['string', 'max:30'],
            'long_headline'  => ['sometimes', 'nullable', 'string', 'max:90'],
            'descriptions'   => ['sometimes', 'array', 'max:5'],
            'descriptions.*' => ['string', 'max:90'],

            'keywords'        => ['sometimes', 'array', 'max:100'],
            'keywords.*'      => ['string', 'max:80'],
            'match_type'      => ['sometimes', 'nullable', Rule::in(self::MATCH_TYPES)],
            'search_themes'   => ['sometimes', 'array', 'max:25'],
            'search_themes.*' => ['string', 'max:80'],

            'marketing_images'          => ['sometimes', 'array', 'max:20'],
            'marketing_images.*'        => ['integer'],
            'square_marketing_images'   => ['sometimes', 'array', 'max:20'],
            'square_marketing_images.*' => ['integer'],
            'logos'                     => ['sometimes', 'array', 'max:5'],
            'logos.*'                   => ['integer'],
        ];
    }
}
