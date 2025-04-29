<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Jun 2024 11:24:08 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\CRM;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $ulid
 * @property string $reference
 * @property string $name
 * @property string $contact_name
 * @property string $company_name
 * @property string $email
 * @property string $phone
 * @property array $location
 * @property string $created_at
 * @property string $updated_at
 */
class CustomerClientResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'id'                   => $this->id,
            'ulid'                   => $this->ulid,
            'reference'              => $this->reference,
            'name'                   => $this->name,
            'contact_name'           => $this->contact_name,
            'company_name'           => $this->company_name,
            'location'               => is_string($this->location) ? json_decode($this->location) : $this->location,
            'email'                  => $this->email,
            'phone'                  => $this->phone,
            'created_at'             => $this->created_at,
            'updated_at'             => $this->updated_at
        ];
    }
}
