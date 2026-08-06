<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Aug 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Enums\SysAdmin\Authorisation\WarehousePermissionsEnum;
use App\Models\Inventory\Warehouse;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Picking and packing performance for a warehouse: throughput, the wait between picking and packing, per-person rates, and workload by hour of day. Use breakdown=summary for how the warehouse is flowing, pickers or packers for individual rates, hourly for staffing shape. Only covers work each shop did after it moved to Aiku, and only orders Aiku raised itself: anything picked on paper under the old system is excluded because its timestamps say nothing about how long the work took.')]
#[IsReadOnly]
class WarehousePerformanceTool extends AikuWarehouseTool
{
    protected function permission(): WarehousePermissionsEnum
    {
        return WarehousePermissionsEnum::DISPATCHING_VIEW;
    }

    public function handle(Request $request): Response
    {
        $request->validate([
            'warehouse' => ['required', 'string'],
            'from'      => ['required', 'date'],
            'to'        => ['required', 'date', 'after_or_equal:from'],
            'breakdown' => ['sometimes', 'in:summary,pickers,packers,hourly'],
        ]);

        $warehouse = $this->authorisedWarehouse($request);
        if (!$warehouse) {
            return $this->warehouseNotFoundError($request);
        }

        $from = $request->date('from')->startOfDay();
        $to   = $request->date('to')->endOfDay();

        $payload = [
            'warehouse'      => $warehouse->name,
            'from'           => $from->toDateString(),
            'to'             => $to->toDateString(),
            'measured_since' => '2026-08-06',
        ];

        $payload += match ($request->string('breakdown', 'summary')->toString()) {
            'pickers' => ['pickers' => $this->pickerRates($warehouse, $from, $to)],
            'packers' => ['packers' => $this->packerRates($warehouse, $from, $to)],
            'hourly'  => ['hourly' => $this->hourlyWorkload($warehouse, $from, $to)],
            default   => $this->summary($warehouse, $from, $to),
        };

        return Response::json($payload);
    }

