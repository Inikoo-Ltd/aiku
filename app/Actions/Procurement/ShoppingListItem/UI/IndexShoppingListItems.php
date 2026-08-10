<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\ShoppingListItem\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Procurement\ShoppingListItem;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexShoppingListItems extends OrgAction
{
    use WithProcurementAuthorisation;

    public function handle(array $modelData = []): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('supplier_products.code', $value)
                    ->orWhereStartWith('supplier_products.name', $value)
                    ->orWhereStartWith('suppliers.code', $value);
            });
        });

        $queryBuilder = QueryBuilder::for(ShoppingListItem::class)
            ->leftJoin('supplier_products', 'supplier_products.id', 'shopping_list_items.supplier_product_id')
            ->leftJoin('suppliers', 'suppliers.id', 'shopping_list_items.supplier_id')
            ->leftJoin('agents', 'agents.id', 'shopping_list_items.agent_id')
            ->leftJoin('users', 'users.id', 'shopping_list_items.added_by_user_id')
            ->where('shopping_list_items.organisation_id', $this->organisation->id);

        return $queryBuilder
            ->select([
                'shopping_list_items.id',
                'shopping_list_items.quantity_units',
                'shopping_list_items.units_per_carton_snapshot',
                'shopping_list_items.priority',
                'shopping_list_items.state',
                'shopping_list_items.needed_by',
                'shopping_list_items.notes',
                'shopping_list_items.dismiss_reason',
                'shopping_list_items.created_at',
                'supplier_products.code as supplier_product_code',
                'supplier_products.name as supplier_product_name',
                'supplier_products.units_per_carton as current_units_per_carton',
                'suppliers.code as supplier_code',
                'agents.code as agent_code',
                'users.contact_name as added_by_name',
            ])
            ->defaultSort('-created_at')
            ->allowedFilters([$globalSearch])
            ->allowedSorts(['supplier_product_code', 'priority', 'needed_by', 'state', 'created_at'])
            ->withPaginator(null, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(): Closure
    {
        return function (InertiaTable $table) {
            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('Shopping list item'), __('Shopping list items')])
                ->withEmptyState([
                    'title' => __('No items on the shopping list'),
                ])
                ->column(key: 'supplier_product_code', label: __('Product'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'supplier_code', label: __('Supplier'), canBeHidden: false)
                ->column(key: 'agent_code', label: __('Agent'), canBeHidden: false)
                ->column(key: 'quantity_units', label: __('Quantity'), canBeHidden: false)
                ->column(key: 'priority', label: __('Priority'), canBeHidden: false, sortable: true)
                ->column(key: 'needed_by', label: __('Needed by'), canBeHidden: false, sortable: true)
                ->column(key: 'state', label: __('State'), canBeHidden: false, sortable: true)
                ->column(key: 'added_by_name', label: __('Added'), canBeHidden: false)
                ->defaultSort('-created_at');
        };
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);

        return $this->handle();
    }

    public function htmlResponse(LengthAwarePaginator $shoppingListItems, ActionRequest $request): Response
    {
        return Inertia::render(
            'Procurement/ShoppingListItems',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Shopping list'),
                'pageHead'    => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-shopping-basket'],
                        'title' => __('Shopping list'),
                    ],
                    'title' => __('Shopping list'),
                ],
                'dismiss_proposed_count' => ShoppingListItem::where('organisation_id', $this->organisation->id)
                    ->where('state', ShoppingListItemStateEnum::DISMISS_PROPOSED)
                    ->count(),
                'data' => $shoppingListItems,
            ]
        )->table($this->tableStructure());
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowProcurementDashboard::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.procurement.shopping_list.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Shopping list'),
                        'icon'  => 'fal fa-shopping-basket',
                    ],
                ],
            ]
        );
    }
}
