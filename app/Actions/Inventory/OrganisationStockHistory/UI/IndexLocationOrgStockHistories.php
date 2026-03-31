<?php

/*
 * Author: Nickel
 * Created: Tue, 01 Apr 2026
 * Copyright (c) 2026, Inikoo LTD
 */

namespace App\Actions\Inventory\OrganisationStockHistory\UI;

use App\Actions\Inventory\UI\ShowInventoryDashboard;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithInventoryAuthorisation;
use App\Http\Resources\Inventory\LocationOrgStockHistoriesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexLocationOrgStockHistories extends OrgAction
{
    use WithInventoryAuthorisation;

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($organisation);
    }

    public function handle(Organisation $organisation): LengthAwarePaginator
    {
        $perPage = config('ui.table.records_per_page', 25);

        $query = DB::table('location_org_stock_histories as losh')
            ->join('org_stocks as os', 'os.id', '=', 'losh.org_stock_id')
            ->join('locations as l', 'l.id', '=', 'losh.location_id')
            ->select([
                'losh.id',
                'losh.date',
                'losh.actual_quantity_in_locations',
                'losh.quantity_in_locations',
                'os.code as stock_code',
                'os.name as stock_name',
                'os.slug as stock_slug',
                'l.code as location_code',
            ])
            ->where('os.organisation_id', $organisation->id)
            ->orderBy('losh.date', 'desc')
            ->orderBy('os.code')
            ->orderBy('l.code');

        $this->applyDateFilter($query);

        return $query->paginate(perPage: $perPage)->appends(request()->query());
    }

    private function applyDateFilter(Builder $query): void
    {
        $filters  = request()->input('between', []);
        $timezone = resolveTimezoneHeader();

        if (!isset($filters['date'])) {
            return;
        }

        $parts = explode('-', $filters['date']);

        if (count($parts) !== 2) {
            return;
        }

        [$start, $end] = array_map('trim', $parts);

        $startDate = Carbon::createFromFormat('Ymd', $start, $timezone)->setTimezone('UTC')->startOfDay()->toDateTimeString();
        $endDate   = Carbon::createFromFormat('Ymd', $end, $timezone)->setTimezone('UTC')->endOfDay()->toDateTimeString();

        $query->whereBetween('losh.date', [$startDate, $endDate]);
    }

    public function tableStructure(): Closure
    {
        return function (InertiaTable $table) {
            $table
                ->withLabelRecord([__('record'), __('records')])
                ->betweenDates(['date'])
                ->column(key: 'date', label: __('Date'), canBeHidden: false, sortable: false, type: 'date')
                ->column(key: 'stock_code', label: __('SKU Code'), canBeHidden: false)
                ->column(key: 'stock_name', label: __('SKU Name'), canBeHidden: false)
                ->column(key: 'location_code', label: __('Location'), canBeHidden: false)
                ->column(key: 'quantity_in_locations', label: __('Quantity'), canBeHidden: false, align: 'right')
                ->column(key: 'actual_quantity_in_locations', label: __('Actual Quantity'), canBeHidden: false, align: 'right');
        };
    }

    public function htmlResponse(LengthAwarePaginator $records, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Inventory/LocationOrgStockHistories',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Location Stock Histories'),
                'pageHead'    => [
                    'title' => __('Location Stock Histories'),
                    'icon'  => [
                        'icon'  => ['fal', 'fa-map-marker-alt'],
                        'title' => __('Location Stock Histories'),
                    ],
                ],
                'data' => LocationOrgStockHistoriesResource::collection($records),
            ]
        )->table($this->tableStructure());
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            IndexOrganisationStockHistories::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.inventory.org_stock_histories.location_histories.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Location Histories'),
                        'icon'  => 'fal fa-bars',
                    ],
                ],
            ]
        );
    }
}
