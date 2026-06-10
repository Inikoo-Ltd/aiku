<?php

/*
 * author Louis Perez
 * created on 09-06-2026-11h-12m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Http\Resources\Catalogue;

use App\Models\Catalogue\ProductCategory;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $slug
 * @property string $shop_slug
 * @property string $department_slug
 * @property mixed $state
 * @property string $code
 * @property string $name
 * @property string $description
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property mixed $shop_code
 * @property mixed $shop_name
 * @property mixed $department_code
 * @property mixed $department_name
 * @property int $number_current_products
 * @property string $description_title
 * @property string $description_extra
 * @property string $web_images
 */
class WorkshopDepartmentsResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var ProductCategory $department */
        $department = $this->resource;
        $webImages = [];

        if (is_string($this->web_images)) {
            $webImages = json_decode(trim($this->web_images, '"'), true) ?? [];
        } elseif (is_array($this->web_images)) {
            $webImages = $this->web_images;
        }
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'showcase_image' => $department->imageSources(720, 480, 'showcase_image'),
            'showcase_video' => $department->desc_video_url,
            'web_images' => $webImages,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'description_title' => $this->description_title,
            'description_extra' => $this->description_extra,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
