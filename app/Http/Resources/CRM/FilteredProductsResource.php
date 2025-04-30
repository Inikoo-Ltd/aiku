<?php

namespace App\Http\Resources\CRM;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $slug
 * @property string $code
 * @property string $name*@property mixed $id
 * @property mixed $media
 * @property mixed $price
 * @property mixed $id
 */
class FilteredProductsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'    => $this->id,
            'slug'  => $this->slug,
            'code'  => $this->code,
            'image' => $this->media,
            'price' => $this->price,
            'name'  => $this->name,
        ];
    }
}
