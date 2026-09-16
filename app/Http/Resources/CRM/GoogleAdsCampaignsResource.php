<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 08 Sep 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\CRM;

use App\Actions\CRM\TrafficSourceCampaign\UI\IndexGoogleAdsCampaigns;
use App\Models\CRM\TrafficSourceCampaignMetric;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $status
 * @property mixed $channel_type
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
            'status'        => $this->statusIcon($campaign->status),
            'channel_type'  => $campaign->channel_type,
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
     * Google's serving status as an icon, because the listing shows it for forty campaigns at once and
     * the only two worth stopping on are the ones that waste money: LIMITED, where the budget is
     * capping delivery, and NOT_ELIGIBLE, where nothing is being shown despite the campaign being
     * switched on. Colour finds them, the tooltip names them.
     *
     * @return array{icon: string, class: string, tooltip: string}
     */
    private function statusIcon(?string $status): array
    {
        return match ($status) {
            'ELIGIBLE', 'ENABLED' => ['icon' => 'fal fa-play', 'class' => 'text-green-500', 'tooltip' => __('Serving')],
            'LIMITED'             => ['icon' => 'fal fa-exclamation-triangle', 'class' => 'text-amber-500', 'tooltip' => __('Budget limited, the budget is capping delivery')],
            'NOT_ELIGIBLE'        => ['icon' => 'fal fa-ban', 'class' => 'text-red-500', 'tooltip' => __('Not serving, nobody is being shown this campaign')],
            'PENDING'             => ['icon' => 'fal fa-clock', 'class' => 'text-gray-400', 'tooltip' => __('Pending, it has not started yet')],
            'PAUSED'              => ['icon' => 'fal fa-pause', 'class' => 'text-gray-400', 'tooltip' => __('Paused')],
            'ENDED'               => ['icon' => 'fal fa-flag-checkered', 'class' => 'text-gray-400', 'tooltip' => __('Ended')],
            'REMOVED'             => ['icon' => 'fal fa-trash', 'class' => 'text-gray-400', 'tooltip' => __('Removed')],
            null                  => ['icon' => 'fal fa-question-circle', 'class' => 'text-gray-300', 'tooltip' => __('Not read from Google yet')],
            default               => ['icon' => 'fal fa-question-circle', 'class' => 'text-gray-400', 'tooltip' => __('Google reports this as :status', ['status' => $status])],
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
