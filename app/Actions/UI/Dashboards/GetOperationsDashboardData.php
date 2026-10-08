<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Dashboards;

use App\Actions\Catalogue\Shop\SalesTarget\GetShopMonthSalesTarget;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The Operations tab of the dashboard: what has to ship, what is stuck, who is working and what is
 * arriving, per warehouse in the warehouse's own time zone. Everything counts delivery notes, the unit
 * the warehouse works on, so replacements are included; the Sales tab counts orders.
 */
class GetOperationsDashboardData
{
    use AsAction;

    public const string SETTINGS_KEY = 'operations_dashboard_filters';

    public const array PERIODS = [1, 7, 30];

    public const array THRESHOLDS = [
        'urgent_minutes'          => 60,
        'blocked_amber_count'     => 10,
        'blocked_red_hours'       => 24,
        'cs_red_hours'            => 24,
        'idle_minutes'            => 15,
        'orders_in_staffing_rate' => 1.25,
    ];

    public const array STAGES = [
        'unassigned' => [DeliveryNoteStateEnum::UNASSIGNED],
        'queued'     => [DeliveryNoteStateEnum::QUEUED],
        'picking'    => [DeliveryNoteStateEnum::HANDLING],
        'blocked'    => [DeliveryNoteStateEnum::HANDLING_BLOCKED],
        'packing'    => [DeliveryNoteStateEnum::PICKED, DeliveryNoteStateEnum::PACKING],
        'packed'     => [DeliveryNoteStateEnum::PACKED],
        'finalised'  => [DeliveryNoteStateEnum::FINALISED],
    ];

    private const array STAGE_ROUTES = [
        'unassigned' => 'unassigned.delivery-notes',
        'queued'     => 'queued.delivery-notes',
        'picking'    => 'handling.delivery-notes',
        'blocked'    => 'handling-blocked.delivery-notes',
        'packing'    => 'picked.delivery-notes',
        'packed'     => 'packed.delivery-notes',
        'finalised'  => 'finalised.delivery-notes',
        'dispatched' => 'dispatched.delivery-notes',
    ];

    private const array OPEN_STOCK_DELIVERY_STATES = [
        StockDeliveryStateEnum::CONFIRMED,
        StockDeliveryStateEnum::READY_TO_SHIP,
        StockDeliveryStateEnum::DISPATCHED,
        StockDeliveryStateEnum::RECEIVED,
        StockDeliveryStateEnum::CHECKED,
        StockDeliveryStateEnum::BOOKING_IN,
    ];

    /**
     * Warehouses of shop organisations the user works in: everyone who sees the group sales sees them all.
     *
     * @return Collection<int, Warehouse>
     */
    public static function warehousesFor(User $user): Collection
    {
        $warehouses = Warehouse::query()
            ->whereNull('warehouses.deleted_at')
            ->join('organisations', 'organisations.id', 'warehouses.organisation_id')
            ->where('organisations.type', OrganisationTypeEnum::SHOP->value)
            ->where('warehouses.group_id', $user->group_id)
            ->select('warehouses.*')
            ->orderBy('warehouses.id')
            ->with('organisation.currency', 'organisation.timezone')
            ->get();

        if ($user->canViewSales() || $user->authTo('group-overview')) {
            return $warehouses;
        }

        $authorised = $user->authorisedWarehouses()->pluck('warehouses.id')->all();

        return $warehouses->filter(fn (Warehouse $warehouse) => in_array($warehouse->id, $authorised) && self::worksInWarehouse($user, $warehouse))->values();
    }

    public static function worksInWarehouse(User $user, Warehouse $warehouse): bool
    {
        return $user->authTo(collect(['dispatching', 'incoming', 'fulfilment', 'stocks', 'locations'])
            ->flatMap(fn (string $area) => ["$area.$warehouse->id", "$area.$warehouse->id.view", "supervisor-$area.$warehouse->id"])
            ->all());
    }

    /**
     * Goods in, goods out and fulfilment staff open the dashboard on Operations; directors on Sales.
     */
    public static function isOperationsLanding(User $user): bool
    {
        if ($user->authTo(['group-overview'])) {
            return false;
        }

        $warehouseIds = Warehouse::where('group_id', $user->group_id)->pluck('id');

        return $user->authTo($warehouseIds->flatMap(fn (int $id) => collect(['incoming', 'dispatching', 'fulfilment'])
            ->flatMap(fn (string $area) => ["$area.$id", "supervisor-$area.$id"]))->all());
    }

    /**
     * @param  array{warehouse?: int|null, channel?: string|null, period?: int|null}  $filters
     */
    public function handle(User $user, array $filters): array
    {
        $allowed   = self::warehousesFor($user);
        $filters   = $this->cleanFilters($filters, $allowed);
        $selected  = $filters['warehouse'] ? $allowed->where('id', $filters['warehouse'])->values() : $allowed;
        $withValue = $user->canViewSales();
        $group     = group();

        $perWarehouse = $selected->mapWithKeys(fn (Warehouse $warehouse) => [
            $warehouse->id => Cache::remember(
                "operations-dashboard:$warehouse->id:".($filters['channel'] ?? 'all').':'.$filters['period'],
                now()->addSeconds(55),
                fn () => $this->warehouseFigures($warehouse, $filters['channel'], $filters['period'])
            ),
        ]);

        $figures = $this->combine($selected, $perWarehouse, $filters['channel']);

        return [
            'generated_at'  => now()->toIso8601String(),
            'filters'       => [
                ...$filters,
                'options' => [
                    'warehouses' => $allowed->map(fn (Warehouse $warehouse) => [
                        'id'    => $warehouse->id,
                        'label' => $warehouse->name.' · '.$warehouse->organisation->name,
                    ])->values()->all(),
                    'channels'   => collect(ShopTypeEnum::cases())->map(fn (ShopTypeEnum $type) => ['value' => $type->value, 'label' => $this->channelLabel($type)])->all(),
                    'periods'    => self::PERIODS,
                ],
            ],
            'warehouses'    => $selected->map(fn (Warehouse $warehouse) => [
                'id'            => $warehouse->id,
                'name'          => $warehouse->name,
                'organisation'  => $warehouse->organisation->name,
                'timezone'      => $warehouse->organisation->timezone?->name ?? 'UTC',
                'local_time'    => now($warehouse->organisation->timezone?->name ?? 'UTC')->format('H:i'),
                'currency_code' => $warehouse->organisation->currency->code,
            ])->values()->all(),
            'can_see_value' => $withValue,
            'thresholds'    => self::THRESHOLDS,
            ...($withValue ? $figures : $this->withoutValues($figures)),
            'sales'         => $this->salesCard($group, $selected, $withValue),
        ];
    }

    /**
     * @param  Collection<int, Warehouse>  $allowed
     * @return array{warehouse: int|null, channel: string|null, period: int}
     */
    private function cleanFilters(array $filters, Collection $allowed): array
    {
        $warehouse = (int) Arr::get($filters, 'warehouse');
        $channel   = Arr::get($filters, 'channel');
        $period    = (int) Arr::get($filters, 'period');

        return [
            'warehouse' => $allowed->contains('id', $warehouse) ? $warehouse : null,
            'channel'   => ShopTypeEnum::tryFrom((string) $channel)?->value,
            'period'    => in_array($period, self::PERIODS, true) ? $period : 30,
        ];
    }

    private function channelLabel(ShopTypeEnum $type): string
    {
        return match ($type) {
            ShopTypeEnum::B2B          => __('Trade'),
            ShopTypeEnum::B2C          => __('Retail'),
            ShopTypeEnum::DROPSHIPPING => __('Dropshipping'),
            ShopTypeEnum::EXTERNAL     => __('Marketplace'),
            ShopTypeEnum::FULFILMENT   => __('Fulfilment client'),
        };
    }

