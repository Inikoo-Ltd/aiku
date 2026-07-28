<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:12 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNoteItem\UI;

use App\Actions\Dispatching\DeliveryNoteItem\UI\Traits\WithDeliveryNoteItemUI;
use App\Actions\OrgAction;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class IndexDeliveryNoteItems extends OrgAction
{
    use WithDeliveryNoteItemUI;

    public function handle(DeliveryNote $parent, $prefix = null, DeliveryNoteItemStateEnum|null $stateFilter = null, ?int $deliveryNoteItemId = null): LengthAwarePaginator
    {
        $globalSearch = $this->getGlobalSearchFilter();

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(DeliveryNoteItem::class);

        $query->where('delivery_note_items.delivery_note_id', $parent->id);

        if ($deliveryNoteItemId) {
            $query->where('delivery_note_items.id', $deliveryNoteItemId);
        }

        $this->applyDeliveryNoteItemBaseWiths($query);
        $this->applyDeliveryNoteItemBaseJoins($query);

        // An item can be packed in several goes, so the packings are aggregated per item. Joining
        // the table directly would return one row per packing and duplicate the item in the table.
        $query->leftJoinSub(
            DB::table('packings')
                ->select('delivery_note_item_id')
                ->selectRaw('sum(quantity) as total_quantity')
                ->selectRaw('count(*) as packings_count')
                ->groupBy('delivery_note_item_id'),
            'item_packings',
            'item_packings.delivery_note_item_id',
            '=',
            'delivery_note_items.id'
        );

        $packedSoFar = 'round(coalesce(item_packings.total_quantity, 0), 3)';
        $pickedTotal = 'round(delivery_note_items.quantity_picked, 3)';

        if ($stateFilter === DeliveryNoteItemStateEnum::PACKING) {
            $query->whereRaw("$packedSoFar < $pickedTotal")
                ->where('delivery_note_items.quantity_picked', '!=', 0);
        } elseif ($stateFilter) {
            $query->whereRaw("$packedSoFar >= $pickedTotal");
        }

        return $query->defaultSort('org_stocks.code')
            ->select(
                array_merge(
                    $this->getDeliveryNoteItemBaseSelect(),
                    [
                        'item_packings.total_quantity as packings_quantity',
                        'item_packings.packings_count as packings_count',
                        'org_stocks.main_batch_code_id as org_stocks_batch_code_id',
                        'org_stocks.current_batch_codes as org_stocks_batch_code_count',
                        'batch_codes.code as org_stocks_batch_code',
                        DB::raw("'{$parent->warehouse->slug}' as warehouse_slug"),
                        DB::raw("'{$parent->warehouse->code}' as warehouse_code"),
                    ]
                )
            )
            ->addSelect([
                'un_numbers' => $this->getUnNumbersSubquery(),
                'pickings'   => $this->getPickingsSubquery(),
            ])
            ->allowedSorts($this->getDeliveryNoteItemBaseSorts())
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(DeliveryNote $parent, $prefix = null, bool $isEditable = false): Closure
    {
        return function (InertiaTable $table) use ($parent, $prefix, $isEditable) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }


            $table
                ->withEmptyState(
                    [
                        'title' => __("No items found"),
                    ]
                );

            $table->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon');
            $allowAction = $this->canHandleDeliveryNote($parent);



            $this->addDeliveryNoteItemBaseTableColumns($table);


            $this->addDeliveryNoteItemQuantityTableColumns($table, $allowAction);
            if ($allowAction && $isEditable) {
                $table->column(key: 'action', label: __('Action'), canBeHidden: false, className: 'w-[250px]');
            }
        };
    }
}
