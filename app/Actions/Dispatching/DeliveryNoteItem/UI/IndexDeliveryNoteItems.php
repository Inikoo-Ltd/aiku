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

        $query->leftJoin('packings', function ($join) {
            $join->on('packings.delivery_note_item_id', 'delivery_note_items.id');
        });

        if ($stateFilter === DeliveryNoteItemStateEnum::PACKING) {
            $query->whereNull('packings.id')
                ->where('delivery_note_items.quantity_picked', '!=', 0);
        } elseif ($stateFilter) {
            $query->where(function ($query) {
                $query->whereNotNull('packings.id')
                    ->orWhereColumn('delivery_note_items.quantity_picked', 'delivery_note_items.quantity_packed');
            });
        }

        return $query->defaultSort('org_stocks.code')
            ->select(
                array_merge(
                    $this->getDeliveryNoteItemBaseSelect(),
                    [
                        'org_stocks.main_batch_code_id as org_stocks_batch_code_id',
                        'org_stocks.current_batch_codes as org_stocks_batch_code_count',
                        'batch_codes.code as org_stocks_batch_code',
                        'packings.id as packing_id',
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
            $this->addDeliveryNoteItemBaseTableColumns($table);

            $allowAction = $this->canHandleDeliveryNote($parent);

            $this->addDeliveryNoteItemQuantityTableColumns($table, $allowAction);
            if ($allowAction && $isEditable) {
                $table->column(key: 'action', label: __('Action'), canBeHidden: false, className: 'w-[250px]');
            }
        };
    }
}
