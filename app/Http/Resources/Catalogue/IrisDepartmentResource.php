<?php

/*
 *  Author: Jonathan lopez <raul@inikoo.com>
 *  Created: Sat, 22 Oct 2022 18:53:15 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, inikoo
 */

namespace App\Http\Resources\Catalogue;

use App\Http\Resources\HasSelfCall;
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
 * @property mixed $sales_all
 * @property mixed $organisation_name
 * @property mixed $invoices_all
 * @property mixed $organisation_slug
 * @property mixed $id
 * @property mixed $organisation_code
 * @property mixed $number_current_sub_departments
 * @property mixed $number_current_collections
 * @property mixed $master_product_category_id
 * @property mixed $currency_code
 * @property mixed $is_name_reviewed
 * @property mixed $is_description_title_reviewed
 * @property mixed $is_description_reviewed
 * @property mixed $is_description_extra_reviewed
 * @property mixed $web_images
 */
class IrisDepartmentResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'id'                             => $this->id,
            'slug'                           => $this->slug,
            'code'                           => $this->code,
            'name'                           => $this->name,
            'state'                          => [
                'label' => $this->state->labels()[$this->state->value],
                'icon'  => $this->state->stateIcon()[$this->state->value]['icon'],
                'class' => $this->state->stateIcon()[$this->state->value]['class']
            ],
            'description'                    => $this->description,
            'created_at'                     => $this->created_at,
            'updated_at'                     => $this->updated_at,
            'number_current_families'        => $this->number_current_families,
            'number_current_products'        => $this->number_current_products,
            'number_current_sub_departments' => $this->number_current_sub_departments,
            'number_current_collections'     => $this->number_current_collections,
            'master_product_category_id'     => $this->master_product_category_id,
            'image_thumbnail'                => Arr::get($this->web_images, 'main.thumbnail'),
        ];
    }
}
