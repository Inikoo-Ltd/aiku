<?php

namespace App\Http\Resources\CRM;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $country_code
 * @property string $country_name
 * @property int $total
 * @property int $number_active
 * @property int $number_losing
 * @property int $number_lost
 * @property int $number_ordered
 * @property int $number_never_ordered
 */
class CustomerCountriesResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'country_code'         => $this->country_code,
            'country_name'         => $this->country_name,
            'total'                => (int) $this->total,
            'number_ordered'       => (int) $this->number_ordered,
            'number_never_ordered' => (int) $this->number_never_ordered,
            'number_active'        => (int) $this->number_active,
            'number_losing'        => (int) $this->number_losing,
            'number_lost'          => (int) $this->number_lost,
        ];
    }
}
