<?php

namespace App\Http\Resources\CRM\Livechat;

use Illuminate\Http\Resources\Json\JsonResource;

class ChatAgentResource extends JsonResource
{
    public function toArray($request): array
    {
        $isDeletedInOrg = $this->deleted_at !== null;
        $orgSlug        = $this->organisation_slug;

        $data = [
            'id'                   => $this->id,
            'user_id'              => $this->user_id,
            'name'                 => $this->name,
            'shops'                => $this->shops,
            'is_online'            => $this->isConnected(),
            'is_available'         => $this->is_available,
            'presence_status'      => $this->presenceStatus()->value,
            'last_heartbeat_at'    => $this->last_heartbeat_at,
            'current_chat_count'   => $this->current_chat_count,
            'max_concurrent_chats' => $this->max_concurrent_chats,
            'auto_accept'          => $this->auto_accept,
            'specialization'       => $this->specialization,
            'signature'            => $this->signature,
            'created_at'           => $this->created_at,
            'is_deleted_in_org'    => $isDeletedInOrg,
        ];

        if (!$isDeletedInOrg) {
            $data['route_edit'] = [
                'name'       => 'grp.org.chat.agents.edit',
                'parameters' => [$orgSlug, $this->id],
            ];
        }

        return $data;
    }
}
