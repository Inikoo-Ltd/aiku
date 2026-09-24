<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Procurement;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * @property int $id
 * @property string $auditable_type
 * @property array $new_values
 * @property array $data
 * @property \Illuminate\Support\Carbon $created_at
 * @property string|null $user_name
 * @property string|null $purchase_order_reference
 */
class ProcurementNoteResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                       => $this->id,
            'created_at'               => $this->created_at,
            'author'                   => $this->user_name ?? Arr::get($this->data, 'author'),
            'note'                     => Arr::get($this->new_values, 'note'),
            'strikethrough'            => (bool)Arr::get($this->data, 'strikethrough', false),
            'purchase_order_reference' => $this->auditable_type === 'PurchaseOrder' ? $this->purchase_order_reference : null,
        ];
    }
}
