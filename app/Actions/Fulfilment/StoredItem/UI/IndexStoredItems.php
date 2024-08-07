<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 May 2023 21:14:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\StoredItem\UI;

use App\Actions\Fulfilment\FulfilmentCustomer\ShowFulfilmentCustomer;
use App\Actions\Fulfilment\WithFulfilmentCustomerSubNavigation;
use App\Actions\OrgAction;
use App\Enums\UI\Fulfilment\FulfilmentCustomerStoredItemsTabsEnum;
use App\Http\Resources\Fulfilment\ReturnStoredItemsResource;
use App\Http\Resources\Fulfilment\StoredItemResource;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\StoredItem;
use App\Models\SysAdmin\Organisation;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\InertiaTable\InertiaTable;
use Spatie\QueryBuilder\AllowedFilter;
use App\Services\QueryBuilder;

class IndexStoredItems extends OrgAction
{
    use WithFulfilmentCustomerSubNavigation;

    public function handle(Organisation|FulfilmentCustomer $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('slug', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(StoredItem::class)
            ->defaultSort('slug')
            ->when($parent, function ($query) use ($parent) {
                if($parent instanceof FulfilmentCustomer) {
                    $query->where('fulfilment_customer_id', $parent->id);
                }
            })
            ->allowedSorts(['slug', 'state'])
            ->allowedFilters([$globalSearch, 'slug', 'state'])
            ->withPaginator($prefix)
            ->withQueryString();
    }

    public function tableStructure($parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix) {

            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                    match (class_basename($parent)) {
                        'FulfilmentCustomer' => [
                            'title'         => __("No stored items found"),
                            'count'         => $parent->count(),
                            'description'   => __("No items stored in this customer")
                        ],
                        default => []
                    }
                )
                ->column(key: 'state', label: __('Delivery State'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'reference', label: __('reference'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'customer_name', label: __('Customer Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'actions', label: __('Action'), canBeHidden: false, sortable: true, searchable: true)
                // ->column(key: 'notes', label: __('Notes'), canBeHidden: false, sortable: true, searchable: true)
                ->defaultSort('slug');
        };
    }

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->hasPermissionTo("fulfilment-shop.{$this->fulfilment->id}.edit");

        return
            (
                $request->user()->tokenCan('root') or
                $request->user()->hasPermissionTo("human-resources.{$this->organisation->id}.view")
            );
    }


    public function jsonResponse(LengthAwarePaginator $storedItems): AnonymousResourceCollection
    {
        return StoredItemResource::collection($storedItems);
    }


    public function htmlResponse(LengthAwarePaginator $storedItems, ActionRequest $request): Response
    {
        // dd($this->parent);
        $subNavigation=[];

        $icon      =['fal', 'fa-narwhal'];
        $title     =__('stored items');
        $afterTitle=null;
        $iconRight =null;

        if($this->parent instanceof  FulfilmentCustomer) {
            $subNavigation=$this->getFulfilmentCustomerSubNavigation($this->parent, $request);
            $icon         =['fal', 'fa-user'];
            $title        =$this->parent->customer->name;
            $iconRight    =[
                'icon' => 'fal fa-narwhal',
            ];
            $afterTitle= [

                'label'     => __('stored items')
            ];
        }
        return Inertia::render(
            'Org/Fulfilment/StoredItems',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('stored items'),
                'pageHead'    => [
                    'title'         => $title,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'icon'          => $icon,
                    'subNavigation' => $subNavigation,
                    'actions'       => [
                        'buttons' => [
                            'route' => [
                                'name'       => 'grp.org.hr.employees.create',
                                'parameters' => array_values(request()->route()->originalParameters())
                            ],
                            'label' => __('stored items')
                        ]
                    ],
                ],
                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => FulfilmentCustomerStoredItemsTabsEnum::navigation(),
                ],
                FulfilmentCustomerStoredItemsTabsEnum::STORED_ITEMS->value => $this->tab == FulfilmentCustomerStoredItemsTabsEnum::STORED_ITEMS->value ?
                fn () => StoredItemResource::collection($storedItems)
                : Inertia::lazy(fn () => StoredItemResource::collection($storedItems)),

                 FulfilmentCustomerStoredItemsTabsEnum::PALLET_STORED_ITEMS->value => $this->tab == FulfilmentCustomerStoredItemsTabsEnum::PALLET_STORED_ITEMS->value ?
                    fn () => ReturnStoredItemsResource::collection(IndexPalletStoredItems::run($this->parent))
                    : Inertia::lazy(fn () => ReturnStoredItemsResource::collection(IndexPalletStoredItems::run($this->parent))),

            ]
        )->table($this->tableStructure($storedItems, prefix: FulfilmentCustomerStoredItemsTabsEnum::STORED_ITEMS->value))
        ->table(
            IndexPalletStoredItems::make()->tableStructure(
                $this->parent,
                prefix: FulfilmentCustomerStoredItemsTabsEnum::PALLET_STORED_ITEMS->value
            )
        );
    }


    public function asController(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromFulfilment($fulfilment, $request);
        return $this->handle($fulfilmentCustomer, 'stored_items');
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilmentCustomer(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $fulfilmentCustomer;
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(FulfilmentCustomerStoredItemsTabsEnum::values());

        return $this->handle($fulfilmentCustomer, 'stored_items');
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(
            ShowFulfilmentCustomer::make()->getBreadcrumbs(request()->route()->originalParameters()),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.fulfilments.show.crm.customers.show.stored-items.index',
                            'parameters' => request()->route()->originalParameters()
                        ],
                        'label' => __('stored items'),
                        'icon'  => 'fal fa-bars',
                    ],

                ]
            ]
        );
    }
}
