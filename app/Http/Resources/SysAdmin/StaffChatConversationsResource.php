<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 23 Aug 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\SysAdmin;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $type
 * @property string|null $context
 * @property string|null $members
 * @property int $participants
 * @property int $messages
 * @property string|null $last_message_at
 * @property string $created_at
 */
class StaffChatConversationsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'type'            => $this->type,
            'context'         => $this->context,
            'members'         => $this->members,
            'participants'    => (int) $this->participants,
            'messages'        => (int) $this->messages,
            'last_message_at' => $this->last_message_at,
            'created_at'      => $this->created_at,
        ];
    }
}
