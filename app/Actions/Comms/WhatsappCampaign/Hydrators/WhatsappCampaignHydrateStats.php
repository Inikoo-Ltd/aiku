<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\WhatsappCampaign\Hydrators;

use App\Actions\Comms\WhatsappCampaign\WithWhatsappRecipientStatusQuery;
use App\Enums\CRM\Livechat\MetaTrackingEventTypeEnum;
use App\Models\Comms\WhatsappCampaign;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Recounts a campaign's delivery stats from its recipients.
 *
 * A full recount rather than per-event increments: SendWhatsappDeliveryChannel leaves
 * meta_chat_message_id null on a failure precisely so a re-run can retry that recipient,
 * which would make an incrementing counter double-count on every retry.
 */
class WhatsappCampaignHydrateStats implements ShouldBeUnique
{
    use AsAction;
    use WithWhatsappRecipientStatusQuery;

    public string $jobQueue = 'analytics';

    public function getJobUniqueId(?int $campaignId): string
    {
        return $campaignId ?? 'empty';
    }

    public function handle(?int $campaignId): void
    {
        if (!$campaignId) {
            return;
        }

        $campaign = WhatsappCampaign::find($campaignId);

        if (!$campaign) {
            return;
        }

        $stats = [
            'number_recipients' => $campaign->recipients()->count(),
        ];

        foreach (MetaTrackingEventTypeEnum::cases() as $status) {
            $stats['number_'.$status->value] = $this->countRecipientsWithStatus($campaign, $status->value);
        }

        // ponytail: number_clicked stays 0 until WhatsApp click tracking exists; it needs a
        // clicked_at on meta_chat_messages and a CLICKED case on MetaTrackingEventTypeEnum.

        /* updateOrCreate rather than update: a campaign whose stats row is missing would
           otherwise silently recount into nothing and keep reading as all zeroes. */
        $campaign->stats()->updateOrCreate([], $stats);
    }
}
