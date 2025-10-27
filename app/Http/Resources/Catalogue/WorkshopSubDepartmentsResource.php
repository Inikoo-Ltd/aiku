<?php

/*
 * author Arya Permana - Kirin
 * created on 03-06-2025-11h-46m
 * github: https://github.com/KirinZero0
 * copyright 2025
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
class WorkshopSubDepartmentsResource extends JsonResource
{
    public function toArray($request): array
    {
        $subDepartment = ProductCategory::find($this->id);
        $webImages = [];

        if (is_string($this->web_images)) {
            $webImages = json_decode(trim($this->web_images, '"'), true) ?? [];
        } elseif (is_array($this->web_images)) {
            $webImages = $this->web_images;
        }
        return [
            'id'                 => $this->id,
            'name'               => $this->name,
            'slug'               => $this->slug,
            'image'              => $subDepartment->imageSources(720, 480),
            'code'              => $this->code,
            'web_images'        => $webImages,
            'description'       => $this->description,
            'description_extra' => $this->description_extra,
            'description_title' => $this->description_title,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
            'families_route'    => [
                'name' => 'grp.json.workshop.families.index',
                'parameters' => [
                    'subDepartment' => $this->slug
                ]
            ],
            'collections_route'      => [
                'name' => 'grp.json.product_category.collections.index',
                'parameters' => [
                    'productCategory'   => $this->slug
                ]
            ]
        ];
    }
}
