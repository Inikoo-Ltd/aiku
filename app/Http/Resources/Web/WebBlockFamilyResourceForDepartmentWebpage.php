<?php

/*
 * author Louis Perez
 * created on 06-06-2026-14h-51m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Http\Resources\Web;

use App\Http\Resources\HasSelfCall;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class WebBlockFamilyResourceForDepartmentWebpage extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var ProductCategory $family */
        $family = $this->resource;

        return [
            'slug'                      => $family->slug,
            'name'                      => $family->name,
            'url'                       => $family->webpage->url,
            'image'                     => Arr::get($family->web_images, 'main.original'),
            'web_images'                => $family->web_images,
        ];
    }
}
