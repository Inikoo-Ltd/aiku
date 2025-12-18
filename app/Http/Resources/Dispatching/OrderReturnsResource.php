<?php

/*
 * Author: Oggie Sutrisna
 * Created: Wed, 18 Dec 2025 13:50:00 Makassar Time
 * Description: Resource for OrderReturn model
 */

namespace App\Http\Resources\Dispatching;

use App\Enums\Dispatching\Return\ReturnStateEnum;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $slug
 * @property string $reference
 * @property \App\Enums\Dispatching\Return\ReturnStateEnum $state
 * @property \Illuminate\Support\Carbon $date
 * @property int $number_items
 * @property string|null $customer_name
 * @property string|null $customer_slug
 */
class OrderReturnsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'slug'          => $this->slug,
            'reference'     => $this->reference,
            'state'         => $this->state instanceof \App\Enums\Dispatching\Return\ReturnStateEnum
                ? $this->state->value
                : $this->state,
            'state_icon'    => $this->state instanceof \App\Enums\Dispatching\Return\ReturnStateEnum
                ? (ReturnStateEnum::stateIcon()[$this->state->value] ?? null)
                : (ReturnStateEnum::stateIcon()[$this->state] ?? null),
            'date'          => $this->date,
            'number_items'  => $this->number_items,
            'customer_name' => $this->customer_name ?? null,
            'customer_slug' => $this->customer_slug ?? null,
        ];
    }
}
