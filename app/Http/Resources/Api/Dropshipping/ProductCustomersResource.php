<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 12 Jun 2024 15:41:18 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Api\Dropshipping;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $slug
 * @property string $code
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property string $name
 * @property mixed $state
 * @property mixed $id
 * @property mixed $reference
 * @property mixed $status
 * @property mixed $last_added_at
 * @property mixed $last_removed_at
 * @property mixed $customer_id
 * @property mixed $customer_slug
 * @property mixed $customer_reference
 * @property mixed $customer_name
 * @property mixed $customer_contact_name
 * @property mixed $customer_email
 * @property mixed $customer_updated_at
 * @property mixed $customer_created_at
 *
 */
class ProductCustomersResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                         => $this->id,
            'customer_product_reference' => $this->reference,
            'status'                     => $this->status,
            'last_added_at'              => $this->last_added_at,
            'last_removed_at'            => $this->last_removed_at,
            'customer_id'                => $this->customer_id,
            'created_at'                 => $this->created_at ? (method_exists($this->created_at, 'format') ? $this->created_at->format('Y-m-d H:i:s') : (\Illuminate\Support\Carbon::parse($this->created_at)->format('Y-m-d H:i:s'))) : null,
            'updated_at'                 => $this->updated_at,

                        'customer' => [
                                'id'           => $this->customer_id,
                                'slug'         => $this->customer_slug,
                                'number'       => $this->customer_reference,
                                'name'         => $this->customer_name,
                                'contact_name' => $this->customer_contact_name,
                                'email'        => $this->customer_email,
                                'created_at'   => $this->customer_created_at ? (\Illuminate\Support\Carbon::parse($this->customer_created_at)->format('Y-m-d H:i:s')) : null,
                                'updated_at'   => $this->customer_updated_at,
                            ]
        ];
    }
}