    private function deliveryNotes(Warehouse $warehouse, ?string $channel): Builder
    {
        return DB::table('delivery_notes')
            ->where('delivery_notes.warehouse_id', $warehouse->id)
            ->whereNull('delivery_notes.deleted_at')
            ->when($channel, fn (Builder $query) => $query->where('delivery_notes.shop_type', $channel));
    }

    /**
     * @param  array<int, DeliveryNoteStateEnum>  $states
     * @return array<int, string>
     */
    private function values(array $states): array
    {
        return array_map(fn ($state) => $state->value, $states);
    }

    private function openStates(): array
    {
        return $this->values(array_merge(...array_values(self::STAGES)));
    }

    private function warehouseFigures(Warehouse $warehouse, ?string $channel, int $period): array
    {
        $timezone   = $warehouse->organisation->timezone?->name ?? 'UTC';
        $now        = now($timezone);
        $todayStart = $now->copy()->startOfDay();

        return [
            'pipeline'        => $this->pipeline($warehouse, $channel, $todayStart, $now),
            'attention'       => $this->attention($warehouse, $channel, $now),
            'waiting_split'   => $this->waitingSplit($warehouse, $channel),
            'age_buckets'     => $this->ageBuckets($warehouse, $channel, $now),
            'time_to_dispatch' => $this->timeToDispatch($warehouse, $channel, $period, $timezone, $now),
            'people'          => $this->people($warehouse, $channel, $todayStart, $now),
            'goods_in'        => $this->goodsIn($warehouse, $timezone, $now),
            'stock'           => $this->stock($warehouse, $channel),
            'returns'         => $this->returns($warehouse, $now),
        ];
    }

    private function stageEnteredExpression(): string
    {
        return "case delivery_notes.state
            when 'queued' then coalesce(delivery_notes.queued_at, delivery_notes.date)
            when 'handling' then coalesce(delivery_notes.handling_at, delivery_notes.date)
            when 'handling_blocked' then coalesce(delivery_notes.handling_blocked_at, delivery_notes.date)
            when 'picked' then coalesce(delivery_notes.picked_at, delivery_notes.date)
            when 'packing' then coalesce(delivery_notes.packing_at, delivery_notes.picked_at, delivery_notes.date)
            when 'packed' then coalesce(delivery_notes.packed_at, delivery_notes.date)
            when 'finalised' then coalesce(delivery_notes.finalised_at, delivery_notes.date)
            else delivery_notes.date end";
    }

    private function orderValueJoin(Builder $query): Builder
    {
        return $query->leftJoinLateral(
            DB::table('delivery_note_order')
                ->join('orders', 'orders.id', 'delivery_note_order.order_id')
                ->whereColumn('delivery_note_order.delivery_note_id', 'delivery_notes.id')
                ->whereRaw("delivery_notes.type = 'order'")
                ->selectRaw('sum(orders.org_net_amount) as org_amount, sum(orders.grp_net_amount) as grp_amount'),
            'order_value'
        );
    }

    private function pipeline(Warehouse $warehouse, ?string $channel, Carbon $todayStart, Carbon $now): array
    {
        $rows = $this->orderValueJoin($this->deliveryNotes($warehouse, $channel))
            ->whereIn('delivery_notes.state', $this->openStates())
            ->selectRaw('delivery_notes.state, count(*) as total, count(*) filter (where delivery_notes.type = \'replacement\') as replacements')
            ->selectRaw('count(*) filter (where delivery_notes.is_premium_dispatch) as premium')
            ->selectRaw('min('.$this->stageEnteredExpression().') as since')
            ->selectRaw('coalesce(sum(order_value.org_amount), 0) as org_amount, coalesce(sum(order_value.grp_amount), 0) as grp_amount')
            ->groupBy('delivery_notes.state')
            ->get()
            ->keyBy('state');

        $stages = [];
        foreach (self::STAGES as $stage => $states) {
            $stateRows = $rows->only($this->values($states));
            $since     = $stateRows->pluck('since')->filter()->min();

            $stages[$stage] = [
                'count'        => (int) $stateRows->sum('total'),
                'replacements' => (int) $stateRows->sum('replacements'),
                'premium'      => (int) $stateRows->sum('premium'),
                'oldest'       => $since ? Carbon::parse($since)->toIso8601String() : null,
                'org_amount'   => round((float) $stateRows->sum('org_amount'), 2),
                'grp_amount'   => round((float) $stateRows->sum('grp_amount'), 2),
            ];
        }

        $stages['dispatched'] = $this->dispatchedOn($warehouse, $channel, $todayStart, $now);
        $stages['dispatched']['yesterday']        = $this->dispatchedOn($warehouse, $channel, $todayStart->copy()->subDay(), $now->copy()->subDay());
        $stages['dispatched']['same_day_last_week'] = $this->dispatchedOn($warehouse, $channel, $todayStart->copy()->subWeek(), $now->copy()->subWeek());

        return $stages;
    }

    /**
     * Delivery notes dispatched from the start of a day up to the same time of day, so today is
     * compared with yesterday by this time, not with all of yesterday.
     */
    private function dispatchedOn(Warehouse $warehouse, ?string $channel, Carbon $from, Carbon $to): array
    {
        $row = $this->orderValueJoin($this->deliveryNotes($warehouse, $channel))
            ->where('delivery_notes.state', DeliveryNoteStateEnum::DISPATCHED->value)
            ->where('delivery_notes.dispatched_at', '>=', $from->copy()->utc())
            ->where('delivery_notes.dispatched_at', '<=', $to->copy()->utc())
            ->selectRaw('count(*) as total, coalesce(sum(delivery_notes.number_items), 0) as lines')
            ->selectRaw("coalesce(sum(case when jsonb_typeof(delivery_notes.parcels) = 'array' then jsonb_array_length(delivery_notes.parcels) else 0 end), 0) as parcels")
            ->selectRaw('coalesce(sum(order_value.org_amount), 0) as org_amount, coalesce(sum(order_value.grp_amount), 0) as grp_amount')
            ->first();

        return [
            'count'      => (int) $row->total,
            'lines'      => (int) $row->lines,
            'parcels'    => (int) $row->parcels,
            'org_amount' => round((float) $row->org_amount, 2),
            'grp_amount' => round((float) $row->grp_amount, 2),
        ];
    }

