<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 12 Jun 2023 13:57:50 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Helpers;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $address_line_1
 * @property string $address_line_2
 * @property string $sorting_code
 * @property string $postal_code
 * @property string $country_code
 * @property int $country_id
 * @property string $locality
 * @property string $dependent_locality
 * @property string $administrative_area
 * @property mixed $country
 */
class AddressFormFieldsResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'address_line_1' => $this->address_line_1 ?? null,
            'address_line_2' => $this->address_line_2 ?? null,
            'sorting_code' => $this->sorting_code ?? null,
            'postal_code' => $this->postal_code ?? null,
            'locality' => $this->locality ?? null,
            'dependent_locality' => $this->dependent_locality ?? null,
            'administrative_area' => $this->administrative_area ?? null,
            'country_code' => $this->country_code ?? null,
            'country_id' => $this->country_id ?? null,
        ];
    }
}
