<?php

/*
 * author Arya Permana - Kirin
 * created on 28-01-2025-14h-16m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Http\Resources\Accounting;

use App\Models\Accounting\InvoiceTransaction;
use App\Models\Catalogue\Product;
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
 * @property mixed $model_type
 * @property mixed $model
 */
class RefundInProcessTransactionsResource extends JsonResource
{
    public function toArray($request): array
    {
        $totalLastRefund = abs(InvoiceTransaction::where('invoice_id', '!=', $this->refund_id)->where('original_invoice_transaction_id', $this->id)->sum('net_amount'));
        $refundNetAmount = abs(InvoiceTransaction::where('invoice_id', $this->refund_id)->where('original_invoice_transaction_id', $this->id)->sum('net_amount'));

        $packedInMessage = '';
        $units = soldPackUnits($this->historicAsset?->units, $this->model?->units);
        if ($this->model_type === 'Product' && $units > 1) {
            $packedInMessage = '('.__('Pack of').": " . trimDecimalZeros($units) . ")";
        }

        return [
            'asset_id'                       => $this->asset_id,
            'code'                           => $this->code,
            'slug'                           => $this->slug,
            'name'                           => $this->name,
            'quantity'                       => $this->quantity,
            'net_amount'                     => $this->net_amount,
            'max_refundable_amount'          => max(0, $this->net_amount - $totalLastRefund),
            'currency_code'                  => $this->currency_code,
            'in_process'                     => $this->in_process,
            'unit_price'                     => $this->price,
            'original_item_net_price'        => $this->quantity != 0 ? $this->net_amount / $this->quantity : $this->net_amount,
            'refund_net_amount'              => $refundNetAmount,
            'total_last_refund'              => $totalLastRefund,
            'packed_in_message'              => $packedInMessage,
            'bundle_items'                   => $this->bundleItems(),
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

    /**
     * The components of a bundle line with the net amount of one piece of each, the line's net amount split
     * by the components' current prices, so a missing component can be credited instead of the whole bundle.
     *
     * @return array<int, array{code: string, name: string, quantity: float, net_amount: float}>
     */
    public function bundleItems(): array
    {
        if ($this->model_type !== 'Product' || !$this->model?->is_bundle || !$this->model->bundle || (float) $this->quantity <= 0) {
            return [];
        }

        $bundleQuantity = (float) $this->quantity;
        $items          = $this->model->bundle->items()->with('item')->get()
            ->filter(fn ($bundleItem) => $bundleItem->item instanceof Product);

        $componentsValue = $items->sum(fn ($bundleItem) => $bundleItem->item->price * $bundleItem->quantity);

        return $items->map(fn ($bundleItem) => [
            'code'       => $bundleItem->item->code,
            'name'       => $bundleItem->item->name,
            'quantity'   => $bundleItem->quantity * $bundleQuantity,
            'net_amount' => $componentsValue > 0
                ? round($this->net_amount / $bundleQuantity * $bundleItem->item->price / $componentsValue, 2)
                : 0,
        ])->values()->all();
    }
}