    private function attention(Warehouse $warehouse, ?string $channel, Carbon $now): array
    {
        $urgent = $this->deliveryNotes($warehouse, $channel)
            ->whereIn('delivery_notes.state', $this->values([DeliveryNoteStateEnum::UNASSIGNED, DeliveryNoteStateEnum::QUEUED]))
            ->where('delivery_notes.is_premium_dispatch', true)
            ->selectRaw('count(*) as total, min(delivery_notes.date) as oldest')
            ->first();

        $blocked = $this->deliveryNotes($warehouse, $channel)
            ->where('delivery_notes.state', DeliveryNoteStateEnum::HANDLING_BLOCKED->value)
            ->selectRaw('count(*) as total, min(coalesce(delivery_notes.handling_blocked_at, delivery_notes.date)) as oldest')
            ->selectRaw('count(*) filter (where delivery_notes.number_items_waiting_warehouse > 0) as stock')
            ->selectRaw('count(*) filter (where delivery_notes.number_items_waiting_crm > 0 and coalesce(delivery_notes.number_items_waiting_warehouse, 0) = 0) as customer_service')
            ->selectRaw('count(*) filter (where coalesce(delivery_notes.number_items_waiting_warehouse, 0) = 0 and coalesce(delivery_notes.number_items_waiting_crm, 0) = 0) as other')
            ->first();

        $customerService = $this->deliveryNotes($warehouse, $channel)
            ->whereIn('delivery_notes.state', $this->values([DeliveryNoteStateEnum::HANDLING, DeliveryNoteStateEnum::HANDLING_BLOCKED]))
            ->where('delivery_notes.number_items_waiting_crm', '>', 0)
            ->selectRaw('count(*) as total, min(coalesce(delivery_notes.handling_blocked_at, delivery_notes.handling_at, delivery_notes.date)) as oldest')
            ->first();

        $outOfStock = DB::table('delivery_note_items')
            ->join('delivery_notes', 'delivery_notes.id', 'delivery_note_items.delivery_note_id')
            ->join('org_stocks', 'org_stocks.id', 'delivery_note_items.org_stock_id')
            ->where('delivery_notes.warehouse_id', $warehouse->id)
            ->whereNull('delivery_notes.deleted_at')
            ->when($channel, fn (Builder $query) => $query->where('delivery_notes.shop_type', $channel))
            ->whereIn('delivery_notes.state', $this->values([DeliveryNoteStateEnum::UNASSIGNED, DeliveryNoteStateEnum::QUEUED, DeliveryNoteStateEnum::HANDLING, DeliveryNoteStateEnum::HANDLING_BLOCKED]))
            ->whereRaw('coalesce(delivery_note_items.quantity_picked, 0) < delivery_note_items.quantity_required')
            ->where('org_stocks.quantity_in_locations', '<=', 0)
            ->selectRaw('count(distinct delivery_note_items.org_stock_id) as org_stocks, count(distinct delivery_note_items.delivery_note_id) as delivery_notes')
            ->first();

        $overdue = $this->openStockDeliveries($warehouse)
            ->whereIn('stock_deliveries.state', $this->values([StockDeliveryStateEnum::CONFIRMED, StockDeliveryStateEnum::READY_TO_SHIP, StockDeliveryStateEnum::DISPATCHED]))
            ->whereRaw($this->etaExpression().' < ?', [$now->toDateString()])
            ->selectRaw('count(*) as total, min('.$this->etaExpression().') as oldest')
            ->first();

        $negative = DB::table('location_org_stocks')
            ->join('locations', 'locations.id', 'location_org_stocks.location_id')
            ->where('locations.warehouse_id', $warehouse->id)
            ->whereNull('locations.deleted_at')
            ->where('location_org_stocks.quantity', '<', 0)
            ->count();

        $stats = DB::table('warehouse_stats')->where('warehouse_id', $warehouse->id)->first();

        return [
            'urgent'           => ['count' => (int) $urgent->total, 'oldest' => $this->iso($urgent->oldest)],
            'at_risk'          => ['count' => null],
            'blocked'          => [
                'count'   => (int) $blocked->total,
                'oldest'  => $this->iso($blocked->oldest),
                'reasons' => ['stock' => (int) $blocked->stock, 'customer_service' => (int) $blocked->customer_service, 'other' => (int) $blocked->other],
            ],
            'customer_service' => ['count' => (int) $customerService->total, 'oldest' => $this->iso($customerService->oldest)],
            'out_of_stock'     => ['count' => (int) $outOfStock->org_stocks, 'delivery_notes' => (int) $outOfStock->delivery_notes],
            'replenishment'    => ['count' => (int) (($stats->number_org_stocks_replenishments_wholesale ?? 0) + ($stats->number_org_stocks_replenishments_dropshipping ?? 0))],
            'overdue'          => ['count' => (int) $overdue->total, 'oldest_eta' => $overdue->oldest],
            'stock_errors'     => ['count' => $negative, 'negative' => $negative],
        ];
    }

    /**
     * Whether each delivery note waiting to be picked could be picked now with the stock on the
     * shelves: all of it, part of it, or none of it. Other orders asking for the same stock are
     * not netted off.
     */
    private function waitingSplit(Warehouse $warehouse, ?string $channel): array
    {
        $perDeliveryNote = DB::table('delivery_note_items')
            ->join('delivery_notes', 'delivery_notes.id', 'delivery_note_items.delivery_note_id')
            ->leftJoin('org_stocks', 'org_stocks.id', 'delivery_note_items.org_stock_id')
            ->where('delivery_notes.warehouse_id', $warehouse->id)
            ->whereNull('delivery_notes.deleted_at')
            ->when($channel, fn (Builder $query) => $query->where('delivery_notes.shop_type', $channel))
            ->whereIn('delivery_notes.state', $this->values([DeliveryNoteStateEnum::UNASSIGNED, DeliveryNoteStateEnum::QUEUED]))
            ->groupBy('delivery_notes.id', 'delivery_notes.is_premium_dispatch')
            ->selectRaw('delivery_notes.is_premium_dispatch, bool_and(coalesce(org_stocks.quantity_in_locations, 0) >= delivery_note_items.quantity_required) as all_in_stock, bool_or(coalesce(org_stocks.quantity_in_locations, 0) > 0) as any_in_stock');

        $row = DB::query()->fromSub($perDeliveryNote, 'waiting')
            ->selectRaw('count(*) filter (where all_in_stock) as pickable, count(*) filter (where not all_in_stock and any_in_stock) as partly, count(*) filter (where not any_in_stock) as no_stock')
            ->selectRaw('count(*) filter (where is_premium_dispatch) as premium, count(*) filter (where not is_premium_dispatch) as normal')
            ->first();

        return [
            'pickable' => (int) $row->pickable,
            'partly'   => (int) $row->partly,
            'no_stock' => (int) $row->no_stock,
            'premium'  => (int) $row->premium,
            'normal'   => (int) $row->normal,
        ];
    }

    private function ageBuckets(Warehouse $warehouse, ?string $channel, Carbon $now): array
    {
        $utcNow = $now->copy()->utc();
        $row    = $this->deliveryNotes($warehouse, $channel)
            ->whereIn('delivery_notes.state', $this->openStates())
            ->selectRaw('count(*) filter (where delivery_notes.date > ?) as under_4h', [$utcNow->copy()->subHours(4)])
            ->selectRaw('count(*) filter (where delivery_notes.date <= ? and delivery_notes.date > ?) as h4_24', [$utcNow->copy()->subHours(4), $utcNow->copy()->subDay()])
            ->selectRaw('count(*) filter (where delivery_notes.date <= ? and delivery_notes.date > ?) as d1_2', [$utcNow->copy()->subDay(), $utcNow->copy()->subDays(2)])
            ->selectRaw('count(*) filter (where delivery_notes.date <= ?) as over_2d', [$utcNow->copy()->subDays(2)])
            ->first();

        return [
            'under_4h' => (int) $row->under_4h,
            'h4_24'    => (int) $row->h4_24,
            'd1_2'     => (int) $row->d1_2,
            'over_2d'  => (int) $row->over_2d,
        ];
    }

