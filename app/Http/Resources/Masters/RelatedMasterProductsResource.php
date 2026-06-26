<?php

/*
 * author Arya Permana - Kirin
 * created on 15-10-2024-11h-52m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Http\Resources\Masters;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $code
 * @property string $slug
 * @property string $name*@property mixed $id
 * @property mixed $web_images
 * @property mixed $position
 */
class RelatedMasterProductsResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'slug'            => $this->slug,
            'code'            => $this->code,
            'name'            => $this->name,
            'image_thumbnail' => is_array($this->web_images) ? $this->web_images : json_decode($this->web_images),
            'position'        => $this->position
        ];
    }

}
