<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 24 Apr 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Dispatching\BatchCode\UI;

use App\InertiaTable\InertiaTable;
use App\Models\Dispatching\BatchCode;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Picking;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\QueryBuilder\AllowedFilter;

class IndexDeliveryNotesInBatchCode
{
    use AsAction;

    public function handle(BatchCode $batchCode, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereWith('delivery_notes.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $pickingSessionsCount = function ($q) {
            $q->selectRaw('COALESCE(COUNT(picking_session_id), 0)')
                ->from('picking_session_has_delivery_notes')
                ->whereColumn('picking_session_has_delivery_notes.delivery_note_id', 'delivery_notes.id');
        };

        $pickingSessionIds = function ($q) {
            $q->selectRaw("COALESCE(STRING_AGG(CAST(picking_session_id AS VARCHAR), ','), '')")
                ->from('picking_session_has_delivery_notes')
                ->whereColumn('picking_session_has_delivery_notes.delivery_note_id', 'delivery_notes.id');
        };

        return QueryBuilder::for(DeliveryNote::class)
            ->whereIn(
                'delivery_notes.id',
                Picking::query()
                    ->join('delivery_note_items', 'pickings.delivery_note_item_id', '=', 'delivery_note_items.id')
                    ->where('pickings.batch_code_id', $batchCode->id)
                    ->select('delivery_note_items.delivery_note_id')
                    ->distinct()
            )
            ->leftJoin('customers', 'delivery_notes.customer_id', '=', 'customers.id')
            ->leftJoin('shops', 'delivery_notes.shop_id', '=', 'shops.id')
            ->leftJoin('organisations', 'delivery_notes.organisation_id', '=', 'organisations.id')
            ->defaultSort('-delivery_notes.date')
            ->select([
                'delivery_notes.id',
                'delivery_notes.reference',
                'delivery_notes.date',
                'delivery_notes.state',
                'delivery_notes.slug',
                'delivery_notes.number_items',
                'delivery_notes.data',
                'delivery_notes.effective_weight',
                'delivery_notes.estimated_weight',
                'delivery_notes.weight',
                'delivery_notes.type',
                'delivery_notes.is_premium_dispatch',
                'delivery_notes.has_extra_packing',
                'delivery_notes.shipping_data',
                'delivery_notes.customer_notes',
                'delivery_notes.internal_notes',
                'delivery_notes.public_notes',
                'delivery_notes.shipping_notes',
                'customers.slug as customer_slug',
                'customers.name as customer_name',
                'shops.slug as shop_slug',
                'shops.name as shop_name',
                'organisations.slug as organisation_slug',
                'organisations.name as organisation_name',
            ])
            ->selectSub($pickingSessionsCount, 'picking_sessions_count')
            ->selectSub($pickingSessionIds, 'picking_session_ids')
            ->allowedSorts(['reference', 'date', 'number_items'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?string $prefix = null, ?array $exportLinks = null): Closure
    {
        return function (InertiaTable $table) use ($prefix, $exportLinks) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState(['title' => __('No delivery notes found')]);

            if ($exportLinks) {
                $table->withExportLinks($exportLinks);
            }

            $table
                ->column(key: 'state', label: '', type: 'icon')
                ->column(key: 'reference', label: __('Reference'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'date', label: __('Date'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'customer_name', label: __('Customer'), canBeHidden: false, sortable: true)
                ->column(key: 'number_items', label: __('Items'), canBeHidden: false, sortable: true);
        };
    }
}
