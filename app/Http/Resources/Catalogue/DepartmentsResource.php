<?php

/*
 *  Author: Jonathan lopez <raul@inikoo.com>
 *  Created: Sat, 22 Oct 2022 18:53:15 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, inikoo
 */

namespace App\Http\Resources\Catalogue;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

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
 */
class DepartmentsResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'id'                             => $this->id,
            'slug'                           => $this->slug,
            'shop_slug'                      => $this->shop_slug,
            'shop_code'                      => $this->shop_code,
            'shop_name'                      => $this->shop_name,
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
            'sales'                          => $this->sales_all,
            'invoices'                       => $this->invoices_all,
            'organisation_name'              => $this->organisation_name,
            'organisation_code'              => $this->organisation_code,
            'organisation_slug'              => $this->organisation_slug,
        ];
    }
}
