<?php

/*
 *  Author: Jonathan lopez <raul@inikoo.com>
 *  Created: Sat, 22 Oct 2022 18:53:15 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, inikoo
 */

namespace App\Http\Resources\Catalogue;

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
 *
 */
class FamiliesResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                 => $this->id,
            'slug'               => $this->slug,
            'shop_slug'          => $this->shop_slug,
            'shop_code'          => $this->shop_code,
            'shop_name'          => $this->shop_name,
            'department_slug'    => $this->department_slug,
            'department_code'    => $this->department_code,
            'department_name'    => $this->department_name,
            'state'              => [
                'tooltip' => $this->state->labels()[$this->state->value],
                'icon'    => $this->state->stateIcon()[$this->state->value]['icon'],
                'class'   => $this->state->stateIcon()[$this->state->value]['class']
            ],
            'code'                     => $this->code,
            'name'                     => $this->name,
            'description'              => $this->description,
            'created_at'               => $this->created_at,
            'updated_at'               => $this->updated_at,
            'number_current_products'  => $this->number_current_products,
            'sales'                    => $this->sales_all,
            'invoices'                 => $this->invoices_all,
            'organisation_name' => $this->organisation_name,
            'organisation_slug' => $this->organisation_slug,
        ];
    }
}
