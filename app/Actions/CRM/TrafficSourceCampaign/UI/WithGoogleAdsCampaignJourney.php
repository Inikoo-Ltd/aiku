<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\TrafficSourceCampaign\UI;

use App\Actions\CRM\TrafficSourceCampaign\GoogleAds\UpdateInProcessGoogleAdsCampaign;
use App\Models\CRM\TrafficSourceCampaign;

trait WithGoogleAdsCampaignJourney
{
    /**
     * @return array<int, string>
     */
    public function missingForGoogle(TrafficSourceCampaign $campaign): array
    {
        return UpdateInProcessGoogleAdsCampaign::missing((string) $campaign->channel_type, $campaign->data ?? []);
    }

    /**
     * @return array<int, array{key: string, label: string, current: bool, done: bool, disabled: bool, route: array{name: string, parameters: array<string, string>}}>
     */
    public function getGoogleAdsCampaignJourney(TrafficSourceCampaign $campaign, string $current): array
    {
        if (!$campaign->state->isInProcess()) {
            return [];
        }

        $parameters = [
            'organisation'          => $campaign->trafficSource->shop->organisation->slug,
            'shop'                  => $campaign->trafficSource->shop->slug,
            'trafficSourceCampaign' => $campaign->slug,
        ];

        $isComposed = $this->missingForGoogle($campaign) === [];

        $steps = [
            [
                'key'      => 'compose',
                'label'    => __('Compose'),
                'done'     => $isComposed,
                'disabled' => false,
                'route'    => ['name' => 'grp.org.shops.show.marketing.google_ads.show', 'parameters' => $parameters],
            ],
            [
                'key'      => 'review',
                'label'    => __('Review & Publish'),
                'done'     => false,
                'disabled' => false,
                'route'    => ['name' => 'grp.org.shops.show.marketing.google_ads.review', 'parameters' => $parameters],
            ],
        ];

        return array_map(
            fn (array $step) => array_merge($step, [
                'current' => $step['key'] === $current,
                'done'    => $step['done'] && $step['key'] !== $current,
            ]),
            $steps
        );
    }
}