    /**
     * Time from the delivery note reaching the warehouse to its dispatch, for orders (not
     * replacements) dispatched in the period; same day is judged on the warehouse's calendar.
     */
    private function timeToDispatch(Warehouse $warehouse, ?string $channel, int $period, string $timezone, Carbon $now): array
    {
        $row = $this->deliveryNotes($warehouse, $channel)
            ->where('delivery_notes.state', DeliveryNoteStateEnum::DISPATCHED->value)
            ->where('delivery_notes.type', 'order')
            ->where('delivery_notes.dispatched_at', '>=', $now->copy()->startOfDay()->subDays($period - 1)->utc())
            ->whereColumn('delivery_notes.dispatched_at', '>=', 'delivery_notes.date')
            ->selectRaw('count(*) as total')
            ->selectRaw('percentile_cont(0.5) within group (order by extract(epoch from delivery_notes.dispatched_at - delivery_notes.date)) as median')
            ->selectRaw('percentile_cont(0.9) within group (order by extract(epoch from delivery_notes.dispatched_at - delivery_notes.date)) as p90')
            ->selectRaw('count(*) filter (where (delivery_notes.dispatched_at at time zone ?)::date = (delivery_notes.date at time zone ?)::date) as same_day', [$timezone, $timezone])
            ->first();

        $total = (int) $row->total;

        return [
            'period'          => $period,
            'dispatched'      => $total,
            'median_seconds'  => $row->median === null ? null : (int) $row->median,
            'p90_seconds'     => $row->p90 === null ? null : (int) $row->p90,
            'same_day_percent' => $total ? round(100 * $row->same_day / $total, 1) : null,
        ];
    }

    /**
     * Team figures only: named figures per picker or packer wait for HR approval in SK and ES.
     * Each picked line is a picking row with its picker and time; packing is read from the
     * delivery note's packer and packed time.
     */
    private function people(Warehouse $warehouse, ?string $channel, Carbon $todayStart, Carbon $now): array
    {
        $utcNow   = $now->copy()->utc();
        $idleFrom = $utcNow->copy()->subMinutes(self::THRESHOLDS['idle_minutes']);
        $hourAgo  = $utcNow->copy()->subHour();

        $pickings = DB::table('pickings')
            ->join('delivery_notes', 'delivery_notes.id', 'pickings.delivery_note_id')
            ->where('pickings.organisation_id', $warehouse->organisation_id)
            ->where('delivery_notes.warehouse_id', $warehouse->id)
            ->when($channel, fn (Builder $query) => $query->where('delivery_notes.shop_type', $channel))
            ->where('pickings.created_at', '>=', $todayStart->copy()->utc());

        $pickers = (clone $pickings)
            ->groupBy('pickings.picker_user_id')
            ->selectRaw("pickings.picker_user_id, count(*) filter (where pickings.type = 'pick') as lines, count(*) filter (where pickings.type = 'not-pick') as short, min(pickings.created_at) as first_at, max(pickings.created_at) as last_at")
            ->selectRaw("count(*) filter (where pickings.type = 'pick' and pickings.created_at >= ?) as lines_last_hour", [$hourAgo])
            ->get();

        $packers = $this->deliveryNotes($warehouse, $channel)
            ->where('delivery_notes.packed_at', '>=', $todayStart->copy()->utc())
            ->whereNotNull('delivery_notes.packer_user_id')
            ->groupBy('delivery_notes.packer_user_id')
            ->selectRaw('delivery_notes.packer_user_id, count(*) as packed, max(delivery_notes.packed_at) as last_at, min(delivery_notes.packed_at) as first_at')
            ->selectRaw('count(*) filter (where delivery_notes.packed_at >= ?) as packed_last_hour', [$hourAgo])
            ->get();

        return [
            'pickers' => $this->team($pickers, 'lines', 'lines_last_hour', $idleFrom, $hourAgo, (int) $pickers->sum('short')),
            'packers' => $this->team($packers, 'packed', 'packed_last_hour', $idleFrom, $hourAgo),
        ];
    }

    private function team(Collection $people, string $doneColumn, string $lastHourColumn, Carbon $idleFrom, Carbon $hourAgo, ?int $short = null): array
    {
        $activeHours = $people->sum(fn ($person) => max(0.25, Carbon::parse($person->first_at)->diffInMinutes(Carbon::parse($person->last_at)) / 60));
        $lastHour    = $people->filter(fn ($person) => Carbon::parse($person->last_at)->gte($hourAgo));
        $done        = (int) $people->sum($doneColumn);

        return [
            'today'            => $done,
            'people_today'     => $people->count(),
            'active'           => $people->filter(fn ($person) => Carbon::parse($person->last_at)->gte($idleFrom))->count(),
            'idle'             => $lastHour->filter(fn ($person) => Carbon::parse($person->last_at)->lt($idleFrom))->count(),
            'per_person_hour'  => $activeHours > 0 ? round($done / $activeHours, 1) : null,
            'last_hour'        => (int) $lastHour->sum($lastHourColumn),
            'last_hour_people' => $lastHour->count(),
            'short'            => $short,
        ];
    }

    private function etaExpression(): string
    {
        return "coalesce(nullif(stock_deliveries.data->>'estimated_receiving_date', '')::date, (select min(purchase_orders.estimated_received_at)::date from purchase_order_stock_delivery join purchase_orders on purchase_orders.id = purchase_order_stock_delivery.purchase_order_id where purchase_order_stock_delivery.stock_delivery_id = stock_deliveries.id))";
    }

    private function openStockDeliveries(Warehouse $warehouse): Builder
    {
        return DB::table('stock_deliveries')
            ->where('stock_deliveries.organisation_id', $warehouse->organisation_id)
            ->whereNull('stock_deliveries.deleted_at')
            ->whereIn('stock_deliveries.state', $this->values(self::OPEN_STOCK_DELIVERY_STATES));
    }

