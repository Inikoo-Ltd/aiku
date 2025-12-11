<?php

namespace App\Http\Resources\CRM\Livechat;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatSessionResource extends JsonResource
{
    use HasSelfCall;
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $chatSession = $this;

        return [
            'ulid' => $chatSession->ulid,
            'status' => $chatSession->status->value,
            'is_guest' => is_null($chatSession->web_user_id),
            'guest_identifier' => $chatSession->guest_identifier,
            'created_at' => $this->formatDate($chatSession->created_at),
            'closed_at' => $chatSession->closed_at ? $this->formatDate($chatSession->closed_at) : null,
            'priority' => $chatSession->priority->value,
            'contact_name' => $chatSession->webUser ? $chatSession->webUser->contact_name : null,
        ];
    }

    protected function formatDate($date): array
    {
        return [
            'raw' => $date,
            'formatted' => $date->format('M j, Y H:i'),
            'diff' => $date->diffForHumans()
        ];
    }
}
