<?php

/*
 * author Arya Permana - Kirin
 * created on 12-03-2025-11h-45m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Web;

use Illuminate\Http\Resources\Json\JsonResource;

class RedirectsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'url'           => $this->url,
            'path'          => $this->path,
            'type'          => $this->type,
            'webpage_title' => $this->webpage_title
        ];
    }
}
