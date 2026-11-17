<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 17 Feb 2026 14:45:33 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote\UI;

use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateQuantityInLocations;
use App\Actions\Inventory\OrgStock\UI\ShowOrgStock;
use App\Actions\Inventory\OrgStock\UI\WithOrgStockSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithInventoryAuthorisation;
use App\Http\Resources\Dispatching\DeliveryNotesInOrgStockResource;
use App\InertiaTable\InertiaTable;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockFamily;
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

class IndexDeliveryNotesInOrgStock extends OrgAction
{
    use WithDeliveryNotesSubNavigation;
    use WithInventoryAuthorisation;
    use WithOrgStockSubNavigation;


    public function handle(OrgStock $orgStock, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereWith('delivery_notes.reference', $value)
                    ->orWhereWith('delivery_notes.tracking_number', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(DeliveryNoteItem::class);

        $query->leftjoin('delivery_notes', 'delivery_note_items.delivery_note_id', '=', 'delivery_notes.id');

        $query->leftJoin('customers', 'delivery_notes.customer_id', '=', 'customers.id');
        $query->leftJoin('transactions', 'delivery_note_items.transaction_id', '=', 'transactions.id');
        $query->leftJoin('assets', 'transactions.asset_id', '=', 'assets.id');

        $query->where('delivery_note_items.org_stock_id', $orgStock->id);

        foreach ($this->getElementGroups() as $key => $elementGroup) {
            $query->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix,
            );
        }

        return $query->defaultSort('-date')
            ->select([
                'delivery_notes.id',
                'delivery_notes.reference',
                'delivery_notes.date',
                'delivery_notes.date as delivery_note_date',
                // 'delivery_note_items.date',
                'delivery_note_items.state',
                'delivery_note_items.quantity_required',
                'delivery_note_items.quantity_picked',
                'delivery_note_items.quantity_not_picked',
                'delivery_note_items.quantity_packed',
                'delivery_note_items.quantity_dispatched',
                'customers.name as customer_name',
                'assets.code as product_code',
            ])
            ->selectRaw($orgStock->packed_in ?? 0 .' as packed_in') // handle sql fix if packed_in is not present
            ->allowedSorts(['reference', 'date', 'state', 'quantity_required', 'quantity_picked', 'quantity_packed', 'customer_name', 'product_code'])
            ->allowedFilters([$globalSearch])
            ->withBetweenDates(['delivery_notes.date'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    /**
     * Waiting is what OrgStockHydrateQuantityInLocations::getQuantityToBePicked holds back from sale.
     */
    protected function getElementGroups(?OrgStock $orgStock = null): array
    {
        $openStates = OrgStockHydrateQuantityInLocations::make()->openDeliveryNoteStates();
        $waitingCount = 0;
        $totalCount   = 0;

        if ($orgStock) {
            $counts = DB::table('delivery_note_items')
                ->leftJoin('delivery_notes', 'delivery_note_items.delivery_note_id', '=', 'delivery_notes.id')
                ->where('delivery_note_items.org_stock_id', $orgStock->id)
                ->selectRaw('count(*) as total')
                ->selectRaw(
                    'count(*) filter (where delivery_note_items.state in ('.implode(',', array_fill(0, count($openStates), '?')).') and delivery_notes.state in ('.implode(',', array_fill(0, count($openStates), '?')).') and delivery_notes.deleted_at is null) as waiting',
                    [...$openStates, ...$openStates]
                )
                ->first();

            $waitingCount = (int) $counts->waiting;
            $totalCount   = (int) $counts->total;
        }

        return [
            'state' => [
                'label'    => __('State'),
                'elements' => [
                    'waiting' => [__('Waiting to be picked'), $waitingCount],
                    'done'    => [__('Picked or closed'), $totalCount - $waitingCount],
                ],
                'engine'   => function ($query, $elements) use ($openStates) {
                    $waiting = fn ($query) => $query->whereIn('delivery_note_items.state', $openStates)
                        ->whereIn('delivery_notes.state', $openStates)
                        ->whereNull('delivery_notes.deleted_at');

                    if (in_array('waiting', $elements)) {
                        $query->where($waiting);
                    } else {
                        $query->whereNot($waiting);
                    }
                },
            ],
        ];
    }

    public function tableStructure(OrgStock $orgStock, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($orgStock, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }



            foreach ($this->getElementGroups($orgStock) as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements'],
                );
            }

            $noResults = __("No delivery notes found");
            $count     = 0;


            $table
                ->withGlobalSearch()
                ->betweenDates(['date'])
                ->withEmptyState(
                    [
                        'title' => $noResults,
                        'count' => $count
                    ]
                )->defaultSort('-date');


            $table->column(key: 'reference', label: __('Delivery Note'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'date', label: __('Date'), canBeHidden: false, sortable: true, searchable: true, align: 'right');

            $table->column(key: 'customer_name', label: __('Customer'), canBeHidden: false, sortable: true);
            $table->column(key: 'product_code', label: __('Product'), canBeHidden: false, sortable: true);
            $table->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon');
            $table->column(key: 'quantity_required', label: __('Required'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
            $table->column(key: 'quantity_picked', label: __('Picked'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
            $table->column(key: 'quantity_packed', label: __('Packed'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
        };
    }


    public function htmlResponse(LengthAwarePaginator $deliveryNotes, ActionRequest $request): Response
    {
        /** @var OrgStock $orgStock */
        $orgStock      = $request->route()->parameter('orgStock');
        $subNavigation = $this->getOrgStockSubNavigation($orgStock, $request);


        $title      = __('Delivery notes');
        $model      = '';
        $icon       = [
            'icon'  => ['fal', 'fa-truck'],
            'title' => $title
        ];
        $afterTitle = null;
        $iconRight  = null;
        $actions    = null;


        return Inertia::render(
            'Org/Inventory/DeliveryNotesInOrgStock',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $orgStock,
                    $request->route()->getName(),
                    $request->route()->originalParameters(),
                ),
                'title'       => $title,
                'pageHead'    => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'subNavigation' => $subNavigation,
                    'actions'       => $actions
                ],
                'data'        => DeliveryNotesInOrgStockResource::collection($deliveryNotes),

            ]
        )->table($this->tableStructure(orgStock: $orgStock));
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, OrgStock $orgStock, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle(orgStock: $orgStock);
    }

    public function inStockFamily(Organisation $organisation, Warehouse $warehouse, OrgStockFamily $orgStockFamily, OrgStock $orgStock, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle(orgStock: $orgStock);
    }


    public function getBreadcrumbs(OrgStock $orgStock, string $routeName, array $routeParameters): array
    {
        $routeName = preg_replace('/.delivery_notes$/', '', $routeName);

        return ShowOrgStock::make()->getBreadcrumbs($orgStock, $routeName, $routeParameters, '('.__('Delivery notes').')');
    }
}
