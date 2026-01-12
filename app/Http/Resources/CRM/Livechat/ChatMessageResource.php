<?php

namespace App\Http\Resources\CRM\Livechat;

use App\Http\Resources\HasSelfCall;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatMessageResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        $chatMessage = $this;

        return [
            'id' => $chatMessage->id,
            'message_text' => $chatMessage->message_text,
            'original_text' => $chatMessage->original_text,
            'translations' => $chatMessage->translations->map(function ($translation) {
                return [
                    'translated_text' => $translation->translated_text,
                    'language_name'   => $translation->targetLanguage?->name,
                    'language_flag'   => $translation->targetLanguage?->flag ? asset('flags/' . $translation->targetLanguage->flag) : null,
                ];
            }),
            'message_type' => $chatMessage->message_type->value,
            'sender_type' => $chatMessage->sender_type->value,
            'is_agent' => $chatMessage->sender_type->value === ChatSenderTypeEnum::AGENT->value,
            'is_guest' => $chatMessage->sender_type->value === ChatSenderTypeEnum::GUEST->value,
            'is_user' => $chatMessage->sender_type->value === ChatSenderTypeEnum::USER->value,
            'is_system' => $chatMessage->sender_type->value === ChatSenderTypeEnum::SYSTEM->value,
            'is_ai' => $chatMessage->sender_type->value === ChatSenderTypeEnum::AI->value,
            'is_read' => $chatMessage->is_read,
            'media_url' => $chatMessage->imageSources(0, 0, 'attachment'),
            'original_url' => $chatMessage->attachment ? $chatMessage->attachment->getUrl() : null,
            'file_name' => $chatMessage->attachment ? $chatMessage->attachment->file_name : null,
            'file_size' => $chatMessage->attachment ? $chatMessage->attachment->size : null,
            'file_mime' => $chatMessage->attachment ? $chatMessage->attachment->mime_type : null,
            'download_route' => $chatMessage->attachment ? [
                'name'       => 'grp.api.chats.chat.attachment.download',
                'parameters' => [
                    'ulid' => $chatMessage->attachment->ulid,
                ],
                'method'     => 'get',
                'url'        => route('grp.api.chats.chat.attachment.download', ['ulid' => $chatMessage->attachment->ulid])
            ] : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'timestamp' => $chatMessage->created_at->timestamp
        ];
    }
}
