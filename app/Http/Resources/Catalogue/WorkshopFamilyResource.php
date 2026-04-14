<?php

/*
 * author Louis Perez
 * created on 13-04-2026-13h-39m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Http\Resources\Catalogue;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Catalogue\ProductCategory;

/**
 * @property string $slug
 * @property string $shop_slug
 * @property string $department_slug
 * @property mixed $state
 * @property string $code
 * @property string $name
 * @property string $description
 * @property string $description_extra
 * @property string $description_title
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property mixed $shop_code
 * @property mixed $shop_name
 * @property mixed $department_code
 * @property mixed $department_name
 *
 */
class WorkshopFamilyResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var ProductCategory $family */
        $family = $this->resource;
        $webImages = [];

        if (is_string($family->web_images)) {
            $webImages = json_decode(trim($family->web_images, '"'), true) ?? [];
        } elseif (is_array($family->web_images)) {
            $webImages = $family->web_images;
        }

        return [
            'id'                 => $family->id,
            'name'               => $family->name,
            'slug'               => $family->slug,
            'image'              => $family->imageSources(720, 480),
            'code'              => $family->code,
            'web_images'        => $webImages,
            'description'       => $family->description,
            'description_extra' => $family->description_extra,
            'description_title' => $family->description_title,
            'created_at'        => $family->created_at,
            'updated_at'        => $family->updated_at,
        ];
    }
}
