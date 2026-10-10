<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Mon, 27 Jul 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Procurement;

use App\Actions\GoodsIn\StockDeliveryItem\ResolveStockDeliveryItemDiscrepancy;
use App\Enums\GoodsIn\StockDeliveryClaimStateEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemDiscrepancyEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemDiscrepancyOutcomeEnum;
use App\Models\GoodsIn\StockDeliveryItem;
use Illuminate\Support\Arr;
use Illuminate\Http\Resources\Json\JsonResource;

class StockDeliveryUnderOverDeliveredItemResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var StockDeliveryItem $item */
        $item = $this->resource;

        $supplierProduct = $item->supplierProduct;
        $discrepancy     = $item->discrepancy();
        $claim           = $item->claim;
        $recountTask     = ResolveStockDeliveryItemDiscrepancy::recountTask($item);

        return [
            'id'                    => $item->id,
            'code'                  => $supplierProduct?->code,
            'name'                  => $supplierProduct?->name,
            'units_per_pack'        => $supplierProduct?->units_per_pack ?? $item->org_stock_packed_in,
            'units_per_carton'      => $supplierProduct?->units_per_carton ?? $item->org_stock_packed_in,
            'unit_quantity'         => $item->unit_quantity,
            'unit_quantity_checked' => $item->unit_quantity_checked,
            'org_stock_id'          => $item->org_stock_id,
            'org_stock_slug'        => $item->org_stock_slug,
            'org_stock_code'        => $item->org_stock_code,
            'org_stock_name'        => $item->org_stock_name,
            'difference_units'      => (float) $item->difference_units,
            'difference_skos'       => $item->difference_skos === null ? null : (float) $item->difference_skos,
            'difference_percentage' => $item->difference_percentage === null ? null : (float) $item->difference_percentage,
            'difference_amount'     => $item->difference_amount === null ? null : (float) $item->difference_amount,
            'currency_code'         => $item->currency_code,
            'supplier_unit'         => $supplierProduct?->supplier_unit?->value,
            'units_per_supplier_unit' => $supplierProduct?->supplier_unit ? $supplierProduct->unitsPerSupplierUnit() : null,
            'net_amount'            => (float) $item->net_amount,
            'discrepancy'           => $discrepancy?->value,
            'discrepancy_label'     => $discrepancy ? StockDeliveryItemDiscrepancyEnum::labels()[$discrepancy->value] : null,
            'outcome'               => $item->discrepancy_outcome?->value,
            'outcome_label'         => $item->discrepancy_outcome ? StockDeliveryItemDiscrepancyOutcomeEnum::labels()[$item->discrepancy_outcome->value] : null,
            'resolved_at'           => $item->discrepancy_resolved_at,
            'unit_corrections'      => Arr::get($item->data, 'unit_corrections', []),
            'recount_task'          => $recountTask ? [
                'reference' => $recountTask->reference,
                'status'    => $recountTask->status->value,
                'is_open'   => $recountTask->status->isOpen(),
            ] : null,
            'claim'                 => $claim ? [
                'id'                    => $claim->id,
                'state'                 => $claim->state->value,
                'state_label'           => StockDeliveryClaimStateEnum::labels()[$claim->state->value],
                'quantity'              => (float) $claim->quantity,
                'amount'                => (float) $claim->amount,
                'currency_code'         => $claim->currency->code,
                'credit_note_reference' => $claim->credit_note_reference,
                'credit_note_amount'    => $claim->credit_note_amount === null ? null : (float) $claim->credit_note_amount,
                'credit_note_date'      => $claim->credit_note_date?->toDateString(),
                'notes'                 => $claim->notes,
                'updateRoute'           => [
                    'name'       => 'grp.models.stock-delivery-claim.update',
                    'parameters' => ['stockDeliveryClaim' => $claim->id],
                    'method'     => 'post',
                ],
            ] : null,
            'customs'               => $this->customs($item),
            'resolveRoute'          => [
                'name'       => 'grp.models.stock-delivery-item.resolve-discrepancy',
                'parameters' => ['stockDeliveryItem' => $item->id],
                'method'     => 'post',
            ],
        ];
    }

    /**
     * What a claim could also get back from customs: nothing on a duty-free tariff line, and never the import
     * VAT, which is deducted in the VAT return.
     *
     * @return array{tariff_code: string, duty_rate: float, duty_on_missing: float|null}|null
     */
    private function customs(StockDeliveryItem $item): ?array
    {
        $line = $item->customsLine;
        if (!$line) {
            return null;
        }

        $missing = max((float) $item->unit_quantity - (float) $item->unit_quantity_checked, 0);

        return [
            'tariff_code'     => $line->tariff_code,
            'duty_rate'       => (float) $line->duty_rate,
            'duty_on_missing' => (float) $item->unit_quantity > 0 && $item->cost_duties !== null
                ? round((float) $item->cost_duties * $missing / (float) $item->unit_quantity, 2)
                : null,
        ];
    }
}
