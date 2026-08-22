<?php

/*
 * Author: Eka Yudinata <ekayudinata@gmail.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Whatsapp;

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

        $metadata = array_merge($metaChatMessage->metadata ?? [], ['wa_status' => $statusName]);

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
    }
}
