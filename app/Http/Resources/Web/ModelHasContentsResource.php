<?php

/*
 * author Arya Permana - Kirin
 * created on 02-06-2025-10h-23m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Web;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Web\ModelHasContent;

class ModelHasContentsResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var ModelHasContent $content */
        $content = $this;
        return [
            'id'            => $content->id,
            'type'          => $content->type,
            'title'         => $content->title,
            'text'          => $content->text,
            'position'      => $content->position,
            'image'         => $content->imageSources(64, 64)
        ];
    }
}
