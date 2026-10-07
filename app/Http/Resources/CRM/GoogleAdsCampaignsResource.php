<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 08 Sep 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\CRM;

use App\Actions\CRM\TrafficSourceCampaign\UI\IndexGoogleAdsCampaigns;
use App\Enums\CRM\TrafficSource\GoogleAdsCampaignStateEnum;
use App\Models\CRM\TrafficSourceCampaignMetric;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @property mixed $status
 * @property mixed $channel_type
 * @property mixed $started_at
 * @property mixed $budget_amount
 * @property mixed $currency_code
 * @property mixed $impressions
 * @property mixed $clicks
 * @property mixed $ctr
 * @property mixed $avg_cpc
 * @property mixed $conversions
 * @property mixed $cost_per_conversion
 * @property mixed $conversions_value
 * @property mixed $spend
 * @property mixed $roas
 */
class GoogleAdsCampaignsResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var \App\Models\CRM\TrafficSourceCampaign $campaign */
        $campaign = $this->resource;

        return [
            'id'        => $campaign->id,
            'slug'      => $campaign->slug,
            'reference' => $campaign->reference,
            'name'      => $campaign->name,
            'route'     => [
                'name'       => 'grp.org.shops.show.marketing.google_ads.show',
                'parameters' => array_merge(
                    request()->route()->originalParameters(),
                    ['trafficSourceCampaign' => $campaign->slug]
                ),
            ],
            'status'        => $this->statusIcon($campaign),
            'channel_type'  => $campaign->channel_type,
            'started_at'    => $campaign->started_at ? Carbon::parse($campaign->started_at)->toIso8601String() : null,
            'budget_amount' => $campaign->budget_amount,

            /* The account's currency, which is what the budget and Google's own figures are quoted in.
               Spend is the shop's currency instead, so the two are never formatted from one code. */
            'currency_code' => $campaign->currency_code,

            'impressions'           => (int) $campaign->impressions,
            'clicks'                => (int) $campaign->clicks,
            'ctr'                   => $campaign->ctr !== null ? (float) $campaign->ctr : null,
            'avg_cpc'               => $campaign->avg_cpc !== null ? (float) $campaign->avg_cpc : null,
            'conversions'           => (float) $campaign->conversions,
            'cost_per_conversion'   => $campaign->cost_per_conversion !== null ? (float) $campaign->cost_per_conversion : null,
            'conversions_value'     => (float) $campaign->conversions_value,
            'spend'                 => (float) $campaign->spend,
            'roas'                  => $campaign->roas !== null ? (float) $campaign->roas : null,
            'all_conversions'       => (float) $campaign->all_conversions,
            'all_conversions_value' => (float) $campaign->all_conversions_value,
            ...$this->nullableFloats([
                'purchases',
                'cost_per_purchase',
                'purchase_rate',
                'registrations',
                'cost_per_registration',
                'registration_rate',
                ...TrafficSourceCampaignMetric::IMPRESSION_SHARE_COLUMNS,
            ]),

            /* Only present when the listing was asked to compare, so the table knows to draw the
               change under each figure rather than guessing from a row of nulls. */
            'previous' => $this->when($this->hasPreviousPeriod(), fn () => $this->previousValues()),
        ];
    }

    private function hasPreviousPeriod(): bool
    {
        return array_key_exists('impressions'.IndexGoogleAdsCampaigns::PREVIOUS_SUFFIX, $this->resource->getAttributes());
    }

    /**
     * @return array<string, float|null>
     */
    private function previousValues(): array
    {
        $values = [];

        foreach (IndexGoogleAdsCampaigns::METRIC_KEYS as $key) {
            $previous     = $this->resource->{$key.IndexGoogleAdsCampaigns::PREVIOUS_SUFFIX};
            $values[$key] = $previous !== null ? (float) $previous : null;
        }

        return $values;
    }

    /**
     * Where the campaign stands, as an icon, the way every other marketing listing leads with its
     * state.
     *
     * Aiku's own state answers first for a campaign Google has never heard of, and last for one it
     * has: in between, Google's status is the better answer because it separates the campaigns that
     * waste money from the ones that do not. LIMITED is a budget capping delivery and NOT_ELIGIBLE is
     * a switched-on campaign showing nobody anything, and neither is visible in a state of its own.
     *
     * @return array{icon: string, class: string, tooltip: string}
     */
    private function statusIcon(mixed $campaign): array
    {
        /* Cast to the enum by the model, so a campaign read straight from the database carries the
           case and not the string it is stored as. */
        $state = $campaign->state instanceof GoogleAdsCampaignStateEnum
            ? $campaign->state
            : GoogleAdsCampaignStateEnum::tryFrom((string) $campaign->state);

        /* Saying "not read from Google yet" about a campaign that was never sent to Google would read
           as a broken fetch rather than as unfinished work. */
        if ($state?->isInProcess()) {
            return GoogleAdsCampaignStateEnum::stateIcon()[$state->value];
        }

        /* Published this morning, and the fetch that reads Google's status runs tonight. The state is
           the true answer until then, rather than a question mark against a campaign whose publish
           everyone just watched succeed. */
        if ($state && blank($campaign->status)) {
            return GoogleAdsCampaignStateEnum::stateIcon()[$state->value];
        }

        /* The two campaigns that are reaching people share the glyph and differ only in colour. A
           budget-limited one is serving too, it simply runs out of money before the day does, and
           amber against green is what sends somebody to the one worth looking at. */
        return match ($campaign->status) {
            'ELIGIBLE', 'ENABLED' => ['icon' => 'fal fa-paper-plane', 'class' => 'text-green-600', 'tooltip' => __('Serving')],
            'LIMITED'             => ['icon' => 'fal fa-paper-plane', 'class' => 'text-amber-500', 'tooltip' => __('Budget limited, the budget is capping delivery')],
            'NOT_ELIGIBLE'        => ['icon' => 'fal fa-ban', 'class' => 'text-red-500', 'tooltip' => __('Not serving, nobody is being shown this campaign')],
            'PENDING'             => ['icon' => 'fal fa-clock', 'class' => 'text-gray-400', 'tooltip' => __('Pending, it has not started yet')],
            'PAUSED'              => ['icon' => 'fal fa-pause', 'class' => 'text-gray-400', 'tooltip' => __('Paused')],
            'ENDED'               => ['icon' => 'fal fa-flag-checkered', 'class' => 'text-gray-400', 'tooltip' => __('Ended')],
            'REMOVED'             => ['icon' => 'fal fa-trash', 'class' => 'text-gray-400', 'tooltip' => __('Removed')],
            null                  => ['icon' => 'fal fa-question-circle', 'class' => 'text-gray-300', 'tooltip' => __('Not read from Google yet')],
            default               => ['icon' => 'fal fa-question-circle', 'class' => 'text-gray-400', 'tooltip' => __('Google reports this as :status', ['status' => $campaign->status])],
        };
    }

    /**
     * @param array<int, string> $keys
     * @return array<string, float|null>
     */
    private function nullableFloats(array $keys): array
    {
        $values = [];

        foreach ($keys as $key) {
            $values[$key] = $this->resource->{$key} !== null ? (float) $this->resource->{$key} : null;
        }

        return $values;
    }
}
