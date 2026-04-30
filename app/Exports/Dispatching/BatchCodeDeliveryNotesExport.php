<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 30 Apr 2026
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Exports\Dispatching;

use App\Models\Dispatching\BatchCode;
use App\Models\Dispatching\Picking;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class BatchCodeDeliveryNotesExport implements FromQuery, WithMapping, WithHeadings, ShouldAutoSize, WithColumnFormatting
{
    public function __construct(
        protected BatchCode $batchCode
    ) {
    }

    public function query(): Relation|\Illuminate\Database\Eloquent\Builder|Builder
    {
        $pickingSessionsCount = function ($q) {
            $q->selectRaw('COALESCE(COUNT(picking_session_id), 0)')
                ->from('picking_session_has_delivery_notes')
                ->whereColumn('picking_session_has_delivery_notes.delivery_note_id', 'delivery_notes.id');
        };

        $pickingSessionIds = function ($q) {
            $q->selectRaw("COALESCE(STRING_AGG(CAST(picking_session_id AS VARCHAR), ','), '')")
                ->from('picking_session_has_delivery_notes')
                ->whereColumn('picking_session_has_delivery_notes.delivery_note_id', 'delivery_notes.id');
        };

        return \App\Models\Dispatching\DeliveryNote::query()
            ->whereIn(
                'delivery_notes.id',
                Picking::query()
                    ->join('delivery_note_items', 'pickings.delivery_note_item_id', '=', 'delivery_note_items.id')
                    ->where('pickings.batch_code_id', $this->batchCode->id)
                    ->select('delivery_note_items.delivery_note_id')
                    ->distinct()
            )
            ->leftJoin('customers', 'delivery_notes.customer_id', '=', 'customers.id')
            ->leftJoin('shops', 'delivery_notes.shop_id', '=', 'shops.id')
            ->leftJoin('organisations', 'delivery_notes.organisation_id', '=', 'organisations.id')
            ->select([
                'delivery_notes.id',
                'delivery_notes.reference',
                'delivery_notes.date',
                'delivery_notes.state',
                'delivery_notes.slug',
                'delivery_notes.number_items',
                'customers.name as customer_name',
                'customers.phone as customer_phone',
                'shops.name as shop_name',
                'organisations.name as organisation_name',
            ])
            ->selectSub($pickingSessionsCount, 'picking_sessions_count')
            ->selectSub($pickingSessionIds, 'picking_session_ids')
            ->orderBy('delivery_notes.date', 'desc');
    }

    public function headings(): array
    {
        return [
            __('State'),
            __('Reference'),
            __('Batch Code'),
            __('Date'),
            __('Customer'),
            __('Phone'),
            __('Items'),
            __('Picking Sessions Count'),
            __('Picking Session IDs'),
            __('Shop'),
            __('Organisation'),
        ];
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            'D' => NumberFormat::FORMAT_TEXT,
            'E' => NumberFormat::FORMAT_TEXT,
            'F' => NumberFormat::FORMAT_TEXT,
            'G' => NumberFormat::FORMAT_TEXT,
            'H' => NumberFormat::FORMAT_TEXT,
            'I' => NumberFormat::FORMAT_TEXT,
            'J' => NumberFormat::FORMAT_TEXT,
            'K' => NumberFormat::FORMAT_TEXT,
        ];
    }

    public function map($row): array
    {
        return [
            (string)$row->state?->value ?? '',
            (string)$row->reference,
            (string)$this->batchCode->code,
            (string)($row->date ? $row->date->format('Y-m-d') : ''),
            (string)($row->customer_name ?? ''),
            (string)($row->customer_phone ?? ''),
            (string)$row->number_items,
            (string)$row->picking_sessions_count,
            (string)$row->picking_session_ids,
            (string)($row->shop_name ?? ''),
            (string)($row->organisation_name ?? ''),
        ];
    }
}
