<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\UI\Dropshipping\Marketing;

use App\Enums\Comms\WhatsappCampaign\WhatsappCampaignStateEnum;
use App\Enums\CRM\Livechat\MetaTrackingEventTypeEnum;
use App\Models\Comms\WhatsappCampaign;
use Illuminate\Support\Arr;

trait WithWhatsappCampaignTimeline
{
    /**
     * The timestamp column that marks a campaign entering each state.
     */
    private const STATE_TIMESTAMPS = [
        'in_process' => 'created_at',
        'ready'      => 'ready_at',
        'scheduled'  => 'scheduled_at',
        'sending'    => 'start_sending_at',
        'sent'       => 'sent_at',
        'cancelled'  => 'cancelled_at',
        'stopped'    => 'stopped_at',
    ];

    /**
     * Keyed by state value and in enum declaration order, because Timeline.vue decides
     * which steps read as done purely from a step's position against the current state.
     *
     * @return array<string, array{label: string, tooltip: string, key: string, icon: string, timestamp: string|null}>
     */
    public function getWhatsappCampaignTimeline(WhatsappCampaign $campaign): array
    {
        $icons = WhatsappCampaignStateEnum::stateIcon();

        $timeline = [];

        foreach (WhatsappCampaignStateEnum::cases() as $state) {
            $label = $state->labels()[$state->value];

            $timeline[$state->value] = [
                'label'     => $label,
                'tooltip'   => $label,
                'key'       => $state->value,
                'icon'      => $icons[$state->value]['icon'],
                'timestamp' => $campaign->{self::STATE_TIMESTAMPS[$state->value]}?->toIso8601String(),
            ];
        }

        /* Cancelled and stopped are the two ways a campaign ends early. Only the one that
           actually happened belongs on the strip, so an ordinary campaign shows the five
           lifecycle steps rather than two dead branches it will never reach. */
        return Arr::except($timeline, match ($campaign->state) {
            WhatsappCampaignStateEnum::CANCELLED => [WhatsappCampaignStateEnum::STOPPED->value],
            WhatsappCampaignStateEnum::STOPPED   => [WhatsappCampaignStateEnum::CANCELLED->value],
            default                              => [
                WhatsappCampaignStateEnum::CANCELLED->value,
                WhatsappCampaignStateEnum::STOPPED->value,
            ],
        });
    }

    /**
     * @return array<int, array{label: string, key: string, icon: string, value: int}>
     */
    public function getWhatsappCampaignStats(WhatsappCampaign $campaign): array
    {
        $stats = $campaign->stats;
        $icons = MetaTrackingEventTypeEnum::typeIcon();

        $boxes = [];

        foreach (MetaTrackingEventTypeEnum::cases() as $type) {
            $boxes[] = [
                'label' => MetaTrackingEventTypeEnum::labels()[$type->value],
                'key'   => 'number_'.$type->value,
                'icon'  => $icons[$type->value]['icon'],
                'value' => (int) ($stats?->{'number_'.$type->value} ?? 0),
            ];
        }

        // ponytail: clicked has no source yet, so the box is always 0; it needs a clicked_at
        // on meta_chat_messages before this reads anything real.
        array_splice($boxes, 3, 0, [[
            'label' => __('Clicked'),
            'key'   => 'number_clicked',
            'icon'  => 'fal fa-hand-pointer',
            'value' => (int) ($stats?->number_clicked ?? 0),
        ]]);

        return $boxes;
    }
}
