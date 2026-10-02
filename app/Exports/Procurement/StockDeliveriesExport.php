<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 20 Jun 2023 09:17:36 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Exports\Procurement;

use App\Models\GoodsIn\StockDelivery;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StockDeliveriesExport implements FromQuery, WithMapping, ShouldAutoSize, WithHeadings
{
    public function __construct(public Organisation $organisation)
    {
    }

    public function query(): Relation|\Illuminate\Database\Eloquent\Builder|StockDelivery|Builder
    {
        return StockDelivery::query()->where('organisation_id', $this->organisation->id);
    }

    /** @var StockDelivery $row */
    public function map($row): array
    {
        return [
            $row->id,
            $row->slug,
            $row->reference,
            $row->state->value,
            $row->date,
            $row->dispatched_at,
            $row->received_at,
            $row->checked_at,
            $row->placed_at,
            $row->cancelled_at,

            $row->number_stock_delivery_items,
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
            'Date',
            'Dispatched At',
            'Received At',
            'Checked At',
            'Placed At',
            'Cancelled At',

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
}
