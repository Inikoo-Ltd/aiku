<?php

/*
 * author Arya Permana - Kirin
 * created on 10-06-2025-15h-24m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Web;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $slug
 * @property mixed $code
 * @property mixed $name
 * @property mixed $canonical_url
 * @property mixed $web_images
 * @property mixed $title
 */
class WebBlockCollectionResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        $webImages = json_decode(trim($this->web_images, '"'), true) ?? [];

        return [
            'code'       => $this->code,
            'name'       => $this->name,
            'title'      => $this->title ?? $this->name ?? $this->code,
            'url'        => $this->canonical_url,
            'web_images' => $webImages
        ];
    }
}