    /**
     * Inbound deliveries, overdue first then by ETA, each with the open delivery notes waiting for
     * stock it carries: put away first what releases the most orders.
     */
    private function goodsIn(Warehouse $warehouse, string $timezone, Carbon $now): array
    {
        $counts = $this->openStockDeliveries($warehouse)
            ->groupBy('stock_deliveries.state')
            ->selectRaw('stock_deliveries.state, count(*) as total')
            ->pluck('total', 'state');

        $dockToStock = DB::table('stock_deliveries')
            ->where('organisation_id', $warehouse->organisation_id)
            ->whereNull('deleted_at')
            ->whereNotNull('received_at')
            ->whereRaw('coalesce(booked_in_at, placed_at) >= ?', [$now->copy()->subDays(90)->utc()])
            ->whereRaw('coalesce(booked_in_at, placed_at) >= received_at')
            ->selectRaw('count(*) as total')
            ->selectRaw('percentile_cont(0.5) within group (order by extract(epoch from coalesce(booked_in_at, placed_at) - received_at)) as median')
            ->selectRaw('percentile_cont(0.9) within group (order by extract(epoch from coalesce(booked_in_at, placed_at) - received_at)) as p90')
            ->first();

        $waitingForStock = DB::table('delivery_note_items')
            ->join('delivery_notes', 'delivery_notes.id', 'delivery_note_items.delivery_note_id')
            ->where('delivery_notes.warehouse_id', $warehouse->id)
            ->whereNull('delivery_notes.deleted_at')
            ->whereIn('delivery_notes.state', $this->values([DeliveryNoteStateEnum::UNASSIGNED, DeliveryNoteStateEnum::QUEUED, DeliveryNoteStateEnum::HANDLING, DeliveryNoteStateEnum::HANDLING_BLOCKED]))
            ->whereRaw('coalesce(delivery_note_items.quantity_picked, 0) < delivery_note_items.quantity_required')
            ->whereExists(fn (Builder $query) => $query->selectRaw('1')->from('org_stocks')
                ->whereColumn('org_stocks.id', 'delivery_note_items.org_stock_id')
                ->whereRaw('org_stocks.quantity_in_locations < delivery_note_items.quantity_required'))
            ->select('delivery_note_items.org_stock_id', 'delivery_note_items.delivery_note_id');

        $waiting      = $waitingForStock->get();
        $openIds      = $this->openStockDeliveries($warehouse)->pluck('stock_deliveries.id');
        $orderValues  = DB::table('delivery_note_order')
            ->join('orders', 'orders.id', 'delivery_note_order.order_id')
            ->whereIn('delivery_note_order.delivery_note_id', $waiting->pluck('delivery_note_id')->unique()->values())
            ->groupBy('delivery_note_order.delivery_note_id')
            ->selectRaw('delivery_note_order.delivery_note_id, sum(orders.org_net_amount) as org_amount')
            ->pluck('org_amount', 'delivery_note_id');
        $carried      = DB::table('stock_delivery_items')
            ->whereIn('stock_delivery_id', $openIds)
            ->whereIn('org_stock_id', $waiting->pluck('org_stock_id')->unique()->values())
            ->whereNull('deleted_at')
            ->distinct()
            ->get(['stock_delivery_id', 'org_stock_id']);
        $waitingByOrgStock = $waiting->groupBy('org_stock_id');
        $releases     = $carried->groupBy('stock_delivery_id')->map(function ($items) use ($waitingByOrgStock, $orderValues) {
            $deliveryNoteIds = $items->flatMap(fn ($item) => $waitingByOrgStock->get($item->org_stock_id, collect())->pluck('delivery_note_id'))->unique();

            return [
                'delivery_notes' => $deliveryNoteIds->count(),
                'org_amount'     => (float) $deliveryNoteIds->sum(fn ($id) => $orderValues->get($id, 0)),
            ];
        });

        $deliveries = $this->openStockDeliveries($warehouse)
            ->selectRaw('stock_deliveries.id, stock_deliveries.slug, stock_deliveries.reference, stock_deliveries.state, stock_deliveries.parent_name, stock_deliveries.cbm, stock_deliveries.received_at, stock_deliveries.number_stock_delivery_items, stock_deliveries.number_purchase_orders')
            ->selectRaw($this->etaExpression().' as eta')
            ->get()
            ->map(fn ($delivery) => [
                'id'             => $delivery->id,
                'reference'      => $delivery->reference,
                'supplier'       => $delivery->parent_name,
                'state'          => $delivery->state,
                'eta'            => $delivery->eta,
                'is_overdue'     => $delivery->eta && $delivery->eta < $now->toDateString() && in_array($delivery->state, $this->values([StockDeliveryStateEnum::CONFIRMED, StockDeliveryStateEnum::READY_TO_SHIP, StockDeliveryStateEnum::DISPATCHED])),
                'received_at'    => $this->iso($delivery->received_at),
                'cbm'            => $delivery->cbm === null ? null : (float) $delivery->cbm,
                'lines'          => (int) $delivery->number_stock_delivery_items,
                'has_po'         => $delivery->number_purchase_orders > 0,
                'releases'       => $releases->get($delivery->id)['delivery_notes'] ?? 0,
                'releases_value' => round($releases->get($delivery->id)['org_amount'] ?? 0, 2),
                'route'          => ['name' => 'grp.org.procurement.stock_deliveries.show', 'parameters' => ['organisation' => $warehouse->organisation->slug, 'stockDelivery' => $delivery->slug]],
            ])
            ->sortBy(fn ($delivery) => [$delivery['is_overdue'] ? 0 : 1, $delivery['eta'] ?? '9999-12-31'])
            ->values();

        return [
            'counts'        => [
                'on_the_way' => (int) ($counts[StockDeliveryStateEnum::CONFIRMED->value] ?? 0) + (int) ($counts[StockDeliveryStateEnum::READY_TO_SHIP->value] ?? 0) + (int) ($counts[StockDeliveryStateEnum::DISPATCHED->value] ?? 0),
                'to_book_in' => (int) ($counts[StockDeliveryStateEnum::RECEIVED->value] ?? 0) + (int) ($counts[StockDeliveryStateEnum::CHECKED->value] ?? 0),
                'booking_in' => (int) ($counts[StockDeliveryStateEnum::BOOKING_IN->value] ?? 0),
                'without_eta' => $deliveries->whereNull('eta')->count(),
                'without_po' => $deliveries->where('has_po', false)->count(),
            ],
            'dock_to_stock' => [
                'deliveries'     => (int) $dockToStock->total,
                'median_seconds' => $dockToStock->median === null ? null : (int) $dockToStock->median,
                'p90_seconds'    => $dockToStock->p90 === null ? null : (int) $dockToStock->p90,
            ],
            'deliveries'    => $deliveries->take(15)->all(),
        ];
    }

    private function stock(Warehouse $warehouse, ?string $channel): array
    {
        $stats = DB::table('warehouse_stats')->where('warehouse_id', $warehouse->id)->first();

        $notAudited = DB::table('locations')
            ->where('warehouse_id', $warehouse->id)
            ->whereNull('deleted_at')
            ->where('is_empty', false)
            ->where(fn (Builder $query) => $query->whereNull('audited_at')->orWhere('audited_at', '<', now()->subDays(90)))
            ->count();

        $outOfStock = DB::table('delivery_note_items')
            ->join('delivery_notes', 'delivery_notes.id', 'delivery_note_items.delivery_note_id')
            ->join('org_stocks', 'org_stocks.id', 'delivery_note_items.org_stock_id')
            ->leftJoin('transactions', 'transactions.id', 'delivery_note_items.transaction_id')
            ->where('delivery_notes.warehouse_id', $warehouse->id)
            ->whereNull('delivery_notes.deleted_at')
            ->when($channel, fn (Builder $query) => $query->where('delivery_notes.shop_type', $channel))
            ->whereIn('delivery_notes.state', $this->values([DeliveryNoteStateEnum::UNASSIGNED, DeliveryNoteStateEnum::QUEUED, DeliveryNoteStateEnum::HANDLING, DeliveryNoteStateEnum::HANDLING_BLOCKED]))
            ->whereRaw('coalesce(delivery_note_items.quantity_picked, 0) < delivery_note_items.quantity_required')
            ->where('org_stocks.quantity_in_locations', '<=', 0)
            ->groupBy('org_stocks.id', 'org_stocks.code', 'org_stocks.slug', 'org_stocks.name')
            ->selectRaw('org_stocks.id, org_stocks.code, org_stocks.slug, org_stocks.name, count(distinct delivery_notes.id) as delivery_notes, coalesce(sum(transactions.org_net_amount), 0) as org_amount')
            ->orderByDesc('delivery_notes')
            ->limit(10)
            ->get();

        $inbound = DB::table('stock_delivery_items')
            ->joinSub($this->openStockDeliveries($warehouse)->select('stock_deliveries.id', 'stock_deliveries.state')->selectRaw($this->etaExpression().' as eta'), 'inbound', 'inbound.id', 'stock_delivery_items.stock_delivery_id')
            ->whereIn('stock_delivery_items.org_stock_id', $outOfStock->pluck('id'))
            ->whereNull('stock_delivery_items.deleted_at')
            ->groupBy('stock_delivery_items.org_stock_id')
            ->selectRaw('stock_delivery_items.org_stock_id, min(inbound.eta) as eta, bool_or(inbound.state in (?, ?, ?)) as arrived', $this->values([StockDeliveryStateEnum::RECEIVED, StockDeliveryStateEnum::CHECKED, StockDeliveryStateEnum::BOOKING_IN]))
            ->get()
            ->keyBy('org_stock_id');

        return [
            'locations'        => (int) ($stats->number_locations ?? 0),
            'empty_locations'  => (int) ($stats->number_empty_locations ?? 0),
            'not_audited_90d'  => $notAudited,
            'out_of_stock'     => $outOfStock->map(fn ($orgStock) => [
                'code'           => $orgStock->code,
                'name'           => $orgStock->name,
                'delivery_notes' => (int) $orgStock->delivery_notes,
                'org_amount'     => round((float) $orgStock->org_amount, 2),
                'inbound_eta'    => $inbound->get($orgStock->id)?->eta,
                'inbound'        => $inbound->has($orgStock->id),
                'arrived'        => (bool) ($inbound->get($orgStock->id)?->arrived ?? false),
                'route'          => ['name' => 'grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.show', 'parameters' => ['organisation' => $warehouse->organisation->slug, 'warehouse' => $warehouse->slug, 'orgStock' => $orgStock->slug]],
            ])->all(),
        ];
    }

