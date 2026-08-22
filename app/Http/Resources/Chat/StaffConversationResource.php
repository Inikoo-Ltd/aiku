<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 22 Aug 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Chat;

use App\Models\Chat\StaffConversation;
use App\Models\SysAdmin\User;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StaffConversation
 */
class StaffConversationResource extends JsonResource
{
    public function toArray($request): array
    {
        $participants = $this->participants->map(fn (User $user) => [
            'id'     => $user->id,
            'name'   => $user->contact_name ?: $user->username,
            'avatar' => $user->image_id ? $user->imageSources(0, 48) : null,
        ])->values();

        return [
            'ulid'            => $this->ulid,
            'type'            => $this->type,
            'name'            => $this->name,
            'context_type'    => $this->context_type,
            'context_id'      => $this->context_id,
            'participants'    => $participants,
            'last_message_at' => $this->last_message_at,
            'last_message'    => $this->last_message_body ?? null,
            'unread_count'    => (int) ($this->unread_count ?? 0),
        ];
    }
}
