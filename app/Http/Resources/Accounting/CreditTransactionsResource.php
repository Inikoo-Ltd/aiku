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
        ];
    }
}
