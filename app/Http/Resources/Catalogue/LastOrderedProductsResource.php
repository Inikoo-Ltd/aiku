<?php

/*
 * author Arya Permana - Kirin
 * created on 06-12-2024-11h-09m
 * GitHub: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Http\Resources\Catalogue;


use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * @property mixed $code
 * @property mixed $canonical_url
 * @property mixed $name
 * @property mixed $submitted_at
 * @property mixed $web_images
 */
class LastOrderedProductsResource extends JsonResource
{

    public function toArray($request): array
    {
        $webImages = json_decode($this->web_images, true);

        return [
            'code'          => $this->code,
            'canonical_url' => $this->canonical_url,
            'name'          => $this->name,
            'submitted_at'  => $this->submitted_at,
            'image'         => Arr::get($webImages, 'main.gallery'),

        ];
    }
}
