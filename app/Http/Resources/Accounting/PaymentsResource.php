<?php

/*
 *  Author: Jonathan lopez <raul@inikoo.com>
 *  Created: Sat, 22 Oct 2022 18:53:15 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, inikoo
 */

namespace App\Http\Resources\Accounting;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $status
 * @property string $date
 * @property int $data
 * @property string $slug
 * @property string $reference
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property string $payment_service_providers_slug
 * @property string $payment_accounts_slug
 * @property mixed $id
 *
 */
class PaymentsResource extends JsonResource
{
    public function toArray($request): array
    {
        return array(
            'id'         => $this->id,
            'status'     => $this->status,
            'state_icon' => $this->status->stateIcon()[$this->status->value],
            'date'       => $this->date,
            'reference'  => $this->reference,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'organisation_name' => $this->organisation_name,
            'organisation_slug' => $this->organisation_slug,
            'shop_name'         => $this->shop_name,
            'shop_slug'         => $this->shop_slug,
        );
    }
}
