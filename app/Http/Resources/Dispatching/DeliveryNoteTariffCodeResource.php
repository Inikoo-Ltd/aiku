<?php

namespace App\Http\Resources\Dispatching;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $tariff_code
 * @property string|null $description
 * @property string|null $origin
 * @property string|null $origin_name
 * @property bool $dg
 * @property string|null $un_numbers
 * @property string|null $parts
 * @property int $num_parts
 * @property numeric $units
 * @property numeric $weight
 * @property numeric $amount
 */
class DeliveryNoteTariffCodeResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'tariff_code' => $this->tariff_code,
            'description' => $this->description,
            'origin'      => $this->origin,
            'origin_name' => $this->origin_name,
            'dg'          => (bool)$this->dg,
            'un_numbers'  => $this->un_numbers,
            'parts'       => $this->parts ? explode(', ', $this->parts) : [],
            'num_parts'   => (int)$this->num_parts,
            'units'       => (float)$this->units,
            'weight'      => (float)$this->weight,
            'amount'      => (float)$this->amount,
        ];
    }
}
