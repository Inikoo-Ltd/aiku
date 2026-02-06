<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PickingTrolleyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'code'              => $this->code,
            'slug'              => $this->slug,
            'delivery_note'     => $this->delivery_note,  // TODO
            'warehouse_slug'    => $this->whenLoaded('warehouse', fn () => $this->warehouse->slug, $this->warehouse->slug ?? null),
            'organisation_slug' => $this->whenLoaded('organisation', fn () => $this->organisation->slug, $this->organisation->slug ?? null),
        ];
    }
}
