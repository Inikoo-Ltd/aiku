<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Jun 2025 13:26:41 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNoteItem\UI;

use App\Actions\Dispatching\DeliveryNote\WithDeliveryNotePackaging;
use App\Actions\Dispatching\PartnerStaging\PartnerBayPickingOrder;
use App\Actions\Dispatching\DeliveryNoteItem\UI\Traits\WithDeliveryNoteItemUI;
use App\Actions\OrgAction;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Enums\UI\Dispatch\DeliveryNoteTabsEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class IndexDeliveryNoteItemsStateHandling extends OrgAction
{
    use WithDeliveryNoteItemUI;
    use WithDeliveryNotePackaging;

    /**
     * $isHandled splits the delivery note in two: the items the picker still has to walk to, and the
     * ones already finished. Null keeps them together, which is what every screen outside the picking
     * scan tabs asks for. A dirty item counts as unfinished, since it is waiting to be picked again.
     */
    public function handle(DeliveryNote $parent, $prefix = null, bool $ignoreParentPagination = false, array|DeliveryNoteItemStateEnum|null $stateFilter = null, ?int $deliveryNoteItemId = null, ?bool $isHandled = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return $this->itemsQuery($parent->organisation->slug, $stateFilter, $deliveryNoteItemId, $isHandled)
            ->where('delivery_note_items.delivery_note_id', $parent->id)
            ->withPaginator($ignoreParentPagination ? 'deliveryNoteItems' : $prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    /**
     * The items of several delivery notes of one organisation in a single query, keyed by delivery
     * note, each list being what handle() gives for that note with $ignoreParentPagination.
     *
     * @param  EloquentCollection<int, DeliveryNote>  $deliveryNotes
     * @return Collection<int, Collection<int, DeliveryNoteItem>>
     */
    public function forDeliveryNotes(EloquentCollection $deliveryNotes): Collection
    {
        if ($deliveryNotes->isEmpty()) {
            return collect();
        }

        $perPage = QueryBuilder::recordsPerPage('deliveryNoteItems', tableName: request()->route()->getName());
        $page    = Paginator::resolveCurrentPage('deliveryNoteItemsPage');

        return $this->itemsQuery($deliveryNotes->first()->organisation->slug)
            ->whereIn('delivery_note_items.delivery_note_id', $deliveryNotes->modelKeys())
            ->addSelect('delivery_note_items.delivery_note_id')
            ->get()
            ->groupBy('delivery_note_id')
            ->map(fn (EloquentCollection $items) => $items->forPage($page, $perPage)->values());
    }

    private function itemsQuery(string $organisationSlug, array|DeliveryNoteItemStateEnum|null $stateFilter = null, ?int $deliveryNoteItemId = null, ?bool $isHandled = null): QueryBuilder
    {
        $globalSearch = $this->getGlobalSearchFilter();

        $query = QueryBuilder::for(DeliveryNoteItem::class);

        if ($deliveryNoteItemId) {
            $query->where('delivery_note_items.id', $deliveryNoteItemId);
        }

        if ($stateFilter) {
            if (is_array($stateFilter)) {
                $query->whereIn('delivery_note_items.state', $stateFilter);
            } else {
                $query->where('delivery_note_items.state', $stateFilter);
            }
        }

        $this->applyDeliveryNoteItemBaseWiths($query);
        $this->applyDeliveryNoteItemBaseJoins($query);
        $this->applyDeliveryNoteItemPickingJoins($query);

        $query->leftJoin('batch_codes as org_stock_batch_code', 'org_stocks.main_batch_code_id', '=', 'org_stock_batch_code.id');
        $query->leftjoin('shops', 'shops.id', '=', 'delivery_note_items.shop_id');
        $query->leftJoin('transactions', 'transactions.id', '=', 'delivery_note_items.transaction_id');

        $query->where(function ($q) {
            $q->where('delivery_note_items.quantity_required', '>', 0)
                ->orWhere('delivery_note_items.is_dirty', true);
        });

        if (!is_null($isHandled)) {
            $query->where('delivery_note_items.is_handled', $isHandled);
        }

        return $query
            ->defaultSort(['locations.sort_code', 'org_stocks.code'])
            ->select(array_merge(
                $this->getDeliveryNoteItemBaseSelect(),
                $this->getDeliveryNoteItemPickingSelect(),
                [
                    'delivery_note_items.notes',
                    'org_stocks.main_batch_code_id as org_stocks_batch_code_id',
                    'org_stocks.current_batch_codes as org_stocks_batch_code_count',
                    'org_stock_batch_code.code as org_stocks_batch_code',
                    'transactions.quantity_ordered as transaction_quantity_ordered',
                    'shops.slug as shop_slug',
                    'shops.type as shop_type',
                    DB::raw("'$organisationSlug' as organisation_slug"),
                ]
            ))
            ->addSelect([
                 'un_numbers' => $this->getUnNumbersSubquery(),
                'indivisible_set' => $this->getIndivisibleSetSubquery(),
                'location_org_stocks' => DB::table('location_org_stocks')
                    ->leftJoin('locations', 'location_org_stocks.location_id', '=', 'locations.id')
                    ->whereColumn('location_org_stocks.org_stock_id', 'org_stocks.id')
                    ->selectRaw("
                        jsonb_agg(
                            jsonb_build_object(
                                'id', location_org_stocks.id,
                                'quantity', location_org_stocks.quantity,
                                'type', location_org_stocks.type,
                                'location_id', locations.id,
                                'location_slug', locations.slug,
                                'location_code', locations.code,
                                'org_stock_packed_in', org_stocks.packed_in,
                                'pickings_data', (
                                    SELECT concat(
                                        coalesce(sum(quantity), 0),
                                        ';',
                                        coalesce(string_agg(id::char, ','), '')
                                    )
                                    FROM pickings
                                    WHERE pickings.location_id = location_org_stocks.location_id
                                    AND pickings.org_stock_id = location_org_stocks.org_stock_id
                                AND pickings.type = 'pick'
                                    AND pickings.delivery_note_item_id = delivery_note_items.id
                                )
                            )
                            ORDER BY
                                ".PartnerBayPickingOrder::sql('delivery_note_items.id').",
                                CASE
                                    WHEN shops.type = 'b2b'
                                        THEN location_org_stocks.default_wholesale_picking_location::int
                                    ELSE
                                        location_org_stocks.default_dropshipping_picking_location::int
                                END DESC,
                                picking_priority
                        )
                    ")
            ])
            ->allowedSorts(array_merge($this->getDeliveryNoteItemBaseSorts(), ['picking_position']))
            ->orderBy('org_stocks.code')
            ->orderBy('delivery_note_items.id')
            ->allowedFilters([$globalSearch]);
    }

    /**
     * The two picking tabs are empty for opposite reasons, and each one says which: nothing left to
     * pick means the picking is over, nothing picked yet means it has not started. Either way the tab
     * carries the next step, so the picker does not have to go looking for it.
     *
     * An action only names its button. Finishing the picking asks for a picked bay first, and opening
     * the other tab is a tab change, so both belong to the frontend rather than a route sent from here.
     */
    protected function emptyState(?string $prefix, bool $canFinishPicking): array
    {
        if ($prefix == DeliveryNoteTabsEnum::PICKING_TODO_ITEMS->value) {
            $emptyState = [
                'icons'       => ['fal', 'fa-clipboard-check'],
                'title'       => __('All items are handled'),
                'description' => __('Nothing on this delivery note is waiting to be picked any more.'),
                'count'       => 0,
            ];

            if ($canFinishPicking) {
                $emptyState['action'] = ['key' => 'finish-picking'];
            }

            return $emptyState;
        }

        if ($prefix == DeliveryNoteTabsEnum::PICKING_DONE_ITEMS->value) {
            return [
                'icons'       => ['fal', 'fa-clipboard-list-check'],
                'title'       => __('Nothing picked yet'),
                'description' => __('Every item on this delivery note is still waiting to be picked.'),
                'count'       => 0,
                'action'      => ['key' => 'open-todo-items'],
            ];
        }

        return [
            'title' => __('item empty'),
        ];
    }

    public function tableStructure($prefix = null, ?DeliveryNote $deliveryNote = null, bool $isEditable = false): Closure
    {
        return function (InertiaTable $table) use ($prefix, $deliveryNote, $isEditable) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }


            $table
                ->withLabelRecord([__("Part"), __("Parts")])
                ->withEmptyState(
                    [
                        'title' => __("delivery note empty"),
                    ]
                )->defaultSort('picking_position');

            $allowAction = $this->canHandleDeliveryNote($deliveryNote);

            $table
                ->withLabelRecord([__('item'), __('items')])
                ->withEmptyState($this->emptyState($prefix, $allowAction && $isEditable))
                ->defaultSort('picking_position');

            if ($allowAction && $isEditable) {
                $table->column(key: 'picking_position', label: __('To do actions'), canBeHidden: false, sortable: true);
            }

            $this->addDeliveryNoteItemBaseTableColumns($table);

            $this->addDeliveryNoteItemQuantityTableColumns($table, $allowAction, false);
            if ($allowAction) {
                $table->column(key: 'pickings', label: __('Pickings'), canBeHidden: false);
            }


            if ($deliveryNote && $this->effectivePackaging($deliveryNote)) {
                $table->column(key: 'packaging', label: __('Packaging'), canBeHidden: false);
            }

            if ($deliveryNote?->shop?->hasPackagingAndInserts() && $deliveryNote->leaflets->isNotEmpty()) {
                $table->column(key: 'leaflets', label: __('Inserts to print'), canBeHidden: false);
                $table->column(key: 'print_status', label: __('Print all inserts'), canBeHidden: false);
            }
        };
    }
}
