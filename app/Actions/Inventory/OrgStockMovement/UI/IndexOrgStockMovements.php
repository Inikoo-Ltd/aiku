<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 31-12-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Inventory\OrgStockMovement\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithInventoryAuthorisation;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementFlowEnum;
use App\Http\Resources\Inventory\OrgStockMovementsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockMovement;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexOrgStockMovements extends OrgAction
{
    use WithInventoryAuthorisation;

    private string $bucket;
    private Organisation|OrgStock $parent;

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'all';
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($organisation);
    }

    public function maya(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->maya = true;
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    protected function getElementGroups(Organisation $organisation): array
    {
        return [
            'state' => [
                'label'    => __('State'),
                'elements' => array_merge_recursive(
                    OrgStockMovementFlowEnum::labels(),
                    OrgStockMovementFlowEnum::count($organisation)
                ),

                'engine' => function ($query, $elements) {
                    $query->whereIn('org_stock_movements.flow', $elements);
                }
            ],
        ];
    }

    public function handle(Organisation|OrgStock $parent, $prefix = null, $bucket = null): LengthAwarePaginator
    {
        $this->parent = $parent;

        if ($bucket) {
            $this->bucket = $bucket;
        }

        if ($parent instanceof OrgStock) {
            $organisation = $parent->organisation;
        } else {
            $organisation = $parent;
        }

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('org_stocks.name', $value);
            });
        });
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }
        $queryBuilder = QueryBuilder::for(OrgStockMovement::class);

        foreach ($this->getElementGroups($organisation) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }

        if ($parent instanceof OrgStock) {
            $queryBuilder->where('org_stock_movements.org_stock_id', $parent->id);
        } else {
            $queryBuilder->where('org_stock_movements.organisation_id', $organisation->id);
        }

        return $queryBuilder
            ->defaultSort('-org_stock_movements.date')
            ->select([
                'org_stock_movements.id',
                'org_stock_movements.date',
                'org_stock_movements.flow',
                'org_stock_movements.type',
                'org_stock_movements.class',
                'org_stock_movements.quantity',
                'org_stock_movements.org_amount',
                'org_stock_movements.grp_amount',
                'org_stock_movements.operation_type',
                'org_stock_movements.operation_id',
                'organisations.name as organisation_name',
                'organisations.slug as organisation_slug',
                'warehouses.slug as warehouse_slug',
                'warehouses.name as warehouse_name',
                'locations.slug as location_slug',
                'locations.code as location_code',
                'org_stocks.slug as org_stock_slug',
                'org_stocks.name as org_stock_name',
            ])
            ->leftJoin('organisations', 'org_stock_movements.organisation_id', 'organisations.id')
            ->leftJoin('warehouses', 'warehouses.id', 'org_stock_movements.warehouse_id')
            ->leftJoin('locations', 'locations.id', 'org_stock_movements.location_id')
            ->leftJoin('org_stocks', 'org_stocks.id', 'org_stock_movements.org_stock_id')
            ->allowedSorts(['date', 'flow', 'type', 'class', 'quantity', 'org_amount', 'grp_amount', 'org_stock_name', 'organisation_name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Organisation|OrgStock $parent, $prefix = null): Closure
    {
        if ($parent instanceof OrgStock) {
            $organisation = $parent->organisation;
        } else {
            $organisation = $parent;
        }

        return function (InertiaTable $table) use ($parent, $organisation, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            foreach ($this->getElementGroups($organisation) as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements']
                );
            }

            $table
                ->withGlobalSearch()
                ->column(key: 'date', label: __('Date'), canBeHidden: false, sortable: true);

            if (!($parent instanceof OrgStock)) {
                $table->column(key: 'org_stock_name', label: __('Stock'), canBeHidden: false, sortable: true, searchable: true);
            }

            $table->column(key: 'flow', label: __('Flow'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'type', label: __('Type'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'class', label: __('Class'), canBeHidden: true, sortable: true, searchable: true)
                ->column(key: 'location_code', label: __('Location'), canBeHidden: true, sortable: false)
                ->column(key: 'quantity', label: __('Quantity'), canBeHidden: false, sortable: true)
                ->column(key: 'org_amount', label: __('Amount'), canBeHidden: true, sortable: true);
        };
    }

    public function jsonResponse(LengthAwarePaginator $orgStockMovements): AnonymousResourceCollection
    {
        return OrgStockMovementsResource::collection($orgStockMovements);
    }
}
