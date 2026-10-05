<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Catalogue;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * @property mixed $id
 * @property string $slug
 * @property string $code
 * @property string|null $name
 * @property mixed $web_images
 * @property int|null $website_position
 * @property mixed $subDepartment
 * @property mixed $stats
 * @property mixed $created_at
 * @property string|null $collection_names
 */
class FamilyWebsiteOrderResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'id'                  => $this->id,
            'slug'                => $this->slug,
            'code'                => $this->code,
            'name'                => $this->name,
            'image_thumbnail'     => Arr::get(is_array($this->web_images) ? $this->web_images : json_decode($this->web_images, true), 'main.thumbnail'),
            'position'            => $this->website_position,
            'sub_department_name' => $this->subDepartment?->name,
            'department_name'      => $this->relationLoaded('department') ? $this->department?->name : null,
            'collection_names'     => $this->collection_names,
            'created_at'          => $this->created_at,
            'number_products'     => (int) $this->stats?->number_current_products,
        ];
    }
}
