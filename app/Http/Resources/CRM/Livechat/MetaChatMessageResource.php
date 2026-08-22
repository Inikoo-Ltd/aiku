<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\CRM\Livechat;

use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

class MetaChatMessageResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        $metaChatMessage = $this;

        return [
            'id'             => $metaChatMessage->id,
            'message_text'   => $metaChatMessage->message_text,
            'message_type'   => $metaChatMessage->message_type->value,
            'sender_type'    => $metaChatMessage->sender_type->value,
            'is_agent'       => $metaChatMessage->sender_type->value === ChatSenderTypeEnum::AGENT->value,
            'is_read'        => $metaChatMessage->is_read,
            'media_url'      => $metaChatMessage->imageSources(0, 0, 'attachment'),
            'original_url'   => $metaChatMessage->attachment?->getUrl(),
            'file_name'      => $metaChatMessage->attachment?->file_name,
            'file_size'      => $metaChatMessage->attachment?->size,
            'file_mime'      => $metaChatMessage->attachment?->mime_type,
            'download_route' => $metaChatMessage->attachment ? [
                'name'       => 'grp.api.chats.chat.attachment.download',
                'parameters' => [
                    'ulid' => $metaChatMessage->attachment->ulid,
                ],
                'method'     => 'get',
                'url'        => route('grp.api.chats.chat.attachment.download', ['ulid' => $metaChatMessage->attachment->ulid])
            ] : null,
            'metadata'       => $metaChatMessage->metadata,
            'created_at'     => $metaChatMessage->created_at,
            'timestamp'      => $metaChatMessage->created_at->timestamp,
        ];
    }
}
