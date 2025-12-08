<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 12 Feb 2025 12:50:34 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Fulfilment;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $reference
 * @property string $email
 * @property string $name
 * @property string $phone
 * @property string $slug
 * @property int $id
 * @property mixed $location
 * @property mixed $registered_at
 * @property mixed $rejected_at
 * @property mixed $rejected_reason
 * @property mixed $rejected_notes
 */
class FulfilmentCustomersRejectedResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'reference' => $this->reference,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'location' => $this->location,
            'rejected_at' => $this->rejected_at,
            'rejected_reason' => $this->rejected_reason,
            'rejected_notes' => $this->rejected_notes,

        ];
    }
}
