<?php

/*
 * author Arya Permana - Kirin
 * created on 21-02-2025-14h-59m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Fulfilment\StoredItemAudit\UI;

use App\Actions\Fulfilment\WithFulfilmentCustomerSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithFulfilmentShopAuthorisation;
use App\InertiaTable\InertiaTable;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\StoredItemAudit;
use App\Models\Fulfilment\StoredItemAuditDelta;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class IndexNewStoredItemDeltasInProcessForPallet extends OrgAction
{
    use WithFulfilmentShopAuthorisation;
    use WithFulfilmentCustomerSubNavigation;

    private FulfilmentCustomer $parent;
    private Pallet $pallet;

    public function handle(StoredItemAudit $storedItemAudit, $prefix = null): LengthAwarePaginator
    {
        $globalSearch       = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('stored_items.reference', $value)
                    ->orWhereWith('stored_items.name', $value);
            });
        });


        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(StoredItemAuditDelta::class);
        $query->where('stored_item_audit_deltas.is_stored_item_new_in_pallet', true)
        ->leftJoin('stored_items', 'stored_item_audit_deltas.stored_item_id', '=', 'stored_items.id')
        ->leftJoin('pallet_stored_items', function ($join) use ($storedItemAudit) {
            $join->on('pallet_stored_items.stored_item_id', '=', 'stored_item_audit_deltas.stored_item_id')
                ->where('pallet_stored_items.pallet_id', '=', $storedItemAudit->scope->id);
        })
        ->whereNull('pallet_stored_items.id');


        $query->defaultSort('stored_items.id')
            ->select(
                'stored_items.id',
                'stored_items.reference',
                'stored_items.slug',
                'stored_items.name',
                'stored_item_audit_deltas.notes as audit_notes',
                'stored_item_audit_deltas.audited_quantity',
                'stored_item_audit_deltas.state as delta_state',
                'stored_item_audit_deltas.audit_type',
                'stored_item_audit_deltas.id as stored_item_audit_delta_id'
            )->selectRaw("$storedItemAudit->id. as stored_item_audit_id");


        return $query->allowedSorts(['reference', 'name', 'audited_quantity'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Pallet $pallet, $prefix = null, $modelOperations = []): Closure
    {
        return function (InertiaTable $table) use ($prefix, $modelOperations, $pallet) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }


            $emptyStateData = [
                'icons'       => ['fal fa-pallet'],
                'title'       => __('No stored items found'),
                'count'       => $pallet->number_stored_items,
                'description' => __("This pallet don't have any SKUs")
            ];


            $table->withGlobalSearch();


            $table->withEmptyState($emptyStateData)
                ->withModelOperations($modelOperations);


            $table->column(key: 'reference', label: __('Reference'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'name', label: __("Name"), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'audited_quantity', label: __("Quantity"), canBeHidden: false);
            $table->column(key: 'actions', label: '', canBeHidden: false);

            $table->defaultSort('reference');
        };
    }


}
