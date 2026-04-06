<?php

namespace App\Http\Resources\Helpers;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $slug
 * @property mixed $reference
 * @property mixed $name
 * @property mixed $id
 * @property mixed $number_models
 */
class BrandResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'slug'          => $this->slug,
            'reference'     => $this->reference,
            'name'          => $this->name,
            'id'            => $this->id,
            'number_models' => $this->number_models,
        ];
    }
}
