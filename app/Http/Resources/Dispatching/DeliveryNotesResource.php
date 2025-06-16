<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 22 Feb 2023 22:40:34 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dispatching;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $slug
 * @property string $code
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property string $name
 * @property mixed $state
 * @property string $shop_slug
 * @property string $date
 * @property string $reference
 * @property mixed $id
 * @property mixed $weight
 * @property mixed $customer_slug
 * @property mixed $customer_name
 * @property mixed $number_items
 * @property mixed $organisation_name
 * @property mixed $organisation_slug
 * @property mixed $shop_name
 * @property mixed $type
 * @property mixed $estimated_weight
 * @property mixed $effective_weight
 *
 */
class DeliveryNotesResource extends JsonResource
{
    public static $wrap = null;

    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'slug'              => $this->slug,
            'reference'         => $this->reference,
            'date'              => $this->date,
            'state'             => $this->state,
            'state_icon'        => $this->state->stateIcon()[$this->state->value],
            'type'              => $this->type,
            'weight'            => $this->weight / 1000,
            'estimated_weight'  => $this->estimated_weight / 1000,
            'effective_weight'  => $this->effective_weight / 1000,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
            'shop_slug'         => $this->shop_slug,
            'customer_slug'     => $this->customer_slug,
            'customer_name'     => $this->customer_name,
            'number_items'      => $this->number_items,
            'organisation_name' => $this->organisation_name,
            'organisation_slug' => $this->organisation_slug,
            'shop_name'         => $this->shop_name,
            'employee_pick_route' => [
                'name' => 'grp.models.delivery_note.employee.pick',
                'parameters' => [
                    'deliveryNote' => $this->id
                ],
                'method' => 'patch'
            ]
        ];
    }
}
