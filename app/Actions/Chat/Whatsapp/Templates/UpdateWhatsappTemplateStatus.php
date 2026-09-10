<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\Whatsapp\Templates;

use App\Models\Chat\MetaMessageTemplate;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Meta reviews templates asynchronously and reports the verdict on the
 * `message_template_status_update` webhook. Without this the approval never reaches
 * Aiku and an agent has no way of knowing why a template cannot be used.
 */
class UpdateWhatsappTemplateStatus
{
    use AsAction;

    public string $jobQueue = 'urgent';

    /**
     * @param  array<string, mixed>  $value  The `changes[].value` node of the webhook
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
        $templateId = (string) Arr::get($value, 'message_template_id');
        $status     = (string) Arr::get($value, 'event');

        if ($templateId === '' || $status === '') {
            return;
        }

        $metaMessageTemplate = MetaMessageTemplate::where('template_id', $templateId)->first();

        if (!$metaMessageTemplate) {
            Log::info('WhatsApp template status for unknown template', [
                'message_template_id' => $templateId,
                'event'               => $status,
            ]);

            return;
        }

        $data = $metaMessageTemplate->data ?? [];

        $data['status']           = $status;
        $data['rejected_reason']  = Arr::get($value, 'reason');
        $data['status_update_at'] = now()->toISOString();

        $metaMessageTemplate->update([
            'status'   => $status,
            'category' => Arr::get($value, 'message_template_category', $metaMessageTemplate->category),
            'data'     => $data,
        ]);
    }
}
