<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 08 Apr 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNoteItem\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithDispatchingAuthorisation;
use App\Actions\UI\Dispatch\ShowDispatchHub;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Http\Resources\Dispatching\WaitingDeliveryNoteItemsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexWaitingDeliveryNoteItems extends OrgAction
{
    use WithDispatchingAuthorisation;

    public function handle(Warehouse $warehouse, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('org_stocks.code', $value)
                    ->orWhereStartWith('org_stocks.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(DeliveryNoteItem::class);

        $query->join('delivery_notes', 'delivery_note_items.delivery_note_id', '=', 'delivery_notes.id')
            ->leftJoin('org_stocks', 'delivery_note_items.org_stock_id', '=', 'org_stocks.id')
            ->where('delivery_notes.warehouse_id', $warehouse->id)
            ->where('delivery_note_items.state', DeliveryNoteItemStateEnum::HANDLING_BLOCKED);

        return $query->defaultSort('delivery_note_items.id')
            ->select([
                'delivery_note_items.id',
                'delivery_note_items.quantity_required',
                'delivery_note_items.quantity_picked',
                'delivery_note_items.state',
                'org_stocks.id as org_stock_id',
                'org_stocks.code as org_stock_code',
                'org_stocks.name as org_stock_name',
                DB::raw('(delivery_note_items.quantity_required - COALESCE(delivery_note_items.quantity_picked, 0)) as quantity_waiting'),
            ])
            ->allowedSorts(['org_stock_name', 'org_stock_code', 'quantity_waiting'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table->withEmptyState([
                'title' => __('No waiting items found'),
            ]);

            $table->column(key: 'org_stock_name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'quantity_waiting', label: __('Quantity Waiting'), canBeHidden: false, sortable: true, align: 'right');
            $table->column(key: 'action', label: __('Action'), canBeHidden: false);
        };
    }

    public function htmlResponse(LengthAwarePaginator $items, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Dispatching/WaitingDeliveryNoteItems',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Waiting Items') . ' (' . $items->total() . ')',
                'pageHead'    => [
                    'title' => __('Waiting Items'),
                    'model' => __('Delivery Note'),
                    'icon'  => [
                        'icon'  => ['fal', 'fa-hourglass-start'],
                        'title' => __('Waiting Items'),
                    ],
                ],
                'data' => WaitingDeliveryNoteItemsResource::collection($items),
            ]
        )->table($this->tableStructure());
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($warehouse);
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
                            'name'       => 'grp.org.warehouses.show.dispatching.waiting_items',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Waiting Items'),
                        'icon'  => 'fal fa-bars',
                    ],
                ],
            ]
        );
    }
}
