<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 02 Sep 2026 21:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\HumanResources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $channel
 * @property string|null $text
 * @property string|null $conversation
 * @property string|null $conversation_ulid
 * @property string $created_at
 */
class EmployeeChatMessagesResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'channel'           => $this->channel,
            'text'              => $this->text,
            'conversation'      => $this->conversation,
            'conversation_ulid' => $this->conversation_ulid,
            'created_at'        => $this->created_at,
        ];
    }
}