    private function returns(Warehouse $warehouse, Carbon $now): array
    {
        $returnDeliveryNotes = DB::table('return_delivery_notes')
            ->where('warehouse_id', $warehouse->id)
            ->whereNull('deleted_at')
            ->groupBy('state')
            ->selectRaw('state, count(*) as total, min(created_at) as oldest')
            ->get()
            ->keyBy('state');

        $monthStart = $now->copy()->startOfMonth()->utc();

        $outcomes = DB::table('return_delivery_note_items')
            ->join('return_delivery_notes', 'return_delivery_notes.id', 'return_delivery_note_items.return_delivery_note_id')
            ->where('return_delivery_notes.warehouse_id', $warehouse->id)
            ->whereNull('return_delivery_note_items.deleted_at')
            ->where('return_delivery_note_items.processed_at', '>=', $monthStart)
            ->selectRaw('coalesce(sum(total_item_returned), 0) as restocked, coalesce(sum(total_item_damaged), 0) as damaged, coalesce(sum(total_item_not_returned), 0) as not_returned')
            ->first();

        $customerReturns = DB::table('returns')
            ->where('warehouse_id', $warehouse->id)
            ->whereNull('deleted_at')
            ->groupBy('state')
            ->selectRaw('state, count(*) as total, min(coalesce(received_at, waiting_to_receive_at, created_at)) as oldest')
            ->get()
            ->keyBy('state');

        $reasons = DB::table('returns')
            ->where('warehouse_id', $warehouse->id)
            ->whereNull('deleted_at')
            ->where('created_at', '>=', $monthStart)
            ->whereRaw("coalesce(trim(return_reason), '') <> ''")
            ->groupByRaw('lower(trim(return_reason))')
            ->selectRaw('min(trim(return_reason)) as reason, count(*) as total')
            ->orderByDesc('total')
            ->get();

        $toProcess = collect(['received', 'returning'])->map(fn ($state) => $returnDeliveryNotes->get($state));

        return [
            'to_process'       => (int) $toProcess->sum(fn ($row) => $row->total ?? 0),
            'to_process_oldest' => $this->iso($toProcess->pluck('oldest')->filter()->min()),
            'expected'         => (int) ($customerReturns->get('waiting_to_receive')->total ?? 0),
            'received'         => (int) ($customerReturns->get('received')->total ?? 0),
            'received_oldest'  => $this->iso($customerReturns->get('received')->oldest ?? null),
            'outcomes'         => ['restocked' => (int) $outcomes->restocked, 'damaged' => (int) $outcomes->damaged, 'not_returned' => (int) $outcomes->not_returned],
            'reasons'          => $reasons->map(fn ($row) => ['reason' => $row->reason, 'count' => (int) $row->total])->all(),
        ];
    }

