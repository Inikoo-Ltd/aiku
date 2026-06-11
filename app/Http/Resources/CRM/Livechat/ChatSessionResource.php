<?php

namespace App\Http\Resources\CRM\Livechat;

use App\Http\Resources\HasSelfCall;
use Illuminate\Support\Arr;
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
    public function toArray($request): array
    {
        $chatSession = $this;

        $webUser = $chatSession->relationLoaded('webUser') ? $chatSession->webUser : null;
        $contactName = $webUser?->customer?->contact_name
            ?? $webUser?->username
            ?? $chatSession->guest_identifier
            ?? 'Guest';

        $activeAssignment = null;
        if ($chatSession->relationLoaded('assignments')) {
            $activeAssignment = $chatSession->assignments
                ->sortByDesc('assigned_at')
                ->first();
        }

        return [
            'ulid'          => $chatSession->ulid,
            'status'        => $chatSession->status->value,
            'is_guest'      => is_null($chatSession->web_user_id),
            'guest_identifier' => $chatSession->guest_identifier,
            'created_at'    => $this->formatDate($chatSession->created_at),
            'closed_at'     => $chatSession->closed_at ? $this->formatDate($chatSession->closed_at) : null,
            'priority'      => $chatSession->priority->value,
            'shop_id'       => $chatSession->shop_id,
            'shop_name'     => $chatSession->relationLoaded('shop') && $chatSession->shop ? $chatSession->shop->name : null,
            'contact_name'  => $contactName,
            'assigned_agent' => $activeAssignment?->chatAgent?->user?->contact_name,
            'ai_summary'    => Arr::get($chatSession->metadata ?? [], 'ai_summary'),
            'route'         => [
                'name'       => 'grp.org.shops.show.crm.chat_sessions.show',
                'parameters' => [
                    'organisation' => request()->route()?->originalParameters()['organisation'] ?? null,
                    'shop'         => request()->route()?->originalParameters()['shop'] ?? null,
                    'chatSession'  => $chatSession->id,
                ],
            ],
            'conversation_route' => [
                'name'       => 'grp.org.chat.conversations.detail',
                'parameters' => [
                    'organisation' => request()->route()?->originalParameters()['organisation'] ?? null,
                    'chatSession'  => $chatSession->id,
                ],
            ],
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
