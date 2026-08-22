<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 22 Aug 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Chat;

use App\Models\Chat\StaffMessage;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StaffMessage
 */
class StaffMessageResource extends JsonResource
{
    public function toArray($request): array
    {
        $translations = [];
        foreach ($this->translations as $translation) {
            $translations[$translation->language_id] = $translation->body;
        }

        $reactions = [];
        foreach ($this->reactions as $reaction) {
            $reactions[$reaction->emoji][] = $reaction->user_id;
        }

        return [
            'id'                => $this->id,
            'conversation_ulid' => $this->conversation->ulid,
            'user_id'           => $this->user_id,
            'user_name'         => $this->user->contact_name ?: $this->user->username,
            'parent_id'         => $this->parent_id,
            'body'              => $this->body,
            'language_id'       => $this->language_id,
            'translations'      => $translations,
            'reactions'         => $reactions,
            'image'             => $this->media_id ? $this->imageSources(0, 0, 'attachment') : null,
            'gif_url'           => preg_match('#^https://media\.tenor\.com/\S+\.gif$#', (string) $this->body) ? $this->body : null,
            'created_at'        => $this->created_at,
        ];
    }
}
