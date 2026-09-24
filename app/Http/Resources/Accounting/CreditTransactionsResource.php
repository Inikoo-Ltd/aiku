<?php

/*
 * author Arya Permana - Kirin
 * created on 28-04-2025-14h-35m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Accounting;

use App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * @property int $id
 * @property int $payment_id
 * @property CreditTransactionTypeEnum $type
 * @property float|int|string $amount
 * @property float|int|string $running_amount
 * @property string|null $payment_reference
 * @property string|null $payment_type
 * @property string $currency_code
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class CreditTransactionsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'payment_id'        => $this->payment_id,
            'type'              => $this->type->label(),
            'amount'            => $this->amount,
            'running_amount'    => $this->running_amount,
            'payment_reference' => $this->payment_reference,
            'payment_type'      => $this->payment_type,
            'currency_code'     => $this->currency_code,
            'created_at'        => $this->created_at,
            'order_slug'        => $this->order_slug,
            'order_reference'   => $this->order_reference,
            'notes'             => $this->notes,
            'requested_by'      => Arr::get($this->data, 'requested_by'),
            'applied_by'        => Arr::get($this->data, 'applied_by'),
            'credit_note_slug'  => $this->credit_note_slug,
            'credit_note_reference' => $this->credit_note_reference,
            'customer_slug'     => $this->customer_slug,
            'customer_ref'      => $this->customer_ref,
            'shop_slug'         => $this->shop_slug,
            'org_slug'          => $this->org_slug,
        ];
    }
}
