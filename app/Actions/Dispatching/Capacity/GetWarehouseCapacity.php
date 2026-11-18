<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Capacity;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWarehouseCapacity
{
    use AsObject;

    public const int WINDOW_DAYS = 14;
    public const int BACKLOG_CLEARING_DAYS = 5;
    public const int MIN_NOTES_FOR_A_WORKER_DAY = 5;
    public const int MIN_WORKER_DAYS = 10;
    public const string DROPSHIPPING = 'dropshipping';
    public const string WHOLESALE = 'wholesale';
    public const array STREAMS = [self::DROPSHIPPING, self::WHOLESALE];

    public const array WAITING_TO_BE_PICKED = [
        DeliveryNoteStateEnum::UNASSIGNED,
        DeliveryNoteStateEnum::QUEUED,
        DeliveryNoteStateEnum::HANDLING,
        DeliveryNoteStateEnum::HANDLING_BLOCKED,
    ];

    public const array WAITING_TO_BE_PACKED = [
        DeliveryNoteStateEnum::PICKED,
        DeliveryNoteStateEnum::PACKING,
    ];

    public const array WAITING_TO_BE_SHIPPED = [
        DeliveryNoteStateEnum::PACKED,
        DeliveryNoteStateEnum::FINALISED,
    ];

    /**
     * @return array<string, mixed>
     */
    public function handle(Warehouse $warehouse, string $stream, ?Carbon $now = null): array
    {
        return $this->assess([
            'warehouse_id' => $warehouse->id,
            'code'         => $warehouse->code,
            'name'         => $warehouse->name,
            'stream'       => $stream,
            ...$this->metrics($warehouse, $stream, $now ?? now(), $warehouse->organisation->timezone?->name ?? 'UTC'),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forOrganisation(Organisation $organisation, ?Carbon $now = null): array
    {
        return $organisation->warehouses()->orderBy('code')->get()
            ->flatMap(fn (Warehouse $warehouse) => array_map(fn (string $stream) => $this->handle($warehouse, $stream, $now), self::STREAMS))
            ->filter(fn (array $row) => $row['in_per_day'] > 0 || $row['out_per_day'] > 0 || $row['backlog'] > 0)
            ->values()
            ->all();
    }

    public static function lateCutoff(Carbon $now, string $timezone): Carbon
    {
        $cutoff = $now->copy()->setTimezone($timezone)->subDay();
        while ($cutoff->isWeekend()) {
            $cutoff->subDay();
        }

        return $cutoff;
    }

    /**
     * @param array<int, DeliveryNoteStateEnum> $states
     */
    private static function stateList(array $states): string
    {
        return implode(', ', array_map(fn (DeliveryNoteStateEnum $state) => "'$state->value'", $states));
    }

    /**
     * @return array<string, mixed>
     */
    private function metrics(Warehouse $warehouse, string $stream, Carbon $now, string $timezone): array
    {
        $from                = $now->copy()->subDays(self::WINDOW_DAYS);
        $lateCutoff          = self::lateCutoff($now, $timezone)->utc();
        $dropshippingShopIds = DB::table('shops')->whereRaw(Shop::dispatchingChannelSql().' = ?', [ShopTypeEnum::DROPSHIPPING->value])->pluck('id');
        $notes               = fn () => DB::table('delivery_notes')->where('warehouse_id', $warehouse->id)->whereNull('deleted_at')
            ->when(
                $stream === self::DROPSHIPPING,
                fn ($query) => $query->whereIn('shop_id', $dropshippingShopIds),
                fn ($query) => $query->where(fn ($query) => $query->whereNotIn('shop_id', $dropshippingShopIds)->orWhereNull('shop_id'))
            );

        $in  = $notes()->whereBetween('date', [$from, $now])->count();
        $out = $notes()->whereBetween('dispatched_at', [$from, $now])
            ->selectRaw('count(*) as dispatched, percentile_cont(0.5) within group (order by extract(epoch from dispatched_at - date) / 3600) as median_hours')
            ->first();

        $toPick  = self::stateList(self::WAITING_TO_BE_PICKED);
        $toPack  = self::stateList(self::WAITING_TO_BE_PACKED);
        $backlog = $notes()
            ->whereRaw('state in ('.self::stateList([...self::WAITING_TO_BE_PICKED, ...self::WAITING_TO_BE_PACKED, ...self::WAITING_TO_BE_SHIPPED]).')')
            ->selectRaw(
                "count(*) as total, count(*) filter (where date < ?) as late, count(*) filter (where date < ? and state in ($toPick)) as late_to_pick, count(*) filter (where date < ? and state in ($toPack)) as late_to_pack",
                [$lateCutoff, $lateCutoff, $lateCutoff]
            )
            ->first();

        $pickerDays  = $this->workerDays($notes, 'picker_user_id', 'picked_at', $from, $now, $timezone);
        $packerDays  = $this->workerDays($notes, 'packer_user_id', 'packed_at', $from, $now, $timezone);
        $workingDays = $pickerDays->merge($packerDays)->pluck('day')->unique()->count() ?: self::WINDOW_DAYS;
        $perWorker   = fn (Collection $days) => $days->count() >= self::MIN_WORKER_DAYS ? round($days->sum('notes') / $days->count(), 1) : null;

        return [
            'in_per_day'           => round($in / $workingDays, 1),
            'out_per_day'          => round(((int) $out->dispatched) / $workingDays, 1),
            'median_hours'         => $out->median_hours === null ? null : round((float) $out->median_hours, 1),
            'backlog'              => (int) $backlog->total,
            'late_backlog'         => (int) $backlog->late,
            'late_to_pick'         => (int) $backlog->late_to_pick,
            'late_to_pack'         => (int) $backlog->late_to_pack,
            'working_days'         => $workingDays,
            'pickers'              => $pickerDays->pluck('user_id')->unique()->count(),
            'packers'              => $packerDays->pluck('user_id')->unique()->count(),
            'picks_per_picker_day' => $perWorker($pickerDays),
            'packs_per_packer_day' => $perWorker($packerDays),
        ];
    }

    private function workerDays(Closure $notes, string $userColumn, string $timestampColumn, Carbon $from, Carbon $now, string $timezone): Collection
    {
        return DB::query()->fromSub(
            $notes()->whereBetween($timestampColumn, [$from, $now])->whereNotNull($userColumn)
                ->selectRaw("$userColumn as user_id, ($timestampColumn at time zone ?)::date as day", [$timezone]),
            'work'
        )
            ->groupBy('user_id', 'day')
            ->havingRaw('count(*) >= ?', [self::MIN_NOTES_FOR_A_WORKER_DAY])
            ->selectRaw('user_id, day, count(*) as notes')
            ->get();
    }

    /**
     * @param array{in_per_day: float, out_per_day: float, late_backlog: int, late_to_pick: int, late_to_pack: int, picks_per_picker_day: float|null, packs_per_packer_day: float|null} $row
     *
     * @return array<string, mixed>
     */
    public function assess(array $row): array
    {
        $gap       = round($row['in_per_day'] - $row['out_per_day'], 1);
        $outPerDay = max($row['out_per_day'], 1);
        $lateDays  = $row['late_backlog'] / $outPerDay;
        $gapShare  = $gap / $outPerDay;

        $status = match (true) {
            $row['out_per_day'] <= 0                                   => 'no_data',
            $lateDays >= 1 || ($gapShare >= 0.10 && $lateDays >= 0.5) => 'over',
            $lateDays >= 0.5 || $gapShare >= 0.05                      => 'at_capacity',
            default                                                    => 'ok',
        };

        $isShort    = in_array($status, ['over', 'at_capacity']);
        $keepUp     = max($gap, 0);
        $extraPicks = $keepUp + $row['late_to_pick'] / self::BACKLOG_CLEARING_DAYS;
        $extraPacks = $keepUp + ($row['late_to_pick'] + $row['late_to_pack']) / self::BACKLOG_CLEARING_DAYS;
        $workersFor = fn (float $notesPerDay, ?float $perWorker) => $isShort && $perWorker ? (int) max(0, ceil($notesPerDay / $perWorker - 0.2)) : null;

        return [
            ...$row,
            'status'                => $status,
            'gap_per_day'           => $gap,
            'bottleneck'            => $row['late_to_pick'] >= $row['late_to_pack'] ? 'picking' : 'packing',
            'extra_picks_per_day'   => $isShort ? (int) ceil($extraPicks) : 0,
            'extra_packs_per_day'   => $isShort ? (int) ceil($extraPacks) : 0,
            'extra_pickers'         => $workersFor($extraPicks, $row['picks_per_picker_day']),
            'extra_packers'         => $workersFor($extraPacks, $row['packs_per_packer_day']),
            'backlog_clearing_days' => self::BACKLOG_CLEARING_DAYS,
        ];
    }
}
