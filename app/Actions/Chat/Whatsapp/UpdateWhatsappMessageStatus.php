<?php

/*
 * Author: Eka Yudinata <ekayudinata@gmail.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Whatsapp;

use App\Events\BroadcastMetaChatMessageStatus;
use App\Models\Chat\MetaChatMessage;
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

        if (!$metaChatMessage) {
            Log::info('WhatsApp status for unknown message', [
                'meta_message_id' => $metaMessageId,
                'status'          => Arr::get($status, 'status'),
            ]);

            return;
        }

        $statusName = (string) Arr::get($status, 'status');
        $timestamp  = Arr::get($status, 'timestamp');
        $happenedAt = $timestamp ? Carbon::createFromTimestamp((int) $timestamp) : now();

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

        // ponytail: `conversation`, `pricing` and group recipient fields are dropped, nothing consumes them
        $modelData = ['metadata' => $metadata];

        if ($statusName === 'delivered' && !$metaChatMessage->delivered_at) {
            $modelData['delivered_at'] = $happenedAt;
        }

        if ($statusName === 'read' && !$metaChatMessage->read_at) {
            $modelData['read_at'] = $happenedAt;
            $modelData['is_read'] = true;
        }

        $metaChatMessage->update($modelData);

        BroadcastMetaChatMessageStatus::dispatch($metaChatMessage->fresh('metaChatSession'));
    }
}
