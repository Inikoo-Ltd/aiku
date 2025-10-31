<?php

/*
 * author Arya Permana - Kirin
 * created on 28-01-2025-14h-13m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Accounting;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $code
 * @property string $quantity
 * @property string $net_amount
 * @property string $name
 * @property string $currency_code
 * @property mixed $id
 * @property mixed $in_process
 */
class RefundTransactionsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'code'                      => $this->code,
            'name'                      => $this->name,
            'slug'                      => $this->slug,
            'quantity'                  => -$this->quantity,
            'net_amount'                => $this->net_amount,
            'currency_code'             => $this->currency_code,
            'in_process'                => $this->in_process,
            'refund_route'              => [
                'name'       => 'grp.models.refund.refund_transaction.store',
                'parameters' => [
                    'invoiceTransaction' => $this->id,
                ]
            ],
            'full_refund_route'              => [
                'name'       => 'grp.models.refund.refund_transaction.full_refund',
                'parameters' => [
                    'invoiceTransaction' => $this->id,
                ]
            ],
            'delete_route'              => [
                'name'       => 'grp.models.refund_transaction.force_delete',
                'parameters' => [
                    'invoiceTransaction' => $this->id,
                ],
                'method'     => 'delete',
            ]
        ];
    }
}
