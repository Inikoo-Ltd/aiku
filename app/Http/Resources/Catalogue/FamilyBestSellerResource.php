<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Catalogue;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property string $slug
 * @property string $code
 * @property string $name
 * @property mixed $web_images
 * @property mixed $price
 * @property mixed $sales
 */
class FamilyBestSellerResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'slug'            => $this->slug,
            'code'            => $this->code,
            'name'            => $this->name,
            'image_thumbnail' => is_array($this->web_images) ? $this->web_images : json_decode($this->web_images, true),
            'price'           => (float) $this->price,
            'sales'           => (float) $this->sales,
        ];
    }
}
