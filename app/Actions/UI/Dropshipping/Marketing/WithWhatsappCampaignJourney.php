<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\UI\Dropshipping\Marketing;

use App\Enums\Comms\WhatsappCampaign\WhatsappCampaignStateEnum;
use App\Models\Comms\WhatsappCampaign;

trait WithWhatsappCampaignJourney
{
    /**
     * @return array<int, array{key: string, label: string, current: bool, done: bool, route: array{name: string, parameters: array<int, string>}}>
     */
    public function getWhatsappCampaignJourney(WhatsappCampaign $campaign, string $current): array
    {
        if (!in_array($campaign->state, [WhatsappCampaignStateEnum::IN_PROCESS, WhatsappCampaignStateEnum::READY])) {
            return [];
        }

        $routeBase  = 'grp.org.shops.show.marketing.whatsapp_campaigns';
        $parameters = [
            $campaign->organisation->slug,
            $campaign->shop->slug,
            $campaign->slug
        ];

        $isComposed = (bool) $campaign->meta_message_template_id;

        $steps = [
            [
                'key'   => 'compose',
                'done'  => $isComposed,
                'label' => __('Compose'),
                'route' => [
                    'name'       => "$routeBase.workshop",
                    'parameters' => $parameters
                ]
            ],
            [
                'key'      => 'review',
                'done'     => false,
                'disabled' => !$isComposed,
                'label'    => __('Preview & send'),
                'route'    => [
                    'name'       => "$routeBase.show",
                    'parameters' => $parameters
                ]
            ],
        ];

        return array_map(
            fn (array $step) => array_merge($step, [
                'current' => $step['key'] === $current,
                'done'    => $step['done'] && $step['key'] !== $current
            ]),
            $steps
        );
    }
}
