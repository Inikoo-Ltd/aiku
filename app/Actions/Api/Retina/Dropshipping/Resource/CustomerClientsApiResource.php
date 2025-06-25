<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 14-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Api\Retina\Dropshipping\Resource;

use App\Models\Catalogue\Product;
use App\Models\Fulfilment\StoredItem;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $slug
 * @property string $code
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property string $name
 * @property mixed $state
 * @property string $shop_slug
 * @property mixed $shop_code
 * @property mixed $shop_name
 * @property mixed $department_slug
 * @property mixed $department_code
 * @property mixed $department_name
 * @property mixed $family_slug
 * @property mixed $family_code
 * @property mixed $family_name
 * @property StoredItem|Product $item
 *
 */
class CustomerClientsApiResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                   => $this->id,
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
            'deactivated_at'          => $this->deactivated_at,
        ];
    }
}
