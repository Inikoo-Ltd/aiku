<?php

/*
 *  Author: Jonathan lopez <raul@inikoo.com>
 *  Created: Sat, 22 Oct 2022 18:53:15 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, inikoo
 */

namespace App\Http\Resources\Accounting;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $id
 * @property string $slug
 * @property string $name
 * @property string $code
 * @property mixed $balance
 * @property mixed $shop_slug
 * @property mixed $shop_type
 * @property mixed $fulfilment_slug
 * @property mixed $organisation_name
 * @property mixed $organisation_slug
 * @property mixed $shop_name
 * @property mixed $reference
 * @property mixed $currency_code
 * @property mixed $number_credit_transactions
 * @property mixed $shop_code
 */
class CustomerBalancesResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                         => $this->id,
            'slug'                       => $this->slug,
            'name'                       => $this->name,
            'reference'                  => $this->reference,
            'balance'                    => $this->balance,
            'shop_slug'                  => $this->shop_slug,
            'shop_type'                  => $this->shop_type,
            'shop_code'                  => $this->shop_code,
            'fulfilment_slug'            => $this->fulfilment_slug,
            'organisation_name'          => $this->organisation_name,
            'organisation_slug'          => $this->organisation_slug,
            'shop_name'                  => $this->shop_name,
            'currency_code'              => $this->currency_code,
            'number_credit_transactions' => $this->number_credit_transactions
        ];
    }
}
