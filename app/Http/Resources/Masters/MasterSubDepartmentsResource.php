<?php

/*
 * author Arya Permana - Kirin
 * created on 15-10-2024-15h-19m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Http\Resources\Masters;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * @property string $slug
 * @property string $shop_slug
 * @property string $department_slug
 * @property string $code
 * @property string $name
 * @property mixed $state
 * @property string $description
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property mixed $shop_code
 * @property mixed $shop_name
 * @property int $number_current_families
 * @property int $number_current_products
 * @property int $number_families
 * @property mixed $number_products
 * @property mixed $id
 * @property mixed $description_title
 * @property mixed $description_extra
 * @property mixed $web_images
 * @property mixed $status
 */
class MasterSubDepartmentsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'slug'              => $this->slug,
            'code'              => $this->code,
            'name'              => $this->name,
            'description'       => $this->description,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
            'number_families'   => $this->number_families,
            'number_products'   => $this->number_products,
            'description_title' => $this->description_title,
            'description_extra' => $this->description_extra,
            'image_thumbnail'   => Arr::get($this->web_images, 'main.thumbnail'),
            'status_icon'       => $this->status
                ? [
                    'tooltip' => __('Active'),
                    'icon'    => 'fas fa-check-circle',
                    'class'   => 'text-green-400'
                ]
                : [
                    'tooltip' => __('Closed'),
                    'icon'    => 'fas fa-times-circle',
                    'class'   => 'text-red-400'
                ],
        ];
    }
}
