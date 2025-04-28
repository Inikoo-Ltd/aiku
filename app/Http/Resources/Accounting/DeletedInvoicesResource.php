<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 01:38:56 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Accounting;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $slug
 * @property string $code
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property string $name
 * @property string $state
 * @property string $shop_slug
 * @property string $reference
 * @property string $total_amount
 * @property string $net_amount
 * @property mixed $date
 * @property mixed $type
 * @property mixed $shop_code
 * @property mixed $shop_name
 * @property mixed $customer_name
 * @property mixed $customer_slug
 * @property mixed $currency_code
 * @property mixed $currency_symbol
 * @property mixed $tax_liability_at
 * @property mixed $paid_at
 * @property mixed $pay_status
 * @property mixed $organisation_name
 * @property mixed $organisation_slug
 * @property mixed $in_process
 * @property mixed $organisation_code
 * @property mixed $deleted_at
 * @property mixed $deleted_note
 * @property mixed $deleted_by
 * @property mixed $deleted_by_name
 * @property mixed $shop_id
 *
 */
class DeletedInvoicesResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'slug'              => $this->slug,
            'reference'         => $this->reference,
            'total_amount'      => $this->total_amount,
            'net_amount'        => $this->net_amount,
            'state'             => $this->state,
            'date'              => $this->date,
            'type'              => [
                'label' => $this->type->labels()[$this->type->value],
                'icon'  => $this->type->typeIcon()[$this->type->value],
            ],
            'shop_id'           => $this->shop_id,
            'shop_slug'         => $this->shop_slug,
            'shop_code'         => $this->shop_code,
            'shop_name'         => $this->shop_name,
            'customer_name'     => $this->customer_name,
            'customer_slug'     => $this->customer_slug,
            'currency_code'     => $this->currency_code,
            'currency_symbol'   => $this->currency_symbol,
            'organisation_name' => $this->organisation_name,
            'organisation_code' => $this->organisation_code,
            'organisation_slug' => $this->organisation_slug,
            'deleted_at'        => $this->deleted_at,
            'deleted_note'      => $this->deleted_note,
            'deleted_by'        => $this->deleted_by,
            'deleted_by_name'   => $this->deleted_by_name
        ];
    }
}
