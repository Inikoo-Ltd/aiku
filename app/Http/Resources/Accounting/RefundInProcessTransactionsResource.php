<?php

/*
 * author Arya Permana - Kirin
 * created on 28-01-2025-14h-16m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Accounting;

use App\Models\Accounting\InvoiceTransaction;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $code
 * @property string $quantity
 * @property string $net_amount
 * @property string $name
 * @property string $currency_code
 * @property mixed $id
 * @property mixed $in_process
 * @property mixed $refund_id
 * @property mixed $price
 */
class RefundInProcessTransactionsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'code'                      => $this->code,
            'name'                      => $this->name,
            'quantity'                  => (int) $this->quantity,
            'net_amount'                => $this->net_amount,
            'currency_code'             => $this->currency_code,
            'in_process'                => $this->in_process,
            'unit_price'                => $this->price,
            'refund_net_amount'             => InvoiceTransaction::where('invoice_id', $this->refund_id)->where('invoice_transaction_id', $this->id)->sum('net_amount'),
            'refund_route'              => [
                'name'       => 'grp.models.refund.refund_transaction.store',
                'parameters' => [
                    'refund' => $this->refund_id,
                    'invoiceTransaction' => $this->id,
                ]
            ],
            'refund_transaction_full_refund'              => [
                'name'       => 'grp.models.refund.refund_transaction.full_refund',
                'parameters' => [
                    'refund' => $this->refund_id,
                    'invoiceTransaction' => $this->id,
                ]
            ],
            'delete_route'              => [
                'name'       => 'grp.models.refund_transaction.delete',
                'parameters' => [
                    'invoiceTransaction' => $this->id,
                ],
                'method'     => 'delete',
            ]
        ];
    }
}
