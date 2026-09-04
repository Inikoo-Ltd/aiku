<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Helpers;

use App\Models\Helpers\TicketComment;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TicketComment
 */
class TicketCommentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'body'        => $this->body,
            'is_internal' => $this->is_internal,
            'is_staff'    => $this->author_type === 'User',
            'author'      => $this->author?->contact_name ?: $this->author?->username,
            'created_at'  => $this->created_at,
            'images'      => $this->ticketImageSources(),
        ];
    }
}
