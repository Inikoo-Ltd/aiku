<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 20 Jun 2023 09:17:36 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Exports\Procurement;

use App\Models\Procurement\PurchaseOrder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PurchaseOrdersExport implements FromQuery, ShouldAutoSize, WithChunkReading, WithHeadings, WithMapping
{
    use Exportable;

    public function query(): Relation|\Illuminate\Database\Eloquent\Builder|PurchaseOrder|Builder
    {
        return PurchaseOrder::query();
    }

    /** @var PurchaseOrder */
    public function map($row): array
    {
        return [
            $row->id,
            $row->slug,
            $row->status->value,
            $row->state->value,
            $row->date,
            $row->submitted_at,
            $row->confirmed_at,
            $row->manufactured_at,
            $row->dispatched_at,
            $row->received_at,
            $row->checked_at,
            $row->settled_at,
            $row->cancelled_at,
            $row->currency->code,
            $row->exchange,
            $row->number_of_items,
            $row->gross_weight,
            $row->net_weight,
            $row->cost_items,
            $row->cost_extra,
            $row->cost_shipping,
            $row->cost_duties,
            $row->cost_tax,
            $row->cost_total,
            $row->created_at,
        ];
    }

    public function headings(): array
    {
        return [
            '#',
            'Slug',
            'Status',
            'State',
            'Date',
            'Submitted At',
            'Confirmed At',
            'Manufactured At',
            'Dispatched At',
            'Received At',
            'Checked At',
            'Settled At',
            'Cancelled At',
            'Currency',
            'Exchange',
            'Number of Items',
            'Gross Weight',
            'Net Weight',
            'Cost Items',
            'Cst Extra',
            'Cost Shipping',
            'Cost Duties',
            'Cost Tax',
            'Cost Total',
            'Created At',
        ];
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}
