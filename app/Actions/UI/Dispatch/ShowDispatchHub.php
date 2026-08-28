<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Mar 2023 18:44:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Dispatch;

use App\Actions\Helpers\Dashboard\DashboardIntervalFilters;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithDispatchingAuthorisation;
use App\Actions\Traits\Dashboards\Settings\WithDashboardCurrencyTypeSettings;
use App\Actions\Traits\Dashboards\WithDashboardIntervalOption;
use App\Actions\Traits\Dashboards\WithDashboardSettings;
use App\Actions\Traits\WithDashboard;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Enums\DateIntervals\DateIntervalEnum;
use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\Enums\UI\Dispatch\DispatchHubTabsEnum;
use App\Http\Resources\Dispatching\DashboardDispatchHubDashboardResource;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowDispatchHub extends OrgAction
{
    use WithDashboard;
    use WithDashboardSettings;
    use WithDashboardIntervalOption;
    use WithDispatchingAuthorisation;
    use WithDashboardCurrencyTypeSettings;

    public function handle(Warehouse $warehouse): Warehouse
    {
        return $warehouse;
    }

    public function asController(Organisation $organisation, Warehouse $warehouse): Warehouse
    {
        $this->initialisationFromWarehouse($warehouse, [])->withTab(DispatchHubTabsEnum::values());

        return $this->handle($warehouse);
    }

    public function htmlResponse(Warehouse $warehouse, ActionRequest $request): Response
    {
        $userSettings = $request->user()->settings;

        return Inertia::render(
            'Org/Dispatching/DispatchHub',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => 'dispatch',
                'pageHead'    => [
                    'title' => __('Dispatching Backlog'),
                    'icon'  => [
                        'icon' => ['fal', 'fa-conveyor-belt-alt'],
                    ],
                ],
                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => DispatchHubTabsEnum::navigation()
                ],
                'intervals'   => [
                    'options'        => $this->dashboardIntervalOption(),
                    'value'          => DateIntervalEnum::ALL,
                    'range_interval' => DashboardIntervalFilters::run(DateIntervalEnum::ALL, $userSettings),
                ],
                'settings'    => [
                    'model_state_type'  => $this->dashboardModelStateTypeSettings($userSettings, 'left'),
                    'data_display_type' => $this->dashboardDataDisplayTypeSettings($userSettings),
                    'currency_type'     => $this->dashboardCurrencyTypeSettings($this->organisation, $userSettings),
                ],
                'delivery_note'   => DashboardDispatchHubDashboardResource::make(GetDispatchHubShowcase::make()->handle($warehouse)),
                'picking_session' => $this->getPickingSessionStats($warehouse),
                'pickers'         => $this->getPickersStats($warehouse),
                'packers'         => $this->getPackersStats($warehouse),
            ]
        );
    }

    private function getPickingSessionStats(Warehouse $warehouse): array
    {
        $stats = $warehouse->stats;
        $routeParams = [
            'organisation' => $this->organisation->slug,
            'warehouse'    => $warehouse->slug,
        ];

        $stateConfig = [
            PickingSessionStateEnum::IN_PROCESS->value       => ['route' => 'grp.org.warehouses.show.dispatching.picking_sessions.in_process', 'icon' => ['fal', 'fa-chair']],
            PickingSessionStateEnum::HANDLING->value         => ['route' => 'grp.org.warehouses.show.dispatching.picking_sessions.picking', 'icon' => ['fal', 'fa-hand-paper']],
            PickingSessionStateEnum::HANDLING_BLOCKED->value => ['route' => 'grp.org.warehouses.show.dispatching.picking_sessions.waiting', 'icon' => ['fal', 'fa-hand-paper']],
            PickingSessionStateEnum::PICKING_FINISHED->value => ['route' => 'grp.org.warehouses.show.dispatching.picking_sessions.picked', 'icon' => ['fal', 'fa-box-check']],
            PickingSessionStateEnum::PACKING_FINISHED->value => ['route' => 'grp.org.warehouses.show.dispatching.picking_sessions.packed', 'icon' => ['fal', 'fa-box-check']],
        ];

        $metrics    = [];
        $dataGlobal = [];
        $totals     = [];

        foreach (PickingSessionStateEnum::cases() as $case) {
            $config    = $stateConfig[$case->value];
            $statField = 'number_picking_sessions_state_' . $case->snake();
            $count     = $stats->$statField ?? 0;

            $metrics[] = [
                'key'   => $case->snake(),
                'label' => PickingSessionStateEnum::labels()[$case->value],
                'type'  => 'stat',
                'icon'  => $config['icon'],
                'tooltip' => PickingSessionStateEnum::labels()[$case->value],
            ];

            $dataGlobal[$case->snake()] = [
                'value'        => $count,
                'route_target' => [
                    'name'       => $config['route'],
                    'parameters' => $routeParams,
                ],
            ];

            $totals[$case->snake()] = ['value' => $count];
        }

        $total = $stats->number_picking_sessions ?? 0;

        return [
            'metrics'     => $metrics,
            'data'        => ['_global' => $dataGlobal],
            'row_totals'  => [
                '_global' => [
                    'value'        => $total,
                    'route_target' => [
                        'name'       => 'grp.org.warehouses.show.dispatching.picking_sessions.index',
                        'parameters' => $routeParams,
                    ],
                ],
            ],
            'totals'      => $totals,
            'grand_total' => [
                'value' => $total,
                'icon'  => ['fal', 'fa-arrow-from-left'],
                'tooltip' => 'Total'
            ],
        ];
    }

    private function dateRange(): array
    {
        $range = request()->input('between.date');
        $timezone = resolveTimezoneHeader();

        if ($range && count($parts = explode('-', $range)) === 2) {
            return [
                Carbon::createFromFormat('Ymd', trim($parts[0]), $timezone)->startOfDay()->setTimezone('UTC'),
                Carbon::createFromFormat('Ymd', trim($parts[1]), $timezone)->endOfDay()->setTimezone('UTC'),
            ];
        }

        return [Carbon::today($timezone)->setTimezone('UTC'), Carbon::today($timezone)->endOfDay()->setTimezone('UTC')];
    }

    private function getPickersStats(Warehouse $warehouse): array
    {
        $routeParams = ['organisation' => $this->organisation->slug, 'warehouse' => $warehouse->slug];

        $pickers = DB::table('delivery_notes')
            ->select(['delivery_notes.picker_user_id as user_id', 'users.contact_name'])
            ->leftJoin('users', 'users.id', '=', 'delivery_notes.picker_user_id')
            ->where('delivery_notes.warehouse_id', $warehouse->id)
            ->whereNotNull('delivery_notes.picker_user_id')
            ->groupBy('delivery_notes.picker_user_id', 'users.contact_name')
            ->orderBy('users.contact_name')
            ->get();

        $rangeStats     = $this->pickerRangeStats($warehouse);
        $skosByUser     = $this->pickerSkos($warehouse);
        $ordersByUser   = $this->pickerActiveOrders($warehouse, $routeParams);
        $trolleysByUser = $this->pickerTrolleys($warehouse, $routeParams);

        return $this->buildDashboardData($pickers, $rangeStats, $skosByUser, $ordersByUser, $trolleysByUser, 'Picker');
    }

    private function getPackersStats(Warehouse $warehouse): array
    {
        $routeParams = ['organisation' => $this->organisation->slug, 'warehouse' => $warehouse->slug];

        $packers = DB::table('delivery_notes')
            ->select(['delivery_notes.packer_user_id as user_id', 'users.contact_name'])
            ->leftJoin('users', 'users.id', '=', 'delivery_notes.packer_user_id')
            ->where('delivery_notes.warehouse_id', $warehouse->id)
            ->whereNotNull('delivery_notes.packer_user_id')
            ->groupBy('delivery_notes.packer_user_id', 'users.contact_name')
            ->orderBy('users.contact_name')
            ->get();

        $rangeStats     = $this->packerRangeStats($warehouse);
        $skosByUser     = $this->packerSkos($warehouse);
        $ordersByUser   = $this->packerActiveOrders($warehouse, $routeParams);
        $trolleysByUser = $this->packerTrolleys($warehouse, $routeParams);

        return $this->buildDashboardData($packers, $rangeStats, $skosByUser, $ordersByUser, $trolleysByUser, 'Packer');
    }

    private function pickerRangeStats(Warehouse $warehouse): \Illuminate\Support\Collection
    {
        [$start, $end] = $this->dateRange();

        return DB::table('delivery_notes')
            ->select([
                'picker_user_id as user_id',
                DB::raw('COUNT(id) as dns'),
                DB::raw('COALESCE(SUM(number_items),0) as items'),
                DB::raw('SUM(EXTRACT(EPOCH FROM (picked_at - handling_at))) as work_seconds'),
            ])
            ->where('warehouse_id', $warehouse->id)
            ->whereNotNull('picker_user_id')
            ->whereBetween('picked_at', [$start, $end])
            ->groupBy('picker_user_id')
            ->get()
            ->keyBy('user_id');
    }

    private function pickerSkos(Warehouse $warehouse): \Illuminate\Support\Collection
    {
        [$start, $end] = $this->dateRange();

        return DB::table('pickings')
            ->select(['pickings.picker_user_id as user_id', DB::raw('COUNT(DISTINCT pickings.org_stock_id) as skos')])
            ->join('delivery_notes', 'delivery_notes.id', '=', 'pickings.delivery_note_id')
            ->where('delivery_notes.warehouse_id', $warehouse->id)
            ->whereNotNull('pickings.picker_user_id')
            ->whereBetween('pickings.last_picked_at', [$start, $end])
            ->groupBy('pickings.picker_user_id')
            ->pluck('skos', 'user_id');
    }

    private function pickerActiveOrders(Warehouse $warehouse, array $routeParams): \Illuminate\Support\Collection
    {
        return DB::table('delivery_notes')
            ->select(['picker_user_id as user_id', 'reference', 'slug'])
            ->where('warehouse_id', $warehouse->id)
            ->whereNotNull('picker_user_id')
            ->whereIn('state', ['handling', 'handling_blocked'])
            ->get()
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->map(fn ($o) => [
                'reference' => $o->reference,
                'route'     => ['name' => 'grp.org.warehouses.show.dispatching.delivery_notes.show', 'parameters' => array_merge($routeParams, ['deliveryNote' => $o->slug])],
            ])->values()->all());
    }

    private function pickerTrolleys(Warehouse $warehouse, array $routeParams): \Illuminate\Support\Collection
    {
        return DB::table('trolleys')
            ->select(['delivery_notes.picker_user_id as user_id', 'trolleys.name', 'trolleys.slug'])
            ->join('delivery_notes', 'delivery_notes.id', '=', 'trolleys.current_delivery_note_id')
            ->where('trolleys.warehouse_id', $warehouse->id)
            ->whereNotNull('trolleys.current_delivery_note_id')
            ->whereNotNull('delivery_notes.picker_user_id')
            ->whereIn('delivery_notes.state', ['handling', 'handling_blocked'])
            ->get()
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->map(fn ($t) => [
                'reference' => $t->name,
                'route'     => ['name' => 'grp.org.warehouses.show.dispatching.trolleys.show', 'parameters' => array_merge($routeParams, ['trolley' => $t->slug])],
            ])->values()->all());
    }

    private function packerRangeStats(Warehouse $warehouse): \Illuminate\Support\Collection
    {
        [$start, $end] = $this->dateRange();

        return DB::table('delivery_notes')
            ->select([
                'packer_user_id as user_id',
                DB::raw('COUNT(id) as dns'),
                DB::raw('COALESCE(SUM(number_items),0) as items'),
                DB::raw('SUM(EXTRACT(EPOCH FROM (packed_at - packing_at))) as work_seconds'),
            ])
            ->where('warehouse_id', $warehouse->id)
            ->whereNotNull('packer_user_id')
            ->whereBetween('packed_at', [$start, $end])
            ->groupBy('packer_user_id')
            ->get()
            ->keyBy('user_id');
    }

    private function packerSkos(Warehouse $warehouse): \Illuminate\Support\Collection
    {
        [$start, $end] = $this->dateRange();

        return DB::table('delivery_note_items')
            ->select(['delivery_notes.packer_user_id as user_id', DB::raw('COUNT(DISTINCT delivery_note_items.org_stock_id) as skos')])
            ->join('delivery_notes', 'delivery_notes.id', '=', 'delivery_note_items.delivery_note_id')
            ->where('delivery_notes.warehouse_id', $warehouse->id)
            ->whereNotNull('delivery_notes.packer_user_id')
            ->whereBetween('delivery_notes.packed_at', [$start, $end])
            ->groupBy('delivery_notes.packer_user_id')
            ->pluck('skos', 'user_id');
    }

    private function packerActiveOrders(Warehouse $warehouse, array $routeParams): \Illuminate\Support\Collection
    {
        return DB::table('delivery_notes')
            ->select(['packer_user_id as user_id', 'reference', 'slug'])
            ->where('warehouse_id', $warehouse->id)
            ->whereNotNull('packer_user_id')
            ->whereIn('state', ['packing'])
            ->get()
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->map(fn ($o) => [
                'reference' => $o->reference,
                'route'     => ['name' => 'grp.org.warehouses.show.dispatching.delivery_notes.show', 'parameters' => array_merge($routeParams, ['deliveryNote' => $o->slug])],
            ])->values()->all());
    }

    private function packerTrolleys(Warehouse $warehouse, array $routeParams): \Illuminate\Support\Collection
    {
        return DB::table('trolleys')
            ->select(['delivery_notes.packer_user_id as user_id', 'trolleys.name', 'trolleys.slug'])
            ->join('delivery_notes', 'delivery_notes.id', '=', 'trolleys.current_delivery_note_id')
            ->where('trolleys.warehouse_id', $warehouse->id)
            ->whereNotNull('trolleys.current_delivery_note_id')
            ->whereNotNull('delivery_notes.packer_user_id')
            ->whereIn('delivery_notes.state', ['packing'])
            ->get()
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->map(fn ($t) => [
                'reference' => $t->name,
                'route'     => ['name' => 'grp.org.warehouses.show.dispatching.trolleys.show', 'parameters' => array_merge($routeParams, ['trolley' => $t->slug])],
            ])->values()->all());
    }

    private function buildDashboardData(
        \Illuminate\Support\Collection $users,
        \Illuminate\Support\Collection $rangeStats,
        \Illuminate\Support\Collection $skosByUser,
        \Illuminate\Support\Collection $ordersByUser,
        \Illuminate\Support\Collection $trolleysByUser,
        string $dimensionLabel
    ): array {
        $dimensionItems = [];
        $dataRows       = [];
        $rowTotals      = [];
        $totalDns       = 0;
        $totalItems     = 0;
        $totalSkos      = 0;
        $totalSeconds   = 0.0;

        foreach ($users as $user) {
            $stats = $rangeStats[$user->user_id] ?? null;
            $dns   = (int) ($stats->dns ?? 0);

            if ($dns === 0 && empty($ordersByUser[$user->user_id]) && empty($trolleysByUser[$user->user_id])) {
                continue;
            }

            $items   = (int) ($stats->items ?? 0);
            $seconds = (float) ($stats->work_seconds ?? 0);
            $skos    = (int) ($skosByUser[$user->user_id] ?? 0);

            $key = 'user_' . $user->user_id;

            $dimensionItems[] = ['key' => $key, 'label' => $user->contact_name];
            $rowTotals[$key]  = ['value' => $dns];

            $dataRows[$key] = [
                'orders'        => ['value' => count($ordersByUser[$user->user_id] ?? []), 'items' => $ordersByUser[$user->user_id] ?? []],
                'trolleys'      => ['value' => count($trolleysByUser[$user->user_id] ?? []), 'items' => $trolleysByUser[$user->user_id] ?? []],
                'items'         => ['value' => $items],
                'skos'          => ['value' => $skos],
                'avg_minutes'   => ['value' => $dns > 0 ? round($seconds / $dns / 60) : 0],
                'items_per_hour' => ['value' => $seconds > 0 ? round($items / ($seconds / 3600), 1) : 0],
            ];

            $totalDns     += $dns;
            $totalItems   += $items;
            $totalSkos    += $skos;
            $totalSeconds += $seconds;
        }

        return [
            'dimension' => [
                'key'   => 'user',
                'label' => $dimensionLabel,
                'items' => $dimensionItems,
            ],
            'metrics' => [
                ['key' => 'orders',         'label' => __('Orders'),      'type' => 'refs', 'icon' => ['fal', 'fa-file-alt'],     'tooltip' => __('Active orders currently being processed')],
                ['key' => 'trolleys',       'label' => __('Trolleys'),    'type' => 'refs', 'icon' => ['fal', 'fa-dolly'],        'tooltip' => __('Trolleys assigned to active orders')],
                ['key' => 'items',          'label' => __('Items'),       'type' => 'stat', 'icon' => ['fal', 'fa-box'],          'tooltip' => __('Items completed in the selected period')],
                ['key' => 'skos',           'label' => __('SKOs'),        'type' => 'stat', 'icon' => ['fal', 'fa-barcode'],      'tooltip' => __('Different SKOs handled in the selected period')],
                ['key' => 'avg_minutes',    'label' => __('Avg min/DN'),  'type' => 'stat', 'icon' => ['fal', 'fa-clock'],        'tooltip' => __('Average minutes to complete a delivery note')],
                ['key' => 'items_per_hour', 'label' => __('Items/hour'),  'type' => 'stat', 'icon' => ['fal', 'fa-chart-line'],   'tooltip' => __('Items completed per working hour')],
            ],
            'data'       => $dataRows,
            'row_totals' => $rowTotals,
            'totals' => [
                'orders'         => ['value' => collect($dataRows)->sum(fn ($r) => $r['orders']['value'])],
                'trolleys'       => ['value' => collect($dataRows)->sum(fn ($r) => $r['trolleys']['value'])],
                'items'          => ['value' => $totalItems],
                'skos'           => ['value' => $totalSkos],
                'avg_minutes'    => ['value' => $totalDns > 0 ? round($totalSeconds / $totalDns / 60) : 0],
                'items_per_hour' => ['value' => $totalSeconds > 0 ? round($totalItems / ($totalSeconds / 3600), 1) : 0],
            ],
            'grand_total' => [
                'value'   => $totalDns,
                'icon'    => ['fal', 'fa-check-double'],
                'tooltip' => __('Delivery notes completed in the selected period'),
            ],
        ];
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowGroupDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.dispatching.backlog',
                            'parameters' => $routeParameters
                        ],
                        'icon'  => ['fal', 'fa-arrow-from-left'],
                        'label' => __('Goods out'),
                    ]
                ]
            ]
        );
    }
}
