<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 23 Aug 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\SysAdmin;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $username
 * @property int $messages
 * @property int $conversations
 * @property int $media_messages
 * @property int $reactions_given
 * @property string|null $last_message_at
 */
class StaffChatUsersResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'username'        => $this->username,
            'messages'        => (int) $this->messages,
            'conversations'   => (int) $this->conversations,
            'media_messages'  => (int) $this->media_messages,
            'reactions_given' => (int) $this->reactions_given,
            'last_message_at' => $this->last_message_at,
        ];
    }
}
