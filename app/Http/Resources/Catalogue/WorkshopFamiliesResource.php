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
 *
 */
class WorkshopFamiliesResource extends JsonResource
{
    public function toArray($request): array
    {
        $family = ProductCategory::find($this->id);
        return [
            'id'                 => $this->id,
            'slug'               => $this->slug,
            'image'              => $family->imageSources(720, 480),
            'code'                     => $this->code,
            'name'                     => $this->name,
            'description'              => $this->description,
            'description_title'        => $this->description_title,
            'description_extra'        => $this->description_extra,
            'created_at'               => $this->created_at,
            'updated_at'               => $this->updated_at,
        ];
    }
}
