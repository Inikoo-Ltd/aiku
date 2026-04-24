<?php

/*
 * Author: eka yudinata <ekayudintha@gmail.com>
 * Created: Thu, 24 Apr 2026
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Http\Resources\Catalogue;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * @property mixed $slug
 * @property mixed $code
 * @property mixed $name
 * @property mixed $description
 * @property mixed $web_images
 */
class BeefreeProductResource extends JsonResource
{
    public function toArray($request): array
    {
        // Handle web_images JSON decoding (same as ProductsWebpageResource)
        if (!is_array($this->web_images)) {
            $webImages = json_decode(trim($this->web_images, '"'), true) ?? [];
        } else {
            $webImages = $this->web_images;
        }

        $productImage = Arr::get($this?->imageSources(200, 200), 'png', '');

        return [
            'id'            => $this->id,
            'slug'          => $this->slug,
            'code'          => $this->code,
            'name'          => $this->name,
            'description'   => $this->description,
            'web_images'    => $webImages,
            'product_image' => $productImage,
            'url'           => $this->webpage?->getCanonicalUrl(),
        ];
    }
}
