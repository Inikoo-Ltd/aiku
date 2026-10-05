<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 20 Jun 2023 09:17:36 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Exports\Procurement;

use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PurchaseOrdersExport implements FromQuery, WithMapping, ShouldAutoSize, WithHeadings, WithChunkReading
{
    use Exportable;

    public function __construct(public Organisation $organisation)
    {
    }

    public function query(): Relation|\Illuminate\Database\Eloquent\Builder|PurchaseOrder|Builder
    {
        return PurchaseOrder::query()->where('organisation_id', $this->organisation->id)->with('currency');
    }

    /** @var PurchaseOrder $row */
    public function map($row): array
    {
        return [
            $row->id,
            $row->slug,
            $row->reference,
            $row->state->value,
            $row->delivery_state?->value,
            $row->date,
            $row->submitted_at,
            $row->confirmed_at,
            $row->settled_at,
            $row->cancelled_at,
            $row->currency->code,
            $row->org_exchange,
            $row->number_purchase_order_transactions,
            $row->gross_weight,
            $row->net_weight,
            $row->cost_items,
            $row->cost_extra,
            $row->cost_shipping,
            $row->cost_duties,
            $row->cost_tax,
            $row->cost_total,
            $row->created_at
        ];
    }

    public function headings(): array
    {
        return [
            '#',
            'Slug',
            'Reference',
            'State',
            'Delivery State',
            'Date',
            'Submitted At',
            'Confirmed At',
            'Settled At',
            'Cancelled At',
            'Currency',
            'Exchange',
            'Number of Items',
            'Gross Weight',
            'Net Weight',
            'Cost Items',
            'Cost Extra',
            'Cost Shipping',
            'Cost Duties',
            'Cost Tax',
            'Cost Total',
            'Created At'
        ];
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}
