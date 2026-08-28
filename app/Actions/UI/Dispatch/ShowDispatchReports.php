<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 28 Aug 2026 20:30:00 British Summer Time, Sheffield, UK
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Dispatch;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithDispatchingAuthorisation;
use App\Http\Resources\Dispatching\DispatchPersonnelPerformanceResource;
use App\InertiaTable\InertiaTable;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowDispatchReports extends OrgAction
{
    use WithDispatchingAuthorisation;

    public function handle(Warehouse $warehouse): Warehouse
    {
        return $warehouse;
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): Warehouse
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($warehouse);
    }

    public function htmlResponse(Warehouse $warehouse, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Dispatching/DispatchReports',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Dispatching reports'),
                'pageHead'    => [
                    'title' => __('Dispatching Reports'),
                    'icon'  => [
                        'icon' => ['fal', 'fa-chart-line'],
                    ],
                ],
                'pickers' => DispatchPersonnelPerformanceResource::collection($this->pickerPerformance($warehouse, 'pickers')),
                'packers' => DispatchPersonnelPerformanceResource::collection($this->packerPerformance($warehouse, 'packers')),
            ]
        )
            ->table($this->performanceTableStructure('pickers', __('Picker'), withParcels: false))
            ->table($this->performanceTableStructure('packers', __('Packer'), withParcels: true));
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

    private function channelShopTypes(): ?array
    {
        return match (request()->input('channel')) {
            'wholesale'    => ['b2b', 'external'],
            'b2c'          => ['b2c'],
            'dropshipping' => ['dropshipping'],
            'fulfilment'   => ['fulfilment'],
            default        => null,
        };
    }

    private function personnelPaginator(\Illuminate\Database\Query\Builder $base, string $prefix, array $sorts): LengthAwarePaginator
    {
        InertiaTable::updateQueryBuilderParameters($prefix);

        return QueryBuilder::for(DeliveryNote::query()->withoutGlobalScopes()->fromSub($base, $prefix))
            ->defaultSort('name')
            ->allowedSorts($sorts)
            ->withPaginator($prefix, tableName: request()->route()?->getName())
            ->withQueryString();
    }

    private function pickerPerformance(Warehouse $warehouse, string $prefix): LengthAwarePaginator
    {
        [$start, $end] = $this->dateRange();

        $skos = DB::table('pickings')
            ->select(['pickings.picker_user_id as user_id', DB::raw('COUNT(DISTINCT pickings.org_stock_id) as skos')])
            ->join('delivery_notes', 'delivery_notes.id', '=', 'pickings.delivery_note_id')
            ->where('delivery_notes.warehouse_id', $warehouse->id)
            ->whereNotNull('pickings.picker_user_id')
            ->when($this->channelShopTypes(), fn ($q, $types) => $q->join('shops', 'shops.id', '=', 'delivery_notes.shop_id')->whereIn('shops.type', $types))
            ->whereBetween('pickings.last_picked_at', [$start, $end])
            ->groupBy('pickings.picker_user_id');

        $base = DB::table('delivery_notes')
            ->leftJoin('users', 'users.id', '=', 'delivery_notes.picker_user_id')
            ->leftJoinSub($skos, 'sk', 'sk.user_id', '=', 'delivery_notes.picker_user_id')
            ->where('delivery_notes.warehouse_id', $warehouse->id)
            ->whereNotNull('delivery_notes.picker_user_id')
            ->when($this->channelShopTypes(), fn ($q, $types) => $q->join('shops', 'shops.id', '=', 'delivery_notes.shop_id')->whereIn('shops.type', $types))
            ->whereBetween('delivery_notes.picked_at', [$start, $end])
            ->groupBy('delivery_notes.picker_user_id', 'users.contact_name', 'sk.skos')
            ->select([
                'delivery_notes.picker_user_id as user_id',
                'users.contact_name as name',
                DB::raw('COUNT(delivery_notes.id) as dns'),
                DB::raw('COALESCE(SUM(delivery_notes.number_items),0)::int as items'),
                DB::raw('COALESCE(sk.skos,0)::int as skos'),
                DB::raw('ROUND(COALESCE(SUM(delivery_notes.effective_weight),0)/1000.0, 1) as weight'),
                DB::raw('COALESCE(ROUND(SUM(EXTRACT(EPOCH FROM (delivery_notes.picked_at - delivery_notes.handling_at))) / NULLIF(COUNT(delivery_notes.id),0) / 60), 0) as avg_minutes'),
                DB::raw('COALESCE(ROUND((COALESCE(SUM(delivery_notes.number_items),0) / NULLIF(SUM(EXTRACT(EPOCH FROM (delivery_notes.picked_at - delivery_notes.handling_at))) / 3600.0, 0))::numeric, 1), 0) as items_per_hour'),
            ]);

        return $this->personnelPaginator($base, $prefix, ['name', 'dns', 'items', 'skos', 'weight', 'avg_minutes', 'items_per_hour']);
    }

    private function packerPerformance(Warehouse $warehouse, string $prefix): LengthAwarePaginator
    {
        [$start, $end] = $this->dateRange();

        $skos = DB::table('delivery_note_items')
            ->select(['delivery_notes.packer_user_id as user_id', DB::raw('COUNT(DISTINCT delivery_note_items.org_stock_id) as skos')])
            ->join('delivery_notes', 'delivery_notes.id', '=', 'delivery_note_items.delivery_note_id')
            ->where('delivery_notes.warehouse_id', $warehouse->id)
            ->whereNotNull('delivery_notes.packer_user_id')
            ->when($this->channelShopTypes(), fn ($q, $types) => $q->join('shops', 'shops.id', '=', 'delivery_notes.shop_id')->whereIn('shops.type', $types))
            ->whereBetween('delivery_notes.packed_at', [$start, $end])
            ->groupBy('delivery_notes.packer_user_id');

        $base = DB::table('delivery_notes')
            ->leftJoin('users', 'users.id', '=', 'delivery_notes.packer_user_id')
            ->leftJoinSub($skos, 'sk', 'sk.user_id', '=', 'delivery_notes.packer_user_id')
            ->where('delivery_notes.warehouse_id', $warehouse->id)
            ->whereNotNull('delivery_notes.packer_user_id')
            ->when($this->channelShopTypes(), fn ($q, $types) => $q->join('shops', 'shops.id', '=', 'delivery_notes.shop_id')->whereIn('shops.type', $types))
            ->whereBetween('delivery_notes.packed_at', [$start, $end])
            ->groupBy('delivery_notes.packer_user_id', 'users.contact_name', 'sk.skos')
            ->select([
                'delivery_notes.packer_user_id as user_id',
                'users.contact_name as name',
                DB::raw('COUNT(delivery_notes.id) as dns'),
                DB::raw('COALESCE(SUM(delivery_notes.number_items),0)::int as items'),
                DB::raw('COALESCE(sk.skos,0)::int as skos'),
                DB::raw('ROUND(COALESCE(SUM(delivery_notes.effective_weight),0)/1000.0, 1) as weight'),
                DB::raw("COALESCE(SUM(CASE WHEN jsonb_typeof(delivery_notes.parcels)='array' THEN jsonb_array_length(delivery_notes.parcels) ELSE 0 END),0)::int as parcels"),
                DB::raw('COALESCE(ROUND(SUM(EXTRACT(EPOCH FROM (delivery_notes.packed_at - delivery_notes.packing_at))) / NULLIF(COUNT(delivery_notes.id),0) / 60), 0) as avg_minutes'),
                DB::raw('COALESCE(ROUND((COALESCE(SUM(delivery_notes.number_items),0) / NULLIF(SUM(EXTRACT(EPOCH FROM (delivery_notes.packed_at - delivery_notes.packing_at))) / 3600.0, 0))::numeric, 1), 0) as items_per_hour'),
            ]);

        return $this->personnelPaginator($base, $prefix, ['name', 'dns', 'items', 'skos', 'weight', 'parcels', 'avg_minutes', 'items_per_hour']);
    }

    private function performanceTableStructure(string $prefix, string $dimensionLabel, bool $withParcels): Closure
    {
        return function (InertiaTable $table) use ($prefix, $dimensionLabel, $withParcels) {
            $table->name($prefix)->pageName($prefix.'Page');
            $table->withEmptyState([
                'icons' => ['fal fa-chart-line'],
                'title' => __('No completed work in the selected period'),
            ]);

            $table->column(key: 'name', label: $dimensionLabel, canBeHidden: false, sortable: true);
            $table->column(key: 'dns', label: __('Delivery notes'), sortable: true, align: 'right');
            $table->column(key: 'items', label: __('Items'), sortable: true, align: 'right');
            $table->column(key: 'skos', label: __('SKOs'), sortable: true, align: 'right');
            $table->column(key: 'weight', label: __('Weight'), sortable: true, align: 'right');
            if ($withParcels) {
                $table->column(key: 'parcels', label: __('Parcels'), sortable: true, align: 'right');
            }
            $table->column(key: 'avg_minutes', label: __('Avg min/DN'), sortable: true, align: 'right');
            $table->column(key: 'items_per_hour', label: __('Items/hour'), sortable: true, align: 'right');
            $table->defaultSort('name');
        };
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowDispatchHub::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.dispatching.reports',
                            'parameters' => $routeParameters
                        ],
                        'label' => __('Reports'),
                        'icon'  => 'fal fa-chart-line',
                    ]
                ]
            ]
        );
    }
}
