<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery;

use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderAttachmentScopeEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Models\Helpers\Media;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * What an invoice reading would do to the delivery's costing, line by line, for someone to check
 * before applying: invoice lines are matched to delivery lines by supplier product code only (a code
 * on two delivery lines matches neither). The goods cost proposed is the invoice's unit price times what
 * was received, and a line whose cost lands far from what was ordered is flagged for someone to check,
 * which is where invoices quantified in packs or cartons rather than units show up.
 */
class GetStockDeliveryInvoiceCosting
{
    use AsObject;

    private const float TOLERANCE = 0.01;

    private const float CHECK_SHARE = 0.2;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function handle(StockDelivery $stockDelivery): array
    {
        $invoices = $stockDelivery->attachments()
            ->wherePivotIn('scope', [PurchaseOrderAttachmentScopeEnum::INVOICE->value, PurchaseOrderAttachmentScopeEnum::PROFORMA->value])
            ->orderByPivot('created_at', 'desc')
            ->get();

        return $invoices->map(fn (Media $media) => [
            'media_id'   => $media->id,
            'name'       => $media->file_name,
            'scope'      => $media->pivot->scope,
            'readRoute'  => [
                'name'       => 'grp.models.stock-delivery.invoice.read',
                'parameters' => ['stockDelivery' => $stockDelivery->id, 'media' => $media->id],
                'method'     => 'post',
            ],
            'applyRoute' => [
                'name'       => 'grp.models.stock-delivery.invoice.apply',
                'parameters' => ['stockDelivery' => $stockDelivery->id, 'media' => $media->id],
                'method'     => 'post',
            ],
            'reading'    => $this->review($stockDelivery, ReadStockDeliveryInvoice::reading($stockDelivery, $media)),
        ])->values()->all();
    }

    /**
     * @param  array<string, mixed>|null  $reading
     * @return array<string, mixed>|null
     */
    public function review(StockDelivery $stockDelivery, ?array $reading): ?array
    {
        if (Arr::get($reading, 'state') !== 'read') {
            return $reading;
        }

        $items = $stockDelivery->items()
            ->where('state', '!=', StockDeliveryItemStateEnum::CANCELLED)
            ->with('supplierProduct:id,code,name')
            ->orderBy('id')
            ->get();

        $itemsByCode = $items->filter(fn (StockDeliveryItem $item) => $item->supplierProduct)
            ->groupBy(fn (StockDeliveryItem $item) => self::normaliseCode($item->supplierProduct->code))
            ->filter(fn ($sameCode) => $sameCode->count() === 1)
            ->map(fn ($sameCode) => $sameCode->first());

        $invoiced   = [];
        $unmatched  = [];
        $linesTotal = 0.0;

        foreach (Arr::get($reading, 'lines', []) as $line) {
            $amount = $line['amount'] ?? (isset($line['quantity'], $line['unit_price']) ? round($line['quantity'] * $line['unit_price'], 2) : null);
            $item   = $line['code'] ? $itemsByCode->get(self::normaliseCode($line['code'])) : null;

            $linesTotal += (float) $amount;

            if (! $item || $amount === null) {
                $unmatched[] = [...$line, 'amount' => $amount];
                continue;
            }

            $invoiced[$item->id]['amount']   = ($invoiced[$item->id]['amount'] ?? 0) + $amount;
            $invoiced[$item->id]['quantity'] = ($invoiced[$item->id]['quantity'] ?? 0) + (float) ($line['quantity'] ?? 0);
        }

        $currency    = $stockDelivery->currency?->code;
        $linesTotal   = round($linesTotal, 2);
        $chargesTotal = round(array_sum(array_column(Arr::get($reading, 'charges', []), 'amount')), 2);
        $total       = Arr::get($reading, 'total');

        return [
            ...Arr::only($reading, ['state', 'read_at', 'applied_at', 'is_invoice', 'invoice_number', 'invoice_date', 'currency', 'charges', 'total']),
            'delivery_currency'  => $currency,
            'currency_mismatch'  => $reading['currency'] && $currency && strtoupper($reading['currency']) !== $currency,
            'total_mismatch'     => $total !== null && abs($linesTotal + $chargesTotal - $total) > self::TOLERANCE,
            'lines_total'        => $linesTotal,
            'charges_total'      => $chargesTotal,
            'can_apply'          => $stockDelivery->state === StockDeliveryStateEnum::PLACED && ! $stockDelivery->is_costed,
            'unmatched'          => $unmatched,
            'items'              => $items->map(function (StockDeliveryItem $item) use ($invoiced) {
                $quantity = (float) $item->unit_quantity_placed ?: (float) $item->unit_quantity_checked ?: (float) $item->unit_quantity;
                $current  = (float) ($item->cost_items ?? $item->net_amount);
                $invoice  = $invoiced[$item->id] ?? null;
                $expected = (float) $item->net_amount;
                $proposed = match (true) {
                    ! $invoice => $current,
                    $invoice['quantity'] > 0 => round($invoice['amount'] / $invoice['quantity'] * $quantity, 2),
                    default => round($invoice['amount'], 2),
                };

                return [
                    'id'               => $item->id,
                    'code'             => $item->supplierProduct?->code,
                    'name'             => $item->supplierProduct?->name,
                    'quantity'         => $quantity,
                    'expected_amount'  => $expected,
                    'current_cost'     => $current,
                    'invoice_quantity' => $invoice['quantity'] ?? null,
                    'invoice_amount'   => $invoice ? round($invoice['amount'], 2) : null,
                    'quantity_differs' => $invoice && abs($invoice['quantity'] - $quantity) > self::TOLERANCE,
                    'amount_differs'   => $invoice && abs($invoice['amount'] - $expected) > self::TOLERANCE,
                    'proposed_cost'    => $proposed,
                    'needs_check'      => $invoice && abs($proposed - $expected) > max($expected * self::CHECK_SHARE, self::TOLERANCE),
                ];
            })->values()->all(),
        ];
    }

    public static function normaliseCode(?string $code): string
    {
        return strtolower((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $code));
    }
}
