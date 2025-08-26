<?php

/*
 * author Arya Permana - Kirin
 * created on 28-04-2025-14h-35m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Accounting;

use App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum;
use App\Models\Accounting\CreditTransaction;
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
class CreditTransactionResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var CreditTransaction $creditTransaction */
        $creditTransaction = $this;

        return [
            'id'                => $creditTransaction->id,
            'payment_id'        => $creditTransaction->payment_id,
            'type'              => $creditTransaction->type->label(),
            'amount'            => $creditTransaction->amount,
            'running_amount'    => $creditTransaction->running_amount,
            'payment_reference' => $creditTransaction->payment?->reference,
            'payment_type'      => $creditTransaction->payment?->type,
            'currency_code'     => $creditTransaction->currency->code,
            'created_at'        => $creditTransaction->created_at
        ];
    }
}
