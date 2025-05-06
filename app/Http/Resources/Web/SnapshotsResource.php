<?php
/*
 * author Arya Permana - Kirin
 * created on 06-05-2025-08h-45m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Web;

use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

class SnapshotsResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            "id" => $this->id,
            'scope' => $this->scope,
            'parent_type' => $this->parent_type,
            'state' => $this->state,
            'builder' => $this->builder,
            'layout' => $this->layout,
            'published_at' => $this->published_at,
            'published_until' => $this->published_until,
            'recyclable' => $this->recyclable,
            'comment' => $this->comment,
            'publisher' => $this->publisher->contact_name ?? null,

        ];
    }
}
