<?php

/*
 * author Louis Perez
 * created on 28-04-2026-10h-09m
 * GitHub: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\GoodsIn\ReturnDeliveryNote\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\CRM\Customer\UI\ShowCustomer;
use App\Actions\CRM\Customer\UI\WithCustomerSubNavigation;
use App\Actions\GoodsIn\ReturnDeliveryNote\Traits\WithReturnDeliveryNotesSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Enums\GoodsIn\ReturnDeliveryNote\ReturnDeliveryNoteStateEnum;
use App\Http\Resources\Procurement\ReturnDeliveryNotesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\GoodsIn\ReturnDeliveryNote;
use App\Models\Inventory\Warehouse;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexReturnDeliveryNotes extends OrgAction
{
    use WithReturnDeliveryNotesSubNavigation;
    use WithCustomerSubNavigation;

    private Warehouse|Shop|Customer $parent;
    private string $bucket = 'all';

    protected function getElementGroups(Group|Organisation|Shop|Warehouse|Order $parent): array
    {
        return [
            'state' => [
                'label'    => __('State'),
                'elements' => array_merge_recursive(
                    ReturnDeliveryNoteStateEnum::labels(),
                    ReturnDeliveryNoteStateEnum::count($parent)
                ),

                'engine' => function ($query, $elements) {
                    $query->whereIn('return_delivery_notes.state', $elements);
                }
            ],


        ];
    }

    public function handle(Group|Organisation|Shop|Warehouse|Order|Customer $parent, $prefix = null, $bucket = 'all'): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereWith('return_delivery_notes.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(ReturnDeliveryNote::class);
        $query->leftJoin('customers', 'return_delivery_notes.customer_id', '=', 'customers.id');
        $query->leftJoin('organisations', 'return_delivery_notes.organisation_id', '=', 'organisations.id');
        $query->leftJoin('shops', 'return_delivery_notes.shop_id', '=', 'shops.id');
        $query->leftJoin('delivery_notes', 'return_delivery_notes.delivery_note_id', '=', 'delivery_notes.id');

        if ($parent instanceof Warehouse) {
            $query->where('return_delivery_notes.warehouse_id', $parent->id);
        } elseif ($parent instanceof Order) {
            $query->where('return_delivery_notes.order_id', $parent->id);
        } elseif ($parent instanceof Organisation) {
            $query->where('return_delivery_notes.organisation_id', $parent->id);
        } elseif ($parent instanceof Shop) {
            $query->where('return_delivery_notes.shop_id', $parent->id);
        } elseif ($parent instanceof Customer) {
            $query->where('return_delivery_notes.customer_id', $parent->id);
        }

        if (!($parent instanceof Order || $parent instanceof Customer)) {
            foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
                $query->whereElementGroup(
                    key: $key,
                    allowedElements: array_keys($elementGroup['elements']),
                    engine: $elementGroup['engine'],
                    prefix: $prefix
                );
            }
        }

        $query->where('shops.is_aiku', true);

        $allowedFilters = [$globalSearch];

        $selectColumns = [
            'return_delivery_notes.id',
            'return_delivery_notes.reference',
            'return_delivery_notes.created_at as date',
            'return_delivery_notes.state',
            'return_delivery_notes.created_at',
            'return_delivery_notes.updated_at',
            'return_delivery_notes.slug',
            'customers.slug as customer_slug',
            'customers.name as customer_name',
            'shops.name as shop_name',
            'shops.slug as shop_slug',
            'organisations.name as organisation_name',
            'organisations.slug as organisation_slug',
            'return_delivery_notes.customer_notes',
            'return_delivery_notes.internal_notes',
            'return_delivery_notes.public_notes',
            'return_delivery_notes.shipping_notes',
        ];

        if ($bucket != 'all') {
            $query->where('return_delivery_notes.state', $bucket);
        }

        return $query
            ->select($selectColumns)
            ->allowedFilters($allowedFilters)
            ->withBetweenDates(['date'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function htmlResponse(LengthAwarePaginator $returnDeliveryNote, ActionRequest $request): Response
    {
        $subNavigation = null;
        $title      = __('Returns');
        $model      = '';
        $icon       = [
            'icon'  => ['fal', 'fa-exchange'],
            'title' => $title
        ];
        $afterTitle = null;
        $iconRight  = null;
        $actions    = [];

        if ($this->parent instanceof Warehouse) {
            $subNavigation = $this->getReturnDeliveryNotesSubNavigation($this->parent);
        } elseif ($this->parent instanceof Customer) {
            $subNavigation = $this->getCustomerSubNavigation($this->parent, $request);
            
            $icon       = [
                'icon'  => ['fal', 'fa-user'],
                'title' => __('Customer')
            ];
            $title      = $this->parent->name;
            $iconRight  = [
                'icon'  => ['fal', 'fa-exchange'],
                'title' => $title
            ];
            $afterTitle = [
                'label' => __('Returns')
            ];
        }

        if ($this->parent instanceof Warehouse) {
            $icon      = ['fal', 'fa-arrow-to-bottom'];
            $iconRight = [
                'icon' => 'fal fa-exchange',
            ];
            $model     = __('Goods In');

            $actions[] = [
                'type'    => 'button',
                'style'   => 'create',
                'key'     => 'create-return',
                'label'   => __('Create Return'),
                'icon'    => 'fal fa-plus',
            ];
        }

        return Inertia::render(
            'Org/Procurement/Returns',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters(),
                ),
                'title'       => $title,
                'pageHead'    => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'subNavigation' => $subNavigation,
                    'actions'       => $actions
                ],
                'data'          => ReturnDeliveryNotesResource::collection($returnDeliveryNote),
            ]
        )
        ->table($this->tableStructure(parent: $this->parent, bucket: $this->bucket));
    }

    public function tableStructure(Group|Organisation|Shop|Warehouse|Order|Customer $parent, $prefix = null, $bucket = 'all'): Closure
    {

        return function (InertiaTable $table) use ($parent, $prefix, $bucket) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table->betweenDates(['date']);

            $noResults = __("No returned delivery notes found");
            $count = 0;



            if ($bucket == 'all' && !($parent instanceof Order || $parent instanceof Customer)) {
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
                ->withEmptyState(
                    [
                        'title' => $noResults,
                        'count' => $count
                    ]
                );

            $table->column(key: 'state', label: '', type: 'icon');
            $table->column(key: 'reference_return', label: __('Reference'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'date', label: __('Date'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
            $table->column(key: 'customer_name', label: __('Customer'), canBeHidden: false, sortable:true, searchable: true);
        };
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $warehouse;
        $this->bucket = 'all';
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle(parent: $warehouse, bucket: $this->bucket);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function received(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $warehouse;
        $this->bucket = 'received';
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle(parent: $warehouse, bucket: $this->bucket);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function returning(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $warehouse;
        $this->bucket = 'returning';
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle(parent: $warehouse, bucket: $this->bucket);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function returned(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $warehouse;
        $this->bucket = 'returned';
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle(parent: $warehouse, bucket: $this->bucket);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function processed(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $warehouse;
        $this->bucket = 'done';
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle(parent: $warehouse, bucket: $this->bucket);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inShop(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);

        return $this->handle(parent: $shop, bucket: $this->bucket);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inCustomer(Organisation $organisation, Shop $shop, Customer $customer, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $customer;
        $this->initialisationFromShop($shop, $request);

        return $this->handle(parent: $customer, bucket: $this->bucket);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = [], ?string $suffix = null) {
            if (!$suffix && $this->bucket !== 'all') {
                $suffix = "($this->bucket)";
            }

            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Returns'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' =>  $suffix
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.shops.show.ordering.return_delivery_notes.index'  => array_merge(
                ShowShop::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ]
                )
            ),
            'grp.org.warehouses.show.incoming.return_delivery_notes.state.received',
            'grp.org.warehouses.show.incoming.return_delivery_notes.state.returning',
            'grp.org.warehouses.show.incoming.return_delivery_notes.state.returned',
            'grp.org.warehouses.show.incoming.return_delivery_notes.state.processed',
            'grp.org.warehouses.show.incoming.return_delivery_notes.index' => array_merge(
                ShowProcurementDashboard::make()->getBreadcrumbs(
                    Arr::only($routeParameters, ['organisation', 'warehouse'])
                ),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ]
                )
            ),
            'grp.org.shops.show.crm.customers.show.return_delivery_notes.index' => array_merge(
                ShowCustomer::make()->getBreadcrumbs(
                    'grp.org.shops.show.crm.customers.show',
                    $routeParameters
                ),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ]
                )
            ),
            default => []
        };
    }
}
