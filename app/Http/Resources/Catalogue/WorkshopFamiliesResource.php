<?php

/*
 * author Arya Permana - Kirin
 * created on 03-06-2025-11h-50m
 * github: https://github.com/KirinZero0
 * copyright 2025
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
class WorkshopFamiliesResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var ProductCategory $department */
        $family = $this->resource;
        $webImages = [];

        if (is_string($family->web_images)) {
            $webImages = json_decode(trim($family->web_images, '"'), true) ?? [];
        } elseif (is_array($family->web_images)) {
            $webImages = $family->web_images;
        }
        return [
            'id' => $family->id,
            'slug' => $family->slug,
            'image' => $family->imageSources(720, 480),
            'web_images' => $webImages,
            'code' => $family->code,
            'name' => $family->name,
            'description' => $family->description,
            'description_title' => $family->description_title,
            'description_extra' => $family->description_extra,
            'created_at' => $family->created_at,
            'updated_at' => $family->updated_at,
            'tags'       => $family->tradeUnitFamily?->tags->take(3),
        ];
    }
}
