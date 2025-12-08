<?php

/*
 * author Arya Permana - Kirin
 * created on 17-10-2024-13h-30m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Http\Resources\Helpers\Attachment;

use Illuminate\Http\Resources\Json\JsonResource;

class AttachmentsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'caption' => $this->caption,
            'scope' => $this->scope,
            'media_id' => $this->media_id,
            'media_ulid' => $this->media_ulid,
            'is_can_deleted' => match (request()->routeIs('grp.*')) {  // TODO: change to depends on the owner, if owner then can delete is true
                true => true,
                default => false
            },

        ];
    }
}
