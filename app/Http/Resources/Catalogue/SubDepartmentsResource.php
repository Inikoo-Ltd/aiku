<?php

/*
 *  Author: Jonathan lopez <raul@inikoo.com>
 *  Created: Sat, 22 Oct 2022 18:53:15 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, inikoo
 */

namespace App\Http\Resources\Catalogue;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

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
 * @property int $number_current_families
 * @property mixed $number_families
 * @property mixed $number_products
 * @property mixed $id
 * @property mixed $master_product_category_id
 * @property mixed $organisation_name
 * @property mixed $organisation_code
 * @property mixed $organisation_slug
 * @property mixed $is_name_reviewed
 * @property mixed $is_description_title_reviewed
 * @property mixed $is_description_extra_reviewed
 * @property mixed $is_description_reviewed
 * @property mixed $web_images
 */
class SubDepartmentsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                            => $this->id,
            'name'                          => $this->name,
            'slug'                          => $this->slug,
            'shop_slug'                     => $this->shop_slug,
            'shop_code'                     => $this->shop_code,
            'shop_name'                     => $this->shop_name,
            'department_slug'               => $this->department_slug,
            'master_product_category_id'    => $this->master_product_category_id,
            'department_code'               => $this->department_code,
            'department_name'               => $this->department_name,
            'organisation_name'             => $this->organisation_name,
            'organisation_code'             => $this->organisation_code,
            'organisation_slug'             => $this->organisation_slug,
            'image'                         => Arr::get($this->web_images, 'main.gallery'),
            'state'                         => [
                'label'   => $this->state->labels()[$this->state->value],
                'tooltip' => $this->state->labels()[$this->state->value],
                'icon'    => $this->state->stateIcon()[$this->state->value]['icon'],
                'class'   => $this->state->stateIcon()[$this->state->value]['class']
            ],
            'code'                          => $this->code,
            'description'                   => $this->description,
            'created_at'                    => $this->created_at,
            'updated_at'                    => $this->updated_at,
            'number_families'               => $this->number_families,
            'number_products'               => $this->number_products,
            'is_name_reviewed'              => $this->is_name_reviewed,
            'is_description_title_reviewed' => $this->is_description_title_reviewed,
            'is_description_reviewed'       => $this->is_description_reviewed,
            'is_description_extra_reviewed' => $this->is_description_extra_reviewed,
            'image_thumbnail'               => Arr::get($this->web_images, 'main.thumbnail'),

        ];
    }
}
