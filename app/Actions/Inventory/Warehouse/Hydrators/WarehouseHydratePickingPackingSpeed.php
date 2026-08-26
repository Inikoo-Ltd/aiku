<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 26 Aug 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Inventory\Warehouse\Hydrators;

use App\Models\Inventory\Warehouse;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class WarehouseHydratePickingPackingSpeed implements ShouldBeUnique
{
    use AsAction;

    private const SAMPLE_DAYS = 90;

    private const MAX_PHASE_HOURS = 8;

    private const MINIMUM_SAMPLE_SIZE = 30;

    public function getJobUniqueId(Warehouse $warehouse): string
    {
        return $warehouse->id;
    }

    /**
     * Median seconds spent per SKO, so an order's picking and packing time can be
     * estimated from its SKO count. Phases longer than MAX_PHASE_HOURS are dropped:
     * handling_at marks entry into the handling queue rather than the moment a picker
     * starts, so an order left sitting overnight would otherwise skew the rate.
     */
    public function handle(Warehouse $warehouse): void
    {
        $sample = DB::table('delivery_notes')
            ->where('warehouse_id', $warehouse->id)
            ->whereNull('deleted_at')
            ->where('created_at', '>', now()->subDays(self::SAMPLE_DAYS))
            ->where('total_skos', '>', 0)
            ->whereRaw('picked_at > handling_at')
            ->whereRaw('packed_at > packing_at')
            ->whereRaw('picked_at - handling_at < ?::interval', [self::MAX_PHASE_HOURS.' hours'])
            ->whereRaw('packed_at - packing_at < ?::interval', [self::MAX_PHASE_HOURS.' hours'])
            ->selectRaw('count(*) as sample_size')
            ->selectRaw('percentile_cont(0.5) WITHIN GROUP (ORDER BY extract(epoch from (picked_at - handling_at)) / total_skos) as picking_seconds_per_sko')
            ->selectRaw('percentile_cont(0.5) WITHIN GROUP (ORDER BY extract(epoch from (packed_at - packing_at)) / total_skos) as packing_seconds_per_sko')
            ->first();

        $hasEnoughHistory = $sample->sample_size >= self::MINIMUM_SAMPLE_SIZE;

        $warehouse->stats()->update([
            'picking_seconds_per_sko'           => $hasEnoughHistory ? round((float)$sample->picking_seconds_per_sko, 2) : null,
            'packing_seconds_per_sko'           => $hasEnoughHistory ? round((float)$sample->packing_seconds_per_sko, 2) : null,
            'picking_packing_speed_sample_size' => (int)$sample->sample_size,
        ]);
    }

    public string $commandSignature = 'warehouse:hydrate_picking_packing_speed';

    public function asCommand(): int
    {
        foreach (Warehouse::all() as $warehouse) {
            $this->handle($warehouse);
        }

        return 0;
    }
}
