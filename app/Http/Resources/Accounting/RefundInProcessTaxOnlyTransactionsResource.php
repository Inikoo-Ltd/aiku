<?php

/*
 * author Louis Perez
 * created on 06-02-2026-09h-08m
 * github: https://github.com/louis-perez
 * copyright 2026
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
 * @property mixed $slug
 * @property mixed $asset_id
 */
class RefundInProcessTaxOnlyTransactionsResource extends JsonResource
{
    public function toArray($request): array
    {
        $totalLastRefund = abs(InvoiceTransaction::where('invoice_id', '!=', $this->refund_id)->where('original_invoice_transaction_id', $this->id)->sum('tax_amount'));
        $refundNetAmount = abs(InvoiceTransaction::where('invoice_id', $this->refund_id)->where('original_invoice_transaction_id', $this->id)->sum('tax_amount'));

        $taxChargeAmount = round($this->net_amount * $this->tax_rate, 2);

        return [
            'asset_id'                       => $this->asset_id,
            'code'                           => $this->code,
            'slug'                           => $this->slug,
            'name'                           => $this->name,
            'quantity'                       => $this->quantity,
            'net_amount'                     => $taxChargeAmount,
            'max_refundable_amount'          => max(0, $taxChargeAmount - $totalLastRefund),
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
