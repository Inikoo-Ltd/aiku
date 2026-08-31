<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 13 May 2026 11:05:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dispatching\DeliveryNote;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $reference
 * @property mixed $customer_name
 * @property mixed $date
 * @property mixed $slug
 * @property mixed $company_name
 * @property mixed $contact_name
 * @property mixed $customer_reference
 * @property mixed $tracking_number
 */
class DeliveryNotesForSelectResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        $name = $this->company_name ?: $this->contact_name ?: $this->customer_name;

        $parts = array_filter([
            $this->reference,
            $name,
            $this->customer_reference,
            $this->tracking_number,
        ]);

        return [
            'id'                 => $this->id,
            'label'              => implode(' | ', $parts).' ('.$this->date->format('Y-m-d').')',
            'reference'          => $this->reference,
            'customer_name'      => $name,
            'customer_reference' => $this->customer_reference,
            'tracking_number'    => $this->tracking_number,
            'date'               => $this->date->format('Y-m-d'),
        ];
    }
}
