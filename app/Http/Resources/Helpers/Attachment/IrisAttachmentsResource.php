<?php

/*
 * author Arya Permana - Kirin
 * created on 17-10-2024-13h-30m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Http\Resources\Helpers\Attachment;

use Illuminate\Http\Resources\Json\JsonResource;

class IrisAttachmentsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'caption'    => $this->caption,
            'scope'      => $this->scope,
            'media_id'   => $this->media_id,
            'media_ulid'   => $this->media_ulid,
            'download_route' => [
                'name' => 'iris.iris_attachment',
                'parameters' => ['media' => $this->media_ulid],
                'method' => 'get'
            ]

        ];
    }
}
