<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 25-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Api\Retina\Fulfilment\Resource;

use App\Http\Resources\HasSelfCall;
use App\Http\Resources\Helpers\AddressResource;
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
class CustomerClientApiResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'id'                     => $this->id,
            'ulid'                   => $this->ulid,
            'reference'              => $this->reference,
            'active'                 => $this->status,
            'name'                   => $this->name,
            'contact_name'           => $this->contact_name,
            'company_name'           => $this->company_name,
            'location'               => is_string($this->location) ? json_decode($this->location) : $this->location,
            'email'                  => $this->email,
            'phone'                  => $this->phone,
            'created_at'             => $this->created_at,
            'updated_at'             => $this->updated_at,
            'address'                => AddressResource::make($this->address),
        ];
    }
}
