<?php

namespace App\Http\Resources\CRM\Livechat;

use Illuminate\Http\Resources\Json\JsonResource;

class ChatAgentResource extends JsonResource
{
    public function toArray($request): array
    {
        $isDeletedInOrg = (int) ($this->active_shca_count ?? 0) === 0;
        $orgSlug        = $this->organisation_slug;

        $data = [
            'id'                   => $this->id,
            'user_id'              => $this->user_id,
            'name'                 => $this->name,
            'shops'                => $this->shops,
            'is_online'            => $this->is_online,
            'is_available'         => $this->is_available,
            'current_chat_count'   => $this->current_chat_count,
            'max_concurrent_chats' => $this->max_concurrent_chats,
            'auto_accept'          => $this->auto_accept,
            'specialization'       => $this->specialization,
            'created_at'           => $this->created_at,
            'is_deleted_in_org'    => $isDeletedInOrg,
        ];

        if ($isDeletedInOrg) {
            $data['route_restore'] = [
                'name'       => 'grp.org.chat.agents.restore',
                'parameters' => [$orgSlug, $this->id],
            ];
            $data['route_force_delete'] = [
                'name'       => 'grp.org.chat.agents.force_delete',
                'parameters' => [$orgSlug, $this->id],
            ];
        } else {
            $data['route_edit'] = [
                'name'       => 'grp.org.chat.agents.edit',
                'parameters' => [$orgSlug, $this->id],
            ];
            $data['route_delete'] = [
                'name'       => 'grp.org.chat.agents.delete',
                'parameters' => [$orgSlug, $this->id],
            ];
        }

        return $data;
    }
}
