<?php

/*
 * author Louis Perez
 * created on 09-06-2026-14h-03m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Http\Resources\Web;

use App\Http\Resources\HasSelfCall;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Http\Resources\Json\JsonResource;

class WebBlockDepartmentResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var ProductCategory $department */
        $department = $this->resource;

        return [
            'id'                        => $department->id,
            'slug'                      => $department->slug,
            'code'                      => $department->code,
            'name'                      => $department->name,
            'description'               => $department->description,
            'description_title'         => $department->description_title,
            'description_extra'         => $department->description_extra,
            'showcase_image'            => $department->imageSources(720, 480, 'showcase_image'),
            'showcase_video'            => $department->desc_video_url,
            'offers_data'               => $department->offers_data,
            'web_images'                => $department->web_images,
            'url'                       => $department->webpage->url,
        ];
    }
}