    /**
     * @param  Collection<int, Warehouse>  $warehouses
     * @param  Collection<int, array>  $perWarehouse
     */
    private function combine(Collection $warehouses, Collection $perWarehouse, ?string $channel): array
    {
        $single   = $warehouses->count() === 1;
        $currency = $single ? $warehouses->first()->organisation->currency->code : group()->currency->code;
        $amount   = $single ? 'org_amount' : 'grp_amount';

        $breakdown = fn (callable $value, ?callable $route = null) => $warehouses->map(fn (Warehouse $warehouse) => [
            'warehouse' => $warehouse->code ?: $warehouse->slug,
            'value'     => $value($perWarehouse[$warehouse->id], $warehouse),
            'route'     => $route ? $route($warehouse) : null,
        ])->values()->all();

        $sum    = fn (string $path) => $perWarehouse->sum(fn ($figures) => (float) data_get($figures, $path, 0));
        $oldest = fn (string $path) => $perWarehouse->map(fn ($figures) => data_get($figures, $path))->filter()->min();
        $tile   = fn (string $count, ?string $since, ?callable $route, array $extra = []) => [
            'count'     => (int) $sum($count),
            'oldest'    => $since ? $oldest($since) : null,
            'breakdown' => $breakdown(fn ($figures) => (int) data_get($figures, $count, 0), $route),
            ...$extra,
        ];

        $dispatching     = fn (string $routeKey) => fn (Warehouse $warehouse) => $this->dispatchingRoute($warehouse, $routeKey, $channel);
        $warehouseRoute  = fn (string $routeName, array $parameters = []) => fn (Warehouse $warehouse) => ['name' => $routeName, 'parameters' => [...$this->warehouseParameters($warehouse), ...$parameters]];
        $stockDeliveries = $warehouseRoute('grp.org.warehouses.show.incoming.stock_deliveries.index');
        $records         = fn (string $list, array $query = []) => fn (Warehouse $warehouse) => [
            'name'       => 'grp.org.warehouses.show.operations.records',
            'parameters' => [...$this->warehouseParameters($warehouse), 'list' => $list, ...array_filter(['channel' => $channel, ...$query])],
        ];
        $period = $perWarehouse->first()['time_to_dispatch']['period'] ?? 30;

        $pipeline = [];
        foreach ([...array_keys(self::STAGES), 'dispatched'] as $stage) {
            $pipeline[$stage] = [
                ...$tile("pipeline.$stage.count", $stage === 'dispatched' ? null : "pipeline.$stage.oldest", $dispatching(self::STAGE_ROUTES[$stage])),
                'replacements' => (int) $sum("pipeline.$stage.replacements"),
                'premium'      => (int) $sum("pipeline.$stage.premium"),
                'amount'       => round($sum("pipeline.$stage.$amount"), 2),
            ];
        }
        foreach (['lines', 'parcels'] as $measure) {
            $pipeline['dispatched'][$measure] = (int) $sum("pipeline.dispatched.$measure");
        }
        foreach (['yesterday', 'same_day_last_week'] as $comparison) {
            $pipeline['dispatched'][$comparison] = [
                'count'   => (int) $sum("pipeline.dispatched.$comparison.count"),
                'lines'   => (int) $sum("pipeline.dispatched.$comparison.lines"),
                'parcels' => (int) $sum("pipeline.dispatched.$comparison.parcels"),
                'amount'  => round($sum("pipeline.dispatched.$comparison.$amount"), 2),
            ];
        }

        $timeToDispatch = $perWarehouse->pluck('time_to_dispatch');
        $dispatched     = $timeToDispatch->sum('dispatched');

        return [
            'currency_code'    => $currency,
            'attention'        => [
                'urgent'           => $tile('attention.urgent.count', 'attention.urgent.oldest', $dispatching('unassigned.delivery-notes')),
                'at_risk'          => ['count' => null],
                'blocked'          => $tile('attention.blocked.count', 'attention.blocked.oldest', $dispatching('handling-blocked.delivery-notes'), [
                    'reasons' => [
                        'stock'            => (int) $sum('attention.blocked.reasons.stock'),
                        'customer_service' => (int) $sum('attention.blocked.reasons.customer_service'),
                        'other'            => (int) $sum('attention.blocked.reasons.other'),
                    ],
                ]),
                'customer_service' => $tile('attention.customer_service.count', 'attention.customer_service.oldest', $dispatching('waiting_crm_items')),
                'out_of_stock'     => $tile('attention.out_of_stock.count', null, $dispatching('waiting_items'), ['delivery_notes' => (int) $sum('attention.out_of_stock.delivery_notes')]),
                'replenishment'    => $tile('attention.replenishment.count', null, $warehouseRoute('grp.org.warehouses.show.inventory.org_stocks.replenishments.index')),
                'overdue'          => $tile('attention.overdue.count', 'attention.overdue.oldest_eta', $stockDeliveries),
                'stock_errors'     => $tile('attention.stock_errors.count', null, $warehouseRoute('grp.org.warehouses.show.inventory.org_stocks.negative_stocks.index')),
            ],
            'pipeline'         => $pipeline,
            'waiting_split'    => collect(['pickable', 'partly', 'no_stock', 'premium', 'normal'])->mapWithKeys(fn ($key) => [$key => (int) $sum("waiting_split.$key")])->all(),
            'age_buckets'      => collect(['under_4h' => 'age_under_4h', 'h4_24' => 'age_4_24h', 'd1_2' => 'age_1_2d', 'over_2d' => 'age_over_2d'])->map(fn ($list, $key) => $tile("age_buckets.$key", null, $records($list)))->all(),
            'time_to_dispatch' => [
                'period'           => $timeToDispatch->first()['period'] ?? 30,
                'dispatched'       => (int) $dispatched,
                'median_seconds'   => $this->weightedAverage($timeToDispatch, 'median_seconds', 'dispatched'),
                'p90_seconds'      => $timeToDispatch->max('p90_seconds'),
                'same_day_percent' => $dispatched ? round($timeToDispatch->sum(fn ($row) => ($row['same_day_percent'] ?? 0) * $row['dispatched']) / $dispatched, 1) : null,
                'is_combined'      => !$single,
                'by_warehouse'     => $breakdown(fn ($figures) => $figures['time_to_dispatch']),
                'records'          => $tile('time_to_dispatch.dispatched', null, $records('dispatched', ['period' => $period])),
            ],
            'people'           => collect(['pickers', 'packers'])->mapWithKeys(fn ($team) => [$team => [
                'today'            => (int) $sum("people.$team.today"),
                'people_today'     => (int) $sum("people.$team.people_today"),
                'active'           => (int) $sum("people.$team.active"),
                'idle'             => (int) $sum("people.$team.idle"),
                'last_hour'        => (int) $sum("people.$team.last_hour"),
                'last_hour_people' => (int) $sum("people.$team.last_hour_people"),
                'per_person_hour'  => $this->weightedAverage($perWarehouse->pluck("people.$team"), 'per_person_hour', 'today'),
                'short'            => $team === 'pickers' ? (int) $sum('people.pickers.short') : null,
                'records'          => $tile("people.$team.today", null, $records($team === 'pickers' ? 'picked_today' : 'packed_today')),
                'short_records'    => $team === 'pickers' ? $tile('people.pickers.short', null, $records('short_today')) : null,
            ]])->all(),
            'goods_in'         => [
                'counts'        => collect(['on_the_way', 'to_book_in', 'booking_in', 'without_eta', 'without_po'])->mapWithKeys(fn ($key) => [$key => $tile("goods_in.counts.$key", null, $stockDeliveries)])->all(),
                'overdue'       => $tile('attention.overdue.count', null, $stockDeliveries),
                'dock_to_stock' => [
                    'deliveries'     => (int) $sum('goods_in.dock_to_stock.deliveries'),
                    'median_seconds' => $this->weightedAverage($perWarehouse->pluck('goods_in.dock_to_stock'), 'median_seconds', 'deliveries'),
                    'p90_seconds'    => $perWarehouse->pluck('goods_in.dock_to_stock')->max('p90_seconds'),
                    'is_combined'    => !$single,
                ],
                'deliveries'    => $warehouses->flatMap(fn (Warehouse $warehouse) => array_map(fn ($delivery) => [
                    ...$delivery,
                    'warehouse'     => $warehouse->code ?: $warehouse->slug,
                    'currency_code' => $warehouse->organisation->currency->code,
                ], $perWarehouse[$warehouse->id]['goods_in']['deliveries']))
                    ->sortBy(fn ($delivery) => [$delivery['is_overdue'] ? 0 : 1, -$delivery['releases'], $delivery['eta'] ?? '9999-12-31'])
                    ->values()->take(15)->all(),
            ],
            'stock'            => [
                'locations'       => (int) $sum('stock.locations'),
                'empty_locations' => $tile('stock.empty_locations', null, $warehouseRoute('grp.org.warehouses.show.infrastructure.locations.index')),
                'not_audited_90d' => $tile('stock.not_audited_90d', null, $warehouseRoute('grp.org.warehouses.show.operations.records', ['list' => 'not_counted'])),
                'negative'        => $tile('attention.stock_errors.negative', null, $warehouseRoute('grp.org.warehouses.show.inventory.org_stocks.negative_stocks.index')),
                'replenishment'   => $tile('attention.replenishment.count', null, $warehouseRoute('grp.org.warehouses.show.inventory.org_stocks.replenishments.index')),
                'out_of_stock'    => $warehouses->flatMap(fn (Warehouse $warehouse) => array_map(fn ($orgStock) => [
                    ...$orgStock,
                    'warehouse'     => $warehouse->code ?: $warehouse->slug,
                    'currency_code' => $warehouse->organisation->currency->code,
                ], $perWarehouse[$warehouse->id]['stock']['out_of_stock']))
                    ->sortByDesc('delivery_notes')->values()->take(10)->all(),
            ],
            'returns'          => [
                'to_process'        => $tile('returns.to_process', 'returns.to_process_oldest', $warehouseRoute('grp.org.warehouses.show.incoming.return_delivery_notes.state.received')),
                'expected'          => (int) $sum('returns.expected'),
                'received'          => $tile('returns.received', 'returns.received_oldest', $warehouseRoute('grp.org.warehouses.show.incoming.returns.index')),
                'outcomes'          => collect(['restocked', 'damaged', 'not_returned'])->mapWithKeys(fn ($key) => [$key => (int) $sum("returns.outcomes.$key")])->all(),
                'processed'         => [
                    'breakdown' => $breakdown(fn ($figures) => (int) array_sum($figures['returns']['outcomes']), $records('returns_processed')),
                ],
                'reasons'           => $perWarehouse->flatMap(fn ($figures) => $figures['returns']['reasons'])
                    ->groupBy(fn ($row) => mb_strtolower($row['reason']))
                    ->map(fn ($rows) => [
                        'reason'    => $rows->first()['reason'],
                        'count'     => $rows->sum('count'),
                        'breakdown' => $breakdown(
                            fn ($figures) => (int) (collect($figures['returns']['reasons'])->first(fn ($row) => mb_strtolower($row['reason']) === mb_strtolower($rows->first()['reason']))['count'] ?? 0),
                            $records('return_reasons', ['reason' => $rows->first()['reason']])
                        ),
                    ])
                    ->sortByDesc('count')->values()->take(5)->all(),
            ],
        ];
    }

