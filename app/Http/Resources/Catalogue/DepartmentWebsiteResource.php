<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 Jul 2024 16:43:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Catalogue;

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
 */
class DepartmentWebsiteResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'slug'               => $this->slug,
            'shop_slug'          => $this->shop->slug,
            'shop_code'          => $this->shop->code,
            'shop_name'          => $this->shop->name,
            'code'               => $this->code,
            'name'               => $this->name,
            'state'              => [
                'label' => $this->state->labels()[$this->state->value],
                'icon'  => $this->state->stateIcon()[$this->state->value]['icon'],
                'class' => $this->state->stateIcon()[$this->state->value]['class']
            ],
            'description'       => $this->description,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
            'follow_master'    =>  $this->follow_master
            // 'current_families'  => $this->stats->number_families ?? 0,
            // 'current_products'  => $this->stats->number_products ?? 0,
        ];
    }
}
