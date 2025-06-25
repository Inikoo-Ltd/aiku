<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Jun 2025 16:43:21 Malaysia Time, Sheffield, United Kingdom
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Catalogue;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Helpers\Media;
use App\Actions\Helpers\Images\GetPictureSources;


/**
 * @property mixed $id
 * @property mixed $slug
 * @property mixed $code
 * @property mixed $name
 * @property mixed $state
 * @property mixed $number_current_products
 */
class FamiliesSelectResource extends JsonResource
{

    public function toArray($request): array
    {



        return [
            'id'                 => $this->id,
            'slug'               => $this->slug,
            'code'                     => $this->code,
            'name'                     => $this->name,
            'state'              => [
                'tooltip' => $this->state->labels()[$this->state->value],
                'icon'    => $this->state->stateIcon()[$this->state->value]['icon'],
                'class'   => $this->state->stateIcon()[$this->state->value]['class']
            ],
            'number_current_products'  => $this->number_current_products,

        ];
    }
}
