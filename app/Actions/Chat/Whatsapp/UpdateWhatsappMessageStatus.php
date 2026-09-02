<?php

/*
 * Author: Eka Yudinata <ekayudinata@gmail.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Whatsapp;

use App\Actions\Comms\WhatsappCampaign\Hydrators\WhatsappCampaignHydrateStats;
use App\Enums\CRM\Livechat\MetaTrackingEventTypeEnum;
use App\Events\BroadcastMetaChatMessageStatus;
use App\Models\Chat\MetaChatMessage;
use App\Models\Comms\WhatsappRecipient;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateWhatsappMessageStatus
{
    use AsAction;

    // TODO: make sure which queue is the best for this job, because this job is very important and urgent
    public string $jobQueue = 'urgent';

    /**
     * @param  array<string, mixed>  $value  The `changes[].value` node of a WhatsApp webhook
     */
    public function asJob(array $value): void
    {
        $this->handle($value);
    }

    /**
     * @param  array<string, mixed>  $value
     */
    public function handle(array $value): void
    {
        foreach (Arr::get($value, 'statuses', []) as $status) {
            $this->updateStatus($status);
        }
    }

    /**
     * Meta can deliver the lifecycle out of order (a `delivered` callback arriving
     * after `read` is normal), so a status never walks backwards.
     */
    protected const STATUS_RANK = [
        'sent'      => 1,
        'delivered' => 2,
        'read'      => 3,
        'failed'    => 4,
    ];

    /**
     * @param  array<string, mixed>  $status
     */
    protected function updateStatus(array $status): void
    {
        $metaMessageId = (string) Arr::get($status, 'id');

        if ($metaMessageId === '') {
            return;
        }

        $metaChatMessage = MetaChatMessage::where('meta_message_id', $metaMessageId)->first();

        $statusName = (string) Arr::get($status, 'status');
        $timestamp  = Arr::get($status, 'timestamp');
        $happenedAt = $timestamp ? Carbon::createFromTimestamp((int) $timestamp) : now();
        $trackedType = MetaTrackingEventTypeEnum::tryFrom($statusName);

        if (!$metaChatMessage) {
            Log::info('WhatsApp status for unknown message', [
                'meta_message_id' => $metaMessageId,
                'status'          => $statusName,
            ]);

            /* The message is not ours to update, but the status still happened; the wamid on
               the row is what lets it be reconciled if the message turns up later. */
            if ($trackedType) {
                StoreMetaTrackingEvent::run($trackedType, null, $metaMessageId, $status, $happenedAt);
            }

            return;
        }

        $metadata     = $metaChatMessage->metadata ?? [];
        $currentRank  = self::STATUS_RANK[Arr::get($metadata, 'wa_status')] ?? 0;
        $incomingRank = self::STATUS_RANK[$statusName] ?? 0;

        if ($incomingRank >= $currentRank) {
            $metadata['wa_status'] = $statusName;
        }

        $metadata['wa_status_at'] = array_merge(
            (array) (Arr::get($metadata, 'wa_status_at') ?? []),
            [$statusName => $happenedAt->toISOString()]
        );

        if ($statusName === 'failed') {
            $metadata['wa_error'] = Arr::get($status, 'errors.0');
        }

        /* metadata keeps only what the live UI reads; `conversation`, `pricing` and the group
           recipient fields are not copied here because the tracking event below stores the
           whole status node verbatim. */
        $modelData = ['metadata' => $metadata];

        if ($statusName === 'delivered' && !$metaChatMessage->delivered_at) {
            $modelData['delivered_at'] = $happenedAt;
        }

        if ($statusName === 'read' && !$metaChatMessage->read_at) {
            $modelData['read_at'] = $happenedAt;
            $modelData['is_read'] = true;
        }

        $metaChatMessage->update($modelData);

        $this->hydrateCampaignStats($metaChatMessage);

        if ($trackedType) {
            StoreMetaTrackingEvent::run($trackedType, $metaChatMessage, $metaMessageId, $status, $happenedAt);
        }

        BroadcastMetaChatMessageStatus::dispatch($metaChatMessage->fresh('metaChatSession'));
    }

    /**
     * Campaign counters are recomputed, not incremented, so a burst of callbacks for the
     * same campaign collapses into one recount: this action runs on the urgent queue once
     * per message, and the hydrator is ShouldBeUnique on the analytics queue.
     */
    protected function hydrateCampaignStats(MetaChatMessage $metaChatMessage): void
    {
        $campaignId = Arr::get($metaChatMessage->metadata ?? [], 'whatsapp_campaign_id')
            ?: WhatsappRecipient::where('meta_chat_message_id', $metaChatMessage->id)->value('whatsapp_campaign_id');

        if (!$campaignId) {
            return;
        }

        WhatsappCampaignHydrateStats::dispatch((int) $campaignId)->delay(now()->addSeconds(5));
    }
}
