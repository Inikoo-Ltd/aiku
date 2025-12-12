<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 May 2023 21:14:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\StoredItem\UI;

use App\Actions\Fulfilment\FulfilmentCustomer\ShowFulfilmentCustomer;
use App\Actions\Fulfilment\StoredItemAudit\UI\IndexStoredItemAudits;
use App\Actions\Fulfilment\WithFulfilmentCustomerSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\Traits\Authorisations\WithFulfilmentShopAuthorisation;
use App\Enums\Fulfilment\StoredItemAudit\StoredItemAuditStateEnum;
use App\Enums\UI\Fulfilment\StoredItemsInWarehouseTabsEnum;
use App\Http\Resources\Fulfilment\ReturnStoredItemsResource;
use App\Http\Resources\Fulfilment\StoredItemAuditsResource;
use App\Http\Resources\Fulfilment\StoredItemResource;
use App\Http\Resources\Fulfilment\StoredItemsInWarehouseResource;
use App\InertiaTable\InertiaTable;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\StoredItem;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexStoredItems extends OrgAction
{
    use WithFulfilmentCustomerSubNavigation;
    use WithFulfilmentShopAuthorisation;

    private Group|FulfilmentCustomer $parent;

    public function handle(Group|FulfilmentCustomer|Pallet $parent, $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('slug', $value)
                ->orWhereWith('reference', $value);
            });
        });

        return QueryBuilder::for(StoredItem::class)
            ->select(
                'stored_items.id',
                'stored_items.slug',
                'stored_items.reference',
                'stored_items.state',
                'stored_items.name',
                'stored_items.total_quantity',
                'stored_items.number_pallets',
                'stored_items.number_audits',
            )
            ->defaultSort('reference')
            ->when($parent, function ($query) use ($parent) {
                if ($parent instanceof FulfilmentCustomer) {
                    $query->where('fulfilment_customer_id', $parent->id);
                } elseif ($parent instanceof Pallet) {
                    $query->join('pallet_stored_items', 'pallet_stored_items.stored_item_id', '=', 'stored_items.id')
                        ->where('pallet_stored_items.pallet_id', $parent->id);
                } elseif ($parent instanceof Group) {
                    $query->where('stored_items.group_id', $parent->id);
                }
            })
            ->allowedSorts(['reference', 'total_quantity', 'name', 'number_pallets', 'number_audits', 'pallet_reference'])
            ->allowedFilters([$globalSearch, 'slug', 'state'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
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
                            'title'       => __("No stored items found"),
                            'count'       => $parent->number_stored_items,
                            'description' => __("No items stored in this customer")
                        ],
                        'Group' => [
                            'title'       => __("No stored items found"),
                            'count'       => $parent->fulfilmentStats->number_stored_items,
                            'description' => __("No items stored in this group")
                        ],
                        default => []
                    }
                )
                ->column(key: 'state', label: '', canBeHidden: false, type: 'icon')
                ->column(key: 'reference', label: __('reference'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true)
                ->column(key: 'number_pallets', label: __("Pallets"), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'number_audits', label: __("Audits"), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'total_quantity', label: __("Quantity"), canBeHidden: false, sortable: true, align: 'right');
            if (class_basename($parent) == 'Group') {
                $table->column(key: 'organisation_name', label: __('Organisation'), canBeHidden: false, sortable: true, searchable: true);
                $table->column(key: 'customer_name', label: __('Customer Name'), canBeHidden: false, sortable: true, searchable: true);
            }
            //  $table->column(key: 'actions', label: __('Action'), canBeHidden: false, sortable: true, searchable: true)
            $table->defaultSort('reference');
        };
    }


    public function jsonResponse(LengthAwarePaginator $storedItems): AnonymousResourceCollection
    {
        return StoredItemResource::collection($storedItems);
    }

    public function htmlResponse(LengthAwarePaginator $storedItems, ActionRequest $request): Response
    {
        $subNavigation = [];
        $actions       = [];
        $icon          = ['fal', 'fa-narwhal'];
        $title         = __("Customer's SKUs");
        $afterTitle    = null;
        $iconRight     = null;

        if ($this->parent instanceof FulfilmentCustomer) {
            $subNavigation = $this->getFulfilmentCustomerSubNavigation($this->parent, $request);
            $icon          = ['fal', 'fa-user'];
            $title         = $this->parent->customer->name;
            $iconRight     = [
                'icon' => 'fal fa-narwhal',
            ];
            $afterTitle    = [

                'label' => __("Customer's SKUs")
            ];


            if ($this->parent->items_storage) {
                if ($this->parent->number_pallets_state_storing > 0) {
                    $openStoredItemAudit = $this->parent->storedItemAudits()->whereNot('scope_type', 'Pallet')->where('state', StoredItemAuditStateEnum::IN_PROCESS)->first();

                    if ($openStoredItemAudit) {
                        $actions[] = [
                            'type'    => 'button',
                            'style'   => 'secondary',
                            'tooltip' => __("Continue customer's SKUs audit"),
                            'label'   => __("Continue customer's SKUs audit"),
                            'route'   => [
                                'name'       => 'grp.org.fulfilments.show.crm.customers.show.stored-item-audits.show',
                                'parameters' => array_merge($request->route()->originalParameters(), ['storedItemAudit' => $openStoredItemAudit->slug])
                            ]
                        ];
                    } else {
                        $actions[] = [
                            'type'    => 'button',
                            'tooltip' => __("Start customer's SKUs audit"),
                            'label'   => __("Start customer's SKUs audit"),
                            'route'   => [
                                'name'       => 'grp.org.fulfilments.show.crm.customers.show.stored-item-audits.create',
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ];

                    }
                }

                $actions[] = [
                    'type'    => 'button',
                    'style' => 'create',
                    'tooltip' => __("Create SKU"),
                    'label'   => __("Create SKU"),
                    'key'    => 'create_sku',
                    'route' => [
                        'name' => 'grp.org.fulfilments.show.crm.customers.show.stored-items.create',
                        'parameters' => $request->route()->originalParameters()
                    ]
                ];
            }
        }

        return Inertia::render(
            'Org/Fulfilment/StoredItems',
            [
                'breadcrumbs'                                       => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters(),
                ),
                'title'                                             => __("Customer's SKUs"),
                'pageHead'                                          => [
                    'title'         => $title,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'icon'          => $icon,
                    'subNavigation' => $subNavigation,
                    'actions'       => $actions
                ],
                'tabs'                                              => [
                    'current'    => $this->tab,
                    'navigation' => StoredItemsInWarehouseTabsEnum::navigation(),
                ],
                StoredItemsInWarehouseTabsEnum::STORED_ITEMS->value => $this->tab == StoredItemsInWarehouseTabsEnum::STORED_ITEMS->value ?
                    fn () => StoredItemsInWarehouseResource::collection($storedItems)
                    : Inertia::lazy(fn () => StoredItemsInWarehouseResource::collection($storedItems)),

                StoredItemsInWarehouseTabsEnum::PALLET_STORED_ITEMS->value => $this->tab == StoredItemsInWarehouseTabsEnum::PALLET_STORED_ITEMS->value ?
                    fn () => ReturnStoredItemsResource::collection(IndexPalletStoredItems::run(parent: $this->parent, prefix: StoredItemsInWarehouseTabsEnum::PALLET_STORED_ITEMS->value))
                    : Inertia::lazy(fn () => ReturnStoredItemsResource::collection(IndexPalletStoredItems::run(parent: $this->parent, prefix: StoredItemsInWarehouseTabsEnum::PALLET_STORED_ITEMS->value))),

                StoredItemsInWarehouseTabsEnum::STORED_ITEM_AUDITS->value => $this->tab == StoredItemsInWarehouseTabsEnum::STORED_ITEM_AUDITS->value ?
                    fn () => StoredItemAuditsResource::collection(IndexStoredItemAudits::run(parent: $this->parent, prefix: StoredItemsInWarehouseTabsEnum::STORED_ITEM_AUDITS->value))
                    : Inertia::lazy(fn () => StoredItemAuditsResource::collection(IndexStoredItemAudits::run(parent: $this->parent, prefix: StoredItemsInWarehouseTabsEnum::STORED_ITEM_AUDITS->value))),

            ]
        )->table($this->tableStructure(parent: $this->parent, prefix: StoredItemsInWarehouseTabsEnum::STORED_ITEMS->value))
            ->table(IndexStoredItemAudits::make()->tableStructure(parent: $this->parent, prefix: StoredItemsInWarehouseTabsEnum::STORED_ITEM_AUDITS->value))
            ->table(
                IndexPalletStoredItems::make()->tableStructure(
                    $this->parent,
                    prefix: StoredItemsInWarehouseTabsEnum::PALLET_STORED_ITEMS->value
                )
            );
    }

    public function inGroup(ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = group();
        $this->initialisationFromGroup($this->parent, $request)->withTab(StoredItemsInWarehouseTabsEnum::values());

        return $this->handle($this->parent, StoredItemsInWarehouseTabsEnum::STORED_ITEMS->value);
    }


    public function asController(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $fulfilmentCustomer;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($fulfilmentCustomer, StoredItemsInWarehouseTabsEnum::STORED_ITEMS->value);
    }


    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilmentCustomer(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $fulfilmentCustomer;
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(StoredItemsInWarehouseTabsEnum::values());

        return $this->handle($fulfilmentCustomer, StoredItemsInWarehouseTabsEnum::STORED_ITEMS->value);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __("Customer's SKUs"),
                        'icon'  => 'fal fa-bars',
                    ],

                ]
            ];
        };

        return match ($routeName) {
            'grp.overview.fulfilment.stored-items.index' =>
            array_merge(
                ShowGroupOverviewHub::make()->getBreadcrumbs(),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ]
                )
            ),
            default => array_merge(
                ShowFulfilmentCustomer::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.fulfilments.show.crm.customers.show.stored-items.index',
                                'parameters' => $routeParameters
                            ],
                            'label' => __("Customer's SKUs"),
                            'icon'  => 'fal fa-bars',
                        ],

                    ]
                ]
            ),
        };
    }
}
