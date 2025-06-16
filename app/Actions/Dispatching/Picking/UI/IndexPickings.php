<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:12 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Picking\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\CRM\Customer\UI\ShowCustomer;
use App\Actions\CRM\Customer\UI\ShowCustomerClient;
use App\Actions\OrgAction;
use App\Http\Resources\Dispatching\PickingsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Picking;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexPickings extends OrgAction
{
    public function handle(DeliveryNote $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('org_stocks.code', $value)
                    ->orWhereAnyWordStartWith('org_stocks.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(Picking::class);

        $query->where('pickings.delivery_note_id', $parent->id);

        $query->leftjoin('delivery_note_items', 'pickings.delivery_note_item_id', '=', 'delivery_note_items.id');
        $query->leftjoin('org_stocks', 'delivery_note_items.org_stock_id', '=', 'org_stocks.id');

        $query->leftJoin('employees as picker_employees', 'pickings.picker_id', '=', 'picker_employees.id');
        $query->leftJoin('employees as packer_employees', 'pickings.packer_id', '=', 'packer_employees.id');

        return $query->defaultSort('pickings.id')
            ->select([
                'pickings.id',
                'delivery_note_items.quantity_required',
                'pickings.quantity_picked',
                'org_stocks.code as org_stock_code',
                'org_stocks.name as org_stock_name',
                'picker_employees.contact_name as picker_name',
                'packer_employees.contact_name as packer_name',
                'pickings.picker_id',
                'pickings.packer_id',
                'pickings.engine',
                'pickings.location_id',
                'pickings.state',
            ])
            ->allowedSorts(['id', 'org_stock_code', 'org_stock_name', 'picker_name', 'packer_name', 'engine' ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(DeliveryNote $parent, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $prefix) {
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

            $table->column(key: 'state', label: __('State'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'org_stock_code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'org_stock_name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'quantity_required', label: __('Quantity Required'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'picker_name', label: __('Picker'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'packer_name', label: __('Packer'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'engine', label: __('engine'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'actions', label: __('Actions'), canBeHidden: false);
        };
    }

    public function jsonResponse(LengthAwarePaginator $pickings): AnonymousResourceCollection
    {
        return PickingsResource::collection($pickings);
    }

    public function htmlResponse(LengthAwarePaginator $pickings, ActionRequest $request): Response
    {

        $title = __('Pickings');
        $model = __('Picking');
        $icon  = [
            'icon'  => ['fal', 'fa-shopping-cart'],
            'title' => __('pickings')
        ];
        $afterTitle = null;
        $iconRight = null;
        $actions   = null;
        return Inertia::render(
            'Ordering/Orders',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('pickings'),
                'pageHead'    => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'actions'       => $actions
                ],
                'data'        => PickingsResource::collection($pickings),



            ]
        );

    }

    public function asController(Organisation $organisation, Warehouse $warehouse, DeliveryNote $deliveryNote, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle(parent: $deliveryNote);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Orders'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.shops.show.ordering.orders.index' =>
            array_merge(
                ShowShop::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.org.shops.show.ordering.orders.index',
                        'parameters' => $routeParameters
                    ]
                )
            ),
            'grp.org.shops.show.crm.customers.show.orders.index' =>
            array_merge(
                ShowCustomer::make()->getBreadcrumbs('grp.org.shops.show.crm.customers.show', $routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.org.shops.show.crm.customers.show.orders.index',
                        'parameters' => $routeParameters
                    ]
                )
            ),
            'grp.org.shops.show.crm.customers.show.customer_clients.orders.index' =>
            array_merge(
                ShowCustomerClient::make()->getBreadcrumbs('grp.org.shops.show.crm.customers.show.customer_clients.show', $routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.org.shops.show.crm.customers.show.customer_clients.orders.index',
                        'parameters' => $routeParameters
                    ]
                )
            ),
            default => []
        };
    }
}
