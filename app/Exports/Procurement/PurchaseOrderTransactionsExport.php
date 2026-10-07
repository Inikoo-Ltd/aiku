<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Sep 2026 14:00:00 British Summer Time, Sheffield, UK
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Exports\Procurement;

use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PurchaseOrderTransactionsExport implements FromCollection, WithMapping, WithHeadings, ShouldAutoSize
{
    public function __construct(protected PurchaseOrder $purchaseOrder)
    {
    }

    public function collection(): Collection
    {
        return $this->purchaseOrder->purchaseOrderTransactions()
            ->with(['supplierProduct.tradeUnits.tariffCodeOverrides', 'orgStock'])
            ->get()
            ->sortBy(fn (PurchaseOrderTransaction $transaction) => $transaction->supplierProduct?->code)
            ->values();
    }

    /** @param PurchaseOrderTransaction $row */
    public function map($row): array
    {
        $isPartner = $this->purchaseOrder->parent_type === 'OrgPartner';

        return [
            $isPartner ? $row->orgStock?->code : $row->supplierProduct?->code,
            $isPartner ? (float) $row->quantity_ordered / max(1, (int) $row->orgStock?->packed_in) : (float) $row->quantity_ordered,
            $row->supplierProduct?->name,
            $row->orgStock?->code,
            $row->supplierProduct?->units_per_pack,
            $row->supplierProduct?->units_per_carton,
            $row->supplierProduct?->tradeUnits->first()?->getTariffCodeForOrganisation($this->purchaseOrder->organisation_id),
            $row->unit_cost === null ? null : (float) $row->unit_cost,
            (float) $row->net_amount,
            $row->state->value,
        ];
    }

    public function headings(): array
    {
        return ['code', 'quantity', 'name', 'sko', 'units_per_pack', 'units_per_carton', 'tariff_code', 'unit_cost', 'amount', 'state'];
    }
}
