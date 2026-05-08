<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 26 Jan 2024 18:40:36 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\StoredItem\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithFulfilmentShopAuthorisation;
use App\Enums\Fulfilment\StoredItem\StoredItemStateEnum;
use App\Models\CRM\Customer;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\StoredItem;
use App\Models\Fulfilment\StoredItemMovement;
use App\Models\Inventory\Warehouse;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\InertiaTable\InertiaTable;
use App\Models\Fulfilment\Pallet;
use Spatie\QueryBuilder\AllowedFilter;
use App\Services\QueryBuilder;

class IndexStoredItemMovements extends OrgAction
{
    use WithFulfilmentShopAuthorisation;
    private Warehouse|Fulfilment|Customer $parent;
    /**
     * @var true
     */
    private bool $selectStoredPallets = false;

    protected function getElementGroups(): array
    {
        return [
            'state' => [
                'label'    => __('State'),
                'elements' => array_merge_recursive(
                    StoredItemStateEnum::labels(),
                    StoredItemStateEnum::count($this->organisation)
                ),

                'engine' => function ($query, $elements) {
                    $query->whereIn('state', $elements);
                }

            ],

        ];
    }

    public function handle(StoredItem|Pallet $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('pallets.reference', $value)
                    ->orWhereWith('pallets.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(StoredItemMovement::class);


        $query->leftJoin('locations', 'locations.id', 'stored_item_movements.location_id');
        $query->leftJoin('stored_items', 'stored_items.id', 'stored_item_movements.stored_item_id');
        $query->leftJoin('pallets', 'pallets.id', 'stored_item_movements.pallet_id');
        $query->leftJoin('stored_item_audits', 'stored_item_audits.id', 'stored_item_movements.stored_item_audit_id');
        $query->leftJoin('stored_item_audit_deltas', 'stored_item_audit_deltas.id', 'stored_item_movements.stored_item_audit_delta_id');
        $query->leftJoin('pallet_deliveries', 'pallet_deliveries.id', 'stored_item_movements.pallet_delivery_id');
        $query->leftJoin('pallet_returns', 'pallet_returns.id', 'stored_item_movements.pallet_return_id');

        $query->defaultSort('stored_item_movements.id')
            ->select(
                'stored_item_movements.id',
                'stored_item_movements.quantity as delta',
                'stored_item_movements.type',
                'stored_items.id as stored_item_id',
                'stored_items.reference as stored_item_reference',
                'pallets.slug as pallet_slug',
                'pallets.reference as pallet_reference',
                'locations.slug as location_slug',
                'locations.slug as location_code',
                'stored_item_audits.reference as stored_item_audit_reference',
                'stored_item_audit_deltas.id as stored_item_audit_delta_id',
                'pallet_deliveries.reference as pallet_delivery_reference',
                'pallet_returns.reference as pallet_returns_reference'
            );

        $allowedSort = ['id', 'description', 'delta'];

        if ($parent instanceof Pallet) {
            $allowedSort = array_merge($allowedSort, ['stored_item_reference']);
            $query->where('stored_item_movements.pallet_id', $parent->id);
        } else {
            $allowedSort = array_merge($allowedSort, ['pallet_reference']);
            $query->where('stored_item_movements.stored_item_id', $parent->id);
        }

        return $query->defaultSort('pallets.id')
            ->allowedSorts($allowedSort)
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();

    }

    public function tableStructure(StoredItem|Pallet $parent, $prefix = null, $modelOperations = []): Closure
    {
        return function (InertiaTable $table) use ($parent, $prefix, $modelOperations) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }


            $emptyStateData = [
                'icons' => ['fal fa-pallet'],
                'title' => __("No movement exist"),
                'count' => 0
            ];

            $table->withGlobalSearch()
                ->withEmptyState($emptyStateData)
                ->withModelOperations($modelOperations);

            $table->column(key: 'description', label: __('Parent'), canBeHidden: false, sortable: false, searchable: false);
            if ($parent instanceof StoredItem) {
                $table->column(key: 'pallet_reference', label: __('Pallet Reference'), canBeHidden: false, sortable: true, searchable: true)->defaultSort('pallet_reference');
            }

            if ($parent instanceof Pallet) {
                $table->column(key: 'stored_item_reference', label: __('SKU'), canBeHidden: false, sortable: true, searchable: true)->defaultSort('pallet_reference');
            }


            $table->column(key: 'location_code', label: __('Location'), canBeHidden: false, searchable: true);

            $table->column(key: 'delta', label: __('Delta'), canBeHidden: false, searchable: true, sortable: true);
        };
    }
}
