<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Jul 2024 13:54:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\StoredItem\UI;

use App\Actions\OrgAction;
use App\Enums\Fulfilment\StoredItem\StoredItemStateEnum;
use App\Http\Resources\Fulfilment\ReturnStoredItemsResource;
use App\InertiaTable\InertiaTable;
use App\Models\CRM\WebUser;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\PalletStoredItem;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexPalletStoredItems extends OrgAction
{
    protected function getElementGroups(FulfilmentCustomer $parent): array
    {
        return [
            'status' => [
                'label'    => __('State'),
                'elements' => array_merge_recursive(
                    StoredItemStateEnum::labels(),
                    StoredItemStateEnum::count($parent)
                ),

                'engine' => function ($query, $elements) {
                    $query->whereIn('stored_items.state', $elements);
                }

            ]
        ];
    }

    public function handle(Group|FulfilmentCustomer|Pallet|Warehouse $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereWith('pallets.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(PalletStoredItem::class);
        $queryBuilder->join('stored_items', 'pallet_stored_items.stored_item_id', '=', 'stored_items.id');
        $queryBuilder->join('pallets', 'pallet_stored_items.pallet_id', '=', 'pallets.id');
        $queryBuilder->join('locations', 'pallets.location_id', '=', 'locations.id');

        if ($parent instanceof FulfilmentCustomer) {
            foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
                $queryBuilder->whereElementGroup(
                    key: $key,
                    allowedElements: array_keys($elementGroup['elements']),
                    engine: $elementGroup['engine'],
                    prefix: $prefix
                );
            }
            $queryBuilder->where('stored_items.fulfilment_customer_id', $parent->id);
        }

        if ($parent instanceof Pallet) {
            $queryBuilder->where('pallet_stored_items.pallet_id', $parent->id);
        }

        $queryBuilder
            ->select([
                'pallet_stored_items.id',
                'pallets.id as pallet_id',
                'pallets.slug as pallet_slug',
                'pallets.reference as pallet_reference',
                'stored_items.id as stored_item_id',
                'stored_items.reference as stored_item_reference',
                'stored_items.slug as stored_item_slug',
                'stored_items.state as stored_item_state',
                'stored_items.name as stored_item_name',
                'pallet_stored_items.quantity',
                'pallet_stored_items.damaged_quantity',
                'locations.code as location_code'
            ])
            ->defaultSort($parent instanceof Pallet ? 'stored_item_reference' : 'pallet_reference');


        return $queryBuilder->allowedSorts(['stored_item_reference', 'quantity', 'code', 'price', 'name', 'state', 'pallet_reference', 'slug','stored_item_name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Group|FulfilmentCustomer|Pallet|Warehouse $parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            if ($parent instanceof FulfilmentCustomer) {
                foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
                    $table->elementGroup(
                        key: $key,
                        label: $elementGroup['label'],
                        elements: $elementGroup['elements']
                    );
                }
            }

            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                    match (class_basename($parent)) {
                        'FulfilmentCustomer' => [
                            'title'       => __("No stored items found"),
                            'count'       => $parent->number_pallets_with_stored_items,
                            'description' => __("No items stored in this customer")
                        ],
                        default => []
                    }
                )
                ->column(key: 'state_icon', label: '', canBeHidden: false, type: 'icon');
            if (!$parent instanceof Pallet) {
                $table->column(key: 'pallet_reference', label: __('pallet'), canBeHidden: false, sortable: true, searchable: true);
            }
            $table->column(key: 'stored_item_reference', label: __('Customer SKO'), canBeHidden: false, sortable: true, searchable: true);
            if ($parent instanceof Pallet) {
                $table->column(key: 'stored_item_name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);
            }

            $table->column(key: 'quantity', label: __('quantity'), canBeHidden: false, sortable: true, searchable: true);
            if (!$parent instanceof Pallet) {
                $table->column(key: '', label: __('Action'), canBeHidden: false, sortable: true, searchable: true);
            }
            $table->defaultSort($parent instanceof Pallet ? 'stored_item_reference' : 'pallet_reference');
        };
    }

    //todo review this
    public function authorize(ActionRequest $request): bool
    {
        if ($request->user() instanceof WebUser) {
            return true;
        }

        $this->canEdit   = $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.edit");
        $this->canDelete = $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.edit");

        return $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.view");
    }

    public function asController(FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromFulfilment($fulfilmentCustomer->fulfilment, $request);

        return $this->handle($fulfilmentCustomer);
    }


    public function jsonResponse(LengthAwarePaginator $storedItems): AnonymousResourceCollection
    {
        return ReturnStoredItemsResource::collection($storedItems);
    }
}
