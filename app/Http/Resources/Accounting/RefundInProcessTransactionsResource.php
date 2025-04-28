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
        $totalLastRefund = abs(InvoiceTransaction::where('invoice_id', '!=', $this->refund_id)->where('original_invoice_transaction_id', $this->id)->sum('net_amount'));
        $refundNetAmount = abs(InvoiceTransaction::where('invoice_id', $this->refund_id)->where('original_invoice_transaction_id', $this->id)->sum('net_amount'));

        return [
            'code'                           => $this->code,
            'name'                           => $this->name,
            'quantity'                       => (int)$this->quantity,
            'net_amount'                     => $this->net_amount,
            'max_refundable_amount'          => max(0, $this->net_amount - $totalLastRefund),
            'currency_code'                  => $this->currency_code,
            'in_process'                     => $this->in_process,
            'unit_price'                     => $this->price,
            'original_item_net_price'        => $this->quantity != 0 ? $this->net_amount / $this->quantity : $this->net_amount,
            'refund_net_amount'              => $refundNetAmount,
            'total_last_refund'              => $totalLastRefund,
            'refund_route'                   => [
                'name'       => 'grp.models.refund.refund_transaction.store',
                'parameters' => [
                    'refund'             => $this->refund_id,
                    'invoiceTransaction' => $this->id,
                ]
            ],
            'refund_transaction_full_refund' => [
                'name'       => 'grp.models.refund.refund_transaction.full_refund',
                'parameters' => [
                    'refund'             => $this->refund_id,
                    'invoiceTransaction' => $this->id,
                ],
                'method'     => 'post'
            ],
            'delete_route'                   => [
                'name'       => 'grp.models.refund_transaction.force_delete',
                'parameters' => [
                    'invoiceTransaction' => $this->id,
                ],
                'method'     => 'delete',
            ]
        ];
    }
}