    /**
     * @return array<string, mixed>
     */
    protected function summary(Warehouse $warehouse, $from, $to): array
    {
        $picking = DB::table('pickings')
            ->join('delivery_notes', 'delivery_notes.id', '=', 'pickings.delivery_note_id')
            ->where('delivery_notes.warehouse_id', $warehouse->id)
            ->whereBetween('pickings.last_picked_at', [$from, $to])
            ->selectRaw('count(*) as lines, count(distinct pickings.delivery_note_id) as notes, count(distinct pickings.picker_user_id) as people')
            ->first();

        /*
         * Wait and pack durations are properties of a delivery note, not of a line, so the per-line
         * packings are collapsed to one row per note before the medians are taken.
         */
        $notes = DB::table('packings')
            ->join('delivery_notes', 'delivery_notes.id', '=', 'packings.delivery_note_id')
            ->where('delivery_notes.warehouse_id', $warehouse->id)
            ->whereBetween('packings.done_at', [$from, $to])
            ->groupBy('packings.delivery_note_id')
            ->selectRaw('packings.delivery_note_id, count(*) as lines, min(packings.queued_at) as queued_at, min(packings.packing_at) as packing_at, max(packings.done_at) as done_at');

        $flow = DB::query()->fromSub($notes, 'n')
            ->selectRaw("
                count(*) as notes,
                sum(lines) as lines,
                round(percentile_cont(0.5) within group (order by extract(epoch from (packing_at - queued_at))/60)::numeric, 1) as median_wait_mins,
                round(percentile_cont(0.9) within group (order by extract(epoch from (packing_at - queued_at))/60)::numeric, 1) as p90_wait_mins,
                round(percentile_cont(0.5) within group (order by extract(epoch from (done_at - packing_at))/60)::numeric, 1) as median_pack_mins,
                round(percentile_cont(0.9) within group (order by extract(epoch from (done_at - packing_at))/60)::numeric, 1) as p90_pack_mins
            ")
            ->first();

        return [
            'picking' => [
                'lines'   => (int) ($picking->lines ?? 0),
                'notes'   => (int) ($picking->notes ?? 0),
                'pickers' => (int) ($picking->people ?? 0),
            ],
            'packing' => [
                'lines' => (int) ($flow->lines ?? 0),
                'notes' => (int) ($flow->notes ?? 0),
            ],
            'flow' => [
                'median_wait_mins' => $flow->median_wait_mins,
                'p90_wait_mins'    => $flow->p90_wait_mins,
                'median_pack_mins' => $flow->median_pack_mins,
                'p90_pack_mins'    => $flow->p90_pack_mins,
                'note'             => 'wait is picking finished to packing started, the bottleneck measure; pack is packing started to last line packed',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function pickerRates(Warehouse $warehouse, $from, $to): array
    {
        return DB::table('pickings')
            ->join('delivery_notes', 'delivery_notes.id', '=', 'pickings.delivery_note_id')
            ->leftJoin('users', 'users.id', '=', 'pickings.picker_user_id')
            ->where('delivery_notes.warehouse_id', $warehouse->id)
            ->whereBetween('pickings.last_picked_at', [$from, $to])
            ->groupBy('pickings.picker_user_id', 'users.username', 'users.contact_name')
            ->selectRaw("
                coalesce(users.contact_name, users.username, 'unknown') as picker,
                count(*) as lines,
                sum(pickings.quantity) as units,
                count(distinct pickings.delivery_note_id) as notes,
                count(distinct date_trunc('hour', pickings.last_picked_at)) as active_hours,
                round(count(*)::numeric / greatest(count(distinct date_trunc('hour', pickings.last_picked_at)), 1), 1) as lines_per_active_hour
            ")
            ->orderByDesc('lines')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function packerRates(Warehouse $warehouse, $from, $to): array
    {
        return DB::table('packings')
            ->join('delivery_notes', 'delivery_notes.id', '=', 'packings.delivery_note_id')
            ->leftJoin('users', 'users.id', '=', 'packings.packer_user_id')
            ->where('delivery_notes.warehouse_id', $warehouse->id)
            ->whereBetween('packings.done_at', [$from, $to])
            ->groupBy('packings.packer_user_id', 'users.username', 'users.contact_name')
            ->selectRaw("
                coalesce(users.contact_name, users.username, 'unknown') as packer,
                count(*) as lines,
                sum(packings.quantity) as units,
                count(distinct packings.delivery_note_id) as notes,
                count(*) filter (where packings.data->>'auto_packed' = 'true') as auto_packed_lines,
                count(distinct date_trunc('hour', packings.done_at)) as active_hours,
                round(count(*) filter (where packings.data->>'auto_packed' is null)::numeric / greatest(count(distinct date_trunc('hour', packings.done_at)), 1), 1) as lines_per_active_hour
            ")
            ->orderByDesc('lines')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * Picked and packed lines side by side per hour of day, so a shift that is over or under
     * staffed at a given hour, or a packing bench that never catches up, shows up directly.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function hourlyWorkload(Warehouse $warehouse, $from, $to): array
    {
        $picked = DB::table('pickings')
            ->join('delivery_notes', 'delivery_notes.id', '=', 'pickings.delivery_note_id')
            ->where('delivery_notes.warehouse_id', $warehouse->id)
            ->whereBetween('pickings.last_picked_at', [$from, $to])
            ->groupByRaw("extract(hour from pickings.last_picked_at)")
            ->selectRaw("extract(hour from pickings.last_picked_at) as hour, count(*) as lines, count(distinct pickings.picker_user_id) as people")
            ->pluck('lines', 'hour');

        $pickers = DB::table('pickings')
            ->join('delivery_notes', 'delivery_notes.id', '=', 'pickings.delivery_note_id')
            ->where('delivery_notes.warehouse_id', $warehouse->id)
            ->whereBetween('pickings.last_picked_at', [$from, $to])
            ->groupByRaw("extract(hour from pickings.last_picked_at)")
            ->selectRaw("extract(hour from pickings.last_picked_at) as hour, count(distinct pickings.picker_user_id) as people")
            ->pluck('people', 'hour');

        $packed = DB::table('packings')
            ->join('delivery_notes', 'delivery_notes.id', '=', 'packings.delivery_note_id')
            ->where('delivery_notes.warehouse_id', $warehouse->id)
            ->whereBetween('packings.done_at', [$from, $to])
            ->groupByRaw("extract(hour from packings.done_at)")
            ->selectRaw("extract(hour from packings.done_at) as hour, count(*) as lines, count(distinct packings.packer_user_id) as people")
            ->pluck('lines', 'hour');

        $packers = DB::table('packings')
            ->join('delivery_notes', 'delivery_notes.id', '=', 'packings.delivery_note_id')
            ->where('delivery_notes.warehouse_id', $warehouse->id)
            ->whereBetween('packings.done_at', [$from, $to])
            ->groupByRaw("extract(hour from packings.done_at)")
            ->selectRaw("extract(hour from packings.done_at) as hour, count(distinct packings.packer_user_id) as people")
            ->pluck('people', 'hour');

        $hours = collect($picked->keys())->merge($packed->keys())->unique()->sort()->values();

        return $hours->map(fn ($hour) => [
            'hour'         => (int) $hour,
            'lines_picked' => (int) ($picked[$hour] ?? 0),
            'pickers'      => (int) ($pickers[$hour] ?? 0),
            'lines_packed' => (int) ($packed[$hour] ?? 0),
            'packers'      => (int) ($packers[$hour] ?? 0),
        ])->all();
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'warehouse' => $schema->string()->description('Warehouse slug or code')->required(),
            'from'      => $schema->string()->description('Start date (Y-m-d)')->required(),
            'to'        => $schema->string()->description('End date (Y-m-d), inclusive')->required(),
            'breakdown' => $schema->string()->description('summary (default), pickers, packers or hourly'),
        ];
    }
}
