<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 20 Jun 2023 15:21:51 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Exports\Inventory;

use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockFamily;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class OrgStocksExport implements FromQuery, WithMapping, ShouldAutoSize, WithHeadings
{
    public function __construct(private readonly Organisation|OrgStockFamily $parent)
    {
    }

    public function query(): Builder
    {
        $batchesOnHand = DB::table('org_stock_movement_batches')
            ->groupBy('org_stock_id', 'batch_code_id', 'location_id')
            ->havingRaw('sum(quantity) > 0.000001')
            ->selectRaw('org_stock_id, batch_code_id, sum(quantity) as quantity');

        $bestBefore = DB::query()->fromSub($batchesOnHand, 'on_hand')
            ->join('batch_codes', 'batch_codes.id', '=', 'on_hand.batch_code_id')
            ->groupBy('on_hand.org_stock_id')
            ->selectRaw("
                on_hand.org_stock_id,
                min(batch_codes.expiry_date) as earliest_best_before,
                sum(on_hand.quantity) as quantity_in_batches,
                coalesce(sum(on_hand.quantity) filter (where batch_codes.expiry_date < current_date), 0) as quantity_expired,
                coalesce(sum(on_hand.quantity) filter (where batch_codes.expiry_date >= current_date and batch_codes.expiry_date <= current_date + 30), 0) as quantity_expiring_30_days,
                coalesce(sum(on_hand.quantity) filter (where batch_codes.expiry_date >= current_date and batch_codes.expiry_date <= current_date + 90), 0) as quantity_expiring_90_days
            ");

        return OrgStock::query()
            ->with('orgStockFamily')
            ->leftJoinSub($bestBefore, 'best_before', 'best_before.org_stock_id', '=', 'org_stocks.id')
            ->select('org_stocks.*', 'best_before.earliest_best_before', 'best_before.quantity_in_batches', 'best_before.quantity_expired', 'best_before.quantity_expiring_30_days', 'best_before.quantity_expiring_90_days')
            ->when(
                $this->parent instanceof OrgStockFamily,
                fn (Builder $query) => $query->where('org_stocks.org_stock_family_id', $this->parent->id),
                fn (Builder $query) => $query->where('org_stocks.organisation_id', $this->parent->id)
            )
            ->orderBy('org_stocks.code');
    }

    /** @var OrgStock $row */
    public function map($row): array
    {
        return [
            $row->code,
            $row->name,
            $row->orgStockFamily?->code,
            $row->state->value,
            $row->quantity_in_locations,
            $row->quantity_available,
            $row->sku_value,
            $row->value_in_locations,
            $row->earliest_best_before,
            (float) $row->quantity_expired,
            (float) $row->quantity_expiring_30_days,
            (float) $row->quantity_expiring_90_days,
            max(0, round((float) $row->quantity_in_locations - (float) $row->quantity_in_batches, 6)),
            $row->activated_in_organisation_at,
            $row->discontinuing_in_organisation_at,
            $row->discontinued_in_organisation_at,
            $row->created_at,
        ];
    }

    public function headings(): array
    {
        return [
            'Code',
            'Name',
            'Family',
            'State',
            'Quantity in Locations',
            'Quantity Available',
            'SKO Value',
            'Value in Locations',
            'Earliest Best-before',
            'Expired',
            'Expiring within 30 days',
            'Expiring within 90 days',
            'Without Batch',
            'Activated At',
            'Discontinuing At',
            'Discontinued At',
            'Created At',
        ];
    }
}
