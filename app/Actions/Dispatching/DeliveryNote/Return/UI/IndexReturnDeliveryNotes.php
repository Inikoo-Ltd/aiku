<?php

/*
 * author Louis Perez
 * created on 28-04-2026-10h-09m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Dispatching\DeliveryNote\Return\UI;

use App\Actions\Dispatching\DeliveryNote\UI\IsDeliveryNotesIndex;
use App\Actions\Dispatching\DeliveryNote\UI\WithDeliveryNotesSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithDispatchingAuthorisation;
use App\Actions\UI\Dispatch\ShowDispatchHub;
use App\Http\Resources\Dispatching\DeliveryNotesResource;
use App\Http\Resources\Dispatching\ReturnDeliveryNotesResource;
use App\InertiaTable\InertiaTable;
use App\Models\CRM\WebUser;
use App\Models\Dispatching\ReturnDeliveryNote;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexReturnDeliveryNotes extends OrgAction
{
    use WithDispatchingAuthorisation;

    private string $shopType;

    public function handle(Warehouse $parent, $prefix = null, $bucket = 'all', $shopType = 'all', $isReturn = false): LengthAwarePaginator
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
        
        $query->where('shops.is_aiku', true);

        $allowedFilters = [$globalSearch];
        
        $selectColumns = [
            'return_delivery_notes.id',
            'return_delivery_notes.reference',
            'return_delivery_notes.queued_at as date',
            'return_delivery_notes.return_state as state',
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
        // if ($this->parent instanceof Warehouse) {
        //     $subNavigation = $this->getDeliveryNotesSubNavigation($this->shopType);
        // }

        $title      = __('Returned Delivery notes');
        $model      = '';
        $icon       = [
            'icon'  => ['fal', 'fa-truck'],
            'title' => $title
        ];
        $afterTitle = null;
        $iconRight  = null;
        $actions    = null;

        if ($this->parent instanceof Warehouse) {
            $icon      = ['fal', 'fa-arrow-from-left'];
            $iconRight = [
                'icon' => 'fal fa-truck',
            ];
            $model     = __('Goods Out');
        }

        
        return Inertia::render(
            'Org/Dispatching/DeliveryNotes',
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
                'shopType'      => $this->shopType,
            ]
        )
        ->table($this->tableStructure(parent: $this->parent, bucket: $this->bucket, shopType: $this->shopType, isReturn: true));
    }

    public function tableStructure(Warehouse $parent, $prefix = null, $bucket = 'all', $shopType = 'all', $isReturn = false): Closure
    {
        $employee = null;
        if (!request()->user() instanceof WebUser) {
            $employee = request()->user()->employees()->first() ?? null;
        }

        $pickerEmployee = null;
        if ($employee) {
            $pickerEmployee = $employee->jobPositions()->where('name', 'Picker')->first();
        }

        return function (InertiaTable $table) use ($isReturn, $parent, $prefix, $bucket, $pickerEmployee, $shopType) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table->betweenDates(['date']);

            $noResults = __("No returned delivery notes found");
            $count = $parent->organisation->orderingStats->number_delivery_notes;

            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title' => $noResults,
                        'count' => $count
                    ]
                );

            $table->column(key: 'state', label: '', type: 'icon');
            $table->column(key: 'reference', label: __('Reference'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'date', label: __('Date'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
            $table->column(key: 'customer_name', label: __('Customer'), canBeHidden: false, sortable:true, searchable: true);
        };
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $warehouse;
        $this->bucket = 'all';
        $this->shopType = 'all';
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle(parent: $warehouse, bucket: $this->bucket, isReturn: true);
    }

    /** @noinspection PhpUnusedParameterInspection */   
    // public function inWarehouseShopTypes(Organisation $organisation, Warehouse $warehouse, string $shopType, ActionRequest $request): LengthAwarePaginator
    // {
    //     $this->parent = $warehouse;
    //     $this->bucket = 'inWarehouse';
    //     $this->shopType = $shopType;
    //     $this->initialisationFromWarehouse($warehouse, $request);

    //     return $this->handle(parent: $warehouse, bucket: $this->bucket, shopType: $shopType, isReturn: true);
    // }


    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = [], ?string $suffix = null) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Returned Delivery notes'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.warehouses.show.dispatching.return-delivery-notes' =>
            array_merge(
                ShowDispatchHub::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.org.warehouses.show.dispatching.return-delivery-notes',
                        'parameters' => $routeParameters
                    ]
                )
            ),
            default => []
        };
    }
}
