<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Tue, 01 Apr 2026
 * Copyright (c) 2026, Inikoo LTD
 */

namespace App\Actions\Inventory\OrgStock\UI;

use App\Actions\OrgAction;
use App\Enums\Inventory\OrgStock\OrgStockValuationMethodEnum;
use App\Actions\Traits\Authorisations\Inventory\WithInventoryAuthorisation;
use App\Http\Resources\Inventory\OrgStockHistoryResource;
use App\InertiaTable\InertiaTable;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockFamily;
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

class ShowOrgStockHistory extends OrgAction
{
    use WithInventoryAuthorisation;
    use WithOrgStockSubNavigation;

    private Organisation|OrgStockFamily $parent;
    private OrgStock $orgStock;

    public function asController(Organisation $organisation, Warehouse $warehouse, OrgStock $orgStock, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $organisation;
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($orgStock);
    }

    public function inStockFamily(Organisation $organisation, Warehouse $warehouse, OrgStockFamily $orgStockFamily, OrgStock $orgStock, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $orgStockFamily;
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($orgStock);
    }

    public function handle(OrgStock $orgStock): LengthAwarePaginator
    {
        $this->orgStock = $orgStock;
        $perPage        = config('ui.table.records_per_page', 25);

        $query = DB::table('org_stock_histories')
            ->where('org_stock_id', $orgStock->id)
            ->select([
                'id',
                'date',
                'quantity_in_locations',
                'org_stock_lpp_value',
                'grp_stock_lpp_value',
                'org_stock_wac_value',
                'org_stock_fifo_value',
                'number_locations',
                'lpp_per_sku',
                'wac_per_sku',
                'fifo_per_sku',
            ])
            ->orderBy('date', 'desc');

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

        $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function tableStructure(): Closure
    {
        return function (InertiaTable $table) {
            $table
                ->withLabelRecord([__('record'), __('records')])
                ->betweenDates(['date'])
                ->column(key: 'date', label: __('Date'), canBeHidden: false, sortable: false, type: 'date')
                ->column(key: 'quantity_in_locations', label: __('Quantity'), canBeHidden: false, align: 'right')
                ->column(key: 'number_locations', label: __('Number of Locations'), canBeHidden: false, align: 'right');

            foreach (OrgStockValuationMethodEnum::ordered() as $index => $method) {
                $table->column(key: $method->stockValueColumn(), label: __('Value').' ('.$method->label().')', tooltip: $method->legend(), tooltipIcon: $index === 0, canBeHidden: $index !== 0, align: 'right');
            }
            foreach (OrgStockValuationMethodEnum::ordered() as $index => $method) {
                $table->column(key: $method->perSkuColumn(), label: __('Per SKO').' ('.$method->label().')', tooltip: $method->legend(), tooltipIcon: $index === 0, canBeHidden: $index !== 0, align: 'right');
            }
        };
    }

    public function htmlResponse(LengthAwarePaginator $records, ActionRequest $request): Response
    {
        $subNavigation = $this->getOrgStockSubNavigation($this->orgStock, $request);

        return Inertia::render(
            'Org/Inventory/OrgStockHistory',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $this->orgStock,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'    => __('SKO').' '.$this->orgStock->code.' ('.__('Stock History').')',
                'pageHead' => [
                    'icon'          => [
                        'title' => __('SKO').' ('.__('Stock History').')',
                        'icon'  => 'fal fa-history',
                    ],
                    'model'         => __('SKO'),
                    'title'         => $this->orgStock->code,
                    'subNavigation' => $subNavigation,
                ],
                'download_route' => [
                    'name'       => $request->route()->getName().'.export',
                    'parameters' => $request->route()->originalParameters(),
                ],
                'data' => OrgStockHistoryResource::collection($records),
            ]
        )->table($this->tableStructure());
    }

    public function getBreadcrumbs(OrgStock $orgStock, string $routeName, array $routeParameters): array
    {
        $routeName = preg_replace('/.org_stock_history$/', '', $routeName);

        return ShowOrgStock::make()->getBreadcrumbs($orgStock, $routeName, $routeParameters, '('.__('Stock History').')');
    }
}
