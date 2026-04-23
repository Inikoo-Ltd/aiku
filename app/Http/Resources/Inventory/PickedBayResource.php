<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Feb 2026 16:16:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $code
 * @property mixed $slug
 * @property mixed $current_delivery_note_id
 */
class PickedBayResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        /** @var PickedBay $product */
        $pickedBay = $this->resource;

        // Don't worry, won't run if relationship is not eager loaded
        $deliveryNotes = [];
        if ($pickedBay->relationLoaded('deliveryNotes')) {
            $deliveryNotes = $pickedBay->deliveryNotes->map(function ($deliveryNote) {
                $weight = $deliveryNote->estimated_weight ?? 0;
                if ($weight < 1000) {
                    $weight = $weight.' g';
                } elseif ($weight < 10000) {
                    $weight = round($weight / 1000, 1).' Kg';
                } else {
                    $weight = round($weight / 1000).' Kg';
                }
                return [
                    'id'            => $deliveryNote->id,
                    'slug'          => $deliveryNote->slug,
                    'date'          => $deliveryNote->date,
                    'weight'        => $weight,
                    'number_items'  => $deliveryNote->number_items,
                    'reference'     => $deliveryNote->reference,
                    'state'         => $deliveryNote->state->value,
                    'state_icon'    => $deliveryNote->state->stateIcon()[$deliveryNote->state->value],
                ];
            })->toArray();
        }

        return [
            'id'                       => $this->id,
            'code'                     => $this->code,
            'slug'                     => $this->slug,
            'delivery_notes'           => $deliveryNotes,
            'current_delivery_note_id' => $this->current_delivery_note_id,
        ];
    }
}
