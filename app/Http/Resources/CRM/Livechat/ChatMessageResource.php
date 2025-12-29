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
            'message_type' => $chatMessage->message_type->value,
            'sender_type' => $chatMessage->sender_type->value,
            'is_agent' => $chatMessage->sender_type->value === ChatSenderTypeEnum::AGENT->value,
            'is_guest' => $chatMessage->sender_type->value === ChatSenderTypeEnum::GUEST->value,
            'is_user' => $chatMessage->sender_type->value === ChatSenderTypeEnum::USER->value,
            'is_system' => $chatMessage->sender_type->value === ChatSenderTypeEnum::SYSTEM->value,
            'is_ai' => $chatMessage->sender_type->value === ChatSenderTypeEnum::AI->value,
            'sender_name' => $this->getSenderName($chatMessage),
            'is_read' => $chatMessage->is_read,
            'media_url' => $chatMessage->media?->getUrl(),
            'media_type' => $chatMessage->media?->mime_type,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'timestamp' => $chatMessage->created_at->timestamp
        ];
    }

    protected function getSenderName($chatMessage): ?string
    {
        switch ($chatMessage->sender_type) {
            case ChatSenderTypeEnum::AGENT:
                return $chatMessage->agent?->name;
            case ChatSenderTypeEnum::USER:
                return $chatMessage->user?->name;
            case ChatSenderTypeEnum::GUEST:
                return $chatMessage->chatSession->guest_identifier;
            case ChatSenderTypeEnum::SYSTEM:
                return 'System';
            case ChatSenderTypeEnum::AI:
                return 'AI Assistant';
            default:
                return null;
        }
    }
}
