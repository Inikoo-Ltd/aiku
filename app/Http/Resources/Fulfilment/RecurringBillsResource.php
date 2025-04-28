<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 26 Jan 2024 17:29:39 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Fulfilment;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 *
 * @property int $id
 * @property int $reference
 * @property int $slug
 * @property mixed $customer_name
 * @property mixed $fulfilment_customer_slug
 * @property mixed $net_amount
 * @property mixed $start_date
 * @property mixed $end_date
 * @property mixed $currency
 * @property mixed $currency_symbol
 * @property mixed $number_transactions
 */
class RecurringBillsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                       => $this->id,
            'slug'                     => $this->slug,
            'status_icon'             => $this->status->statusIcon()[$this->status->value],
            'reference'                => $this->reference,
            'customer_name'            => $this->customer_name,
            'fulfilment_customer_slug' => $this->fulfilment_customer_slug,
            'net_amount'               => $this->net_amount,
            'start_date'               => $this->start_date,
            'end_date'                 => $this->end_date,
            'currency_code'            => $this->currency_code ?? ($this->currency ? $this->currency->code : null),
            'currency_symbol'          => $this->currency_symbol,
            'number_item_transactions'      => $this->number_item_transactions
        ];
    }
}