    private function weightedAverage(Collection $rows, string $value, string $weight): ?int
    {
        $rows  = $rows->filter(fn ($row) => ($row[$value] ?? null) !== null && ($row[$weight] ?? 0) > 0);
        $total = $rows->sum($weight);

        return $total ? (int) round($rows->sum(fn ($row) => $row[$value] * $row[$weight]) / $total) : null;
    }

    private function warehouseParameters(Warehouse $warehouse): array
    {
        return ['organisation' => $warehouse->organisation->slug, 'warehouse' => $warehouse->slug];
    }

    private function dispatchingRoute(Warehouse $warehouse, string $routeKey, ?string $channel): array
    {
        $routeName = 'grp.org.warehouses.show.dispatching.'.$routeKey;

        return $channel
            ? ['name' => "$routeName.shop", 'parameters' => [...$this->warehouseParameters($warehouse), 'shopType' => $channel]]
            : ['name' => $routeName, 'parameters' => $this->warehouseParameters($warehouse)];
    }

    private function withoutValues(array $figures): array
    {
        foreach (array_keys($figures['pipeline']) as $stage) {
            $figures['pipeline'][$stage]['amount'] = null;
        }
        foreach (['yesterday', 'same_day_last_week'] as $comparison) {
            $figures['pipeline']['dispatched'][$comparison]['amount'] = null;
        }
        $figures['goods_in']['deliveries'] = array_map(fn ($delivery) => [...$delivery, 'releases_value' => null], $figures['goods_in']['deliveries']);
        $figures['stock']['out_of_stock']  = array_map(fn ($orgStock) => [...$orgStock, 'org_amount' => null], $figures['stock']['out_of_stock']);

        return $figures;
    }

    private function iso(?string $date): ?string
    {
        return $date ? Carbon::parse($date)->toIso8601String() : null;
    }

    /**
     * One row per organisation: orders in today on its own calendar against the same weekday last
     * year, and a staffing warning when today runs over 25% above the last four same weekdays. Values,
     * pipeline and month target only for those who see sales; group row in the group currency.
     *
     * @param  Collection<int, Warehouse>  $warehouses
     */
    private function salesCard(Group $group, Collection $warehouses, bool $withValue): array
    {
        $organisations = $warehouses->pluck('organisation')->unique('id')->values();

        $rows = $organisations->map(function (Organisation $organisation) use ($withValue) {
            $figures = Cache::remember("operations-dashboard-sales:$organisation->id", now()->addMinutes(5), fn () => $this->organisationOrdersIn($organisation));
            $month   = $withValue ? Cache::remember("operations-dashboard-month:$organisation->id", now()->addMinutes(10), fn () => GetShopMonthSalesTarget::run($organisation)) : null;

            return [
                'name'          => $organisation->name,
                'currency_code' => $organisation->currency->code,
                ...$figures,
                ...$this->monthColumns($month, $withValue),
                'route'         => ['name' => 'grp.org.dashboard.show', 'parameters' => ['organisation' => $organisation->slug]],
            ];
        });

        $groupMonth = $withValue && $organisations->count() > 1 ? Cache::remember("operations-dashboard-month-group:$group->id", now()->addMinutes(10), fn () => GetShopMonthSalesTarget::run($group)) : null;

        $total = $organisations->count() > 1 ? [
            'name'               => __('Group'),
            'currency_code'      => $group->currency->code,
            'orders_today'       => $rows->sum('orders_today'),
            'orders_last_year'   => $rows->sum('orders_last_year'),
            'change_percent'     => $this->changePercent($rows->sum('orders_today'), $rows->sum('orders_last_year')),
            'weekday_average'    => round($rows->sum('weekday_average'), 1),
            'is_busy'            => $rows->contains('is_busy', true),
            'sparkline'          => collect(range(0, 6))->map(fn ($day) => $rows->sum(fn ($row) => $row['sparkline'][$day] ?? 0))->all(),
            'value_today'        => $withValue ? round($rows->sum('grp_value_today'), 2) : null,
            ...$this->monthColumns($groupMonth, $withValue),
        ] : null;

        return [
            'rows'  => $rows->map(fn ($row) => [...Arr::except($row, ['grp_value_today']), 'value_today' => $withValue ? $row['value_today'] : null])->all(),
            'total' => $total,
        ];
    }

    private function monthColumns(?array $month, bool $withValue): array
    {
        if (!$withValue || !$month) {
            return ['pipeline' => null, 'month_to_date' => null, 'target_percent' => null];
        }

        $target = $month['target']['amount'] ?? null;

        return [
            'pipeline'       => round((float) ($month['pipeline']['in_warehouse_amount'] ?? 0), 2),
            'month_to_date'  => round((float) $month['sales_so_far'], 2),
            'target_percent' => $target ? round(100 * $month['sales_so_far'] / $target, 1) : null,
        ];
    }

    private function changePercent(float $now, float $before): ?float
    {
        return $before > 0 ? round(100 * ($now - $before) / $before, 1) : null;
    }

    private function organisationOrdersIn(Organisation $organisation): array
    {
        $timezone   = $organisation->timezone?->name ?? 'UTC';
        $now        = now($timezone);
        $todayStart = $now->copy()->startOfDay();
        $lastYear   = $todayStart->copy()->subWeeks(52);
        $firstDay   = $todayStart->copy()->subWeeks(4);

        $days = DB::table('orders')
            ->where('organisation_id', $organisation->id)
            ->whereNull('deleted_at')
            ->whereNotNull('submitted_at')
            ->where('state', '!=', OrderStateEnum::CANCELLED->value)
            ->where(fn (Builder $query) => $query
                ->where('submitted_at', '>=', $firstDay->copy()->utc())
                ->orWhereBetween('submitted_at', [$lastYear->copy()->utc(), $lastYear->copy()->setTimeFrom($now)->utc()]))
            ->where('submitted_at', '<=', $now->copy()->utc())
            ->groupByRaw('1')
            ->selectRaw('(submitted_at at time zone ?)::date as day, count(*) as total, sum(org_net_amount) as org_amount, sum(grp_net_amount) as grp_amount', [$timezone])
            ->get()
            ->keyBy('day');

        $count     = fn (Carbon $day) => (int) ($days->get($day->toDateString())->total ?? 0);
        $today     = $count($todayStart);
        $sameDays  = collect(range(1, 4))->map(fn ($weeks) => $count($todayStart->copy()->subWeeks($weeks)));
        $average   = $sameDays->avg();
        $lastYearN = $count($lastYear);

        return [
            'orders_today'     => $today,
            'orders_last_year' => $lastYearN,
            'change_percent'   => $this->changePercent($today, $lastYearN),
            'weekday_average'  => round($average, 1),
            'is_busy'          => $average > 0 && $today > $average * self::THRESHOLDS['orders_in_staffing_rate'],
            'sparkline'        => collect(range(6, 0))->map(fn ($back) => $count($todayStart->copy()->subDays($back)))->all(),
            'value_today'      => round((float) ($days->get($todayStart->toDateString())->org_amount ?? 0), 2),
            'grp_value_today'  => round((float) ($days->get($todayStart->toDateString())->grp_amount ?? 0), 2),
        ];
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        $user    = $request->user();
        $filters = $request->only(['warehouse', 'channel', 'period']);

        abort_if(self::warehousesFor($user)->isEmpty(), 403);

        if ($request->boolean('remember')) {
            $user->update(['settings' => [...($user->settings ?? []), self::SETTINGS_KEY => $filters]]);
        } elseif (!$request->hasAny(['warehouse', 'channel', 'period'])) {
            $filters = Arr::get($user->settings ?? [], self::SETTINGS_KEY, []);
        }

        return response()->json($this->handle($user, $filters));
    }
}
