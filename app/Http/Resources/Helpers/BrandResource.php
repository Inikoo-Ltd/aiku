<?php

namespace App\Http\Resources\Helpers;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $slug
 * @property mixed $name
 * @property mixed $id
 */
class BrandResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'slug'          => $this->slug,
            'name'          => $this->name,
            'id'            => $this->id,
        ];
    }
}
