<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Tue, 09 May 2023 09:25:51 Central European Summer, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Procurement\PurchaseOrder\UI;

use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\Procurement\OrgAgent\UI\ShowOrgAgent;
use App\Actions\Procurement\OrgAgent\WithOrgAgentSubNavigation;
use App\Actions\Procurement\OrgPartner\UI\ShowOrgPartner;
use App\Actions\Procurement\OrgPartner\WithOrgPartnerSubNavigation;
use App\Actions\Procurement\OrgSupplier\UI\ShowOrgSupplier;
use App\Actions\Procurement\OrgSupplier\WithOrgSupplierSubNavigation;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Http\Resources\Procurement\PurchaseOrdersResource;
use App\InertiaTable\InertiaTable;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\Procurement\PurchaseOrder;
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

class IndexPurchaseOrders extends OrgAction
{
    use WithOrgAgentSubNavigation;
    use WithOrgPartnerSubNavigation;
    use WithOrgSupplierSubNavigation;

    private Group|Organisation|OrgAgent|OrgSupplier|OrgPartner|OrgStock|OrgSupplierProduct $parent;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->parent instanceof Group) {
            return $request->user()->authTo("group-overview");
        }
        $this->canEdit = $request->user()->authTo("procurement.{$this->organisation->id}.edit");

        return $request->user()->authTo("procurement.{$this->organisation->id}.view");
    }

    protected function getElementGroups(): array
    {
        return [
            'state' => [
                'label'    => __('State'),
                'elements' => array_map(
                    fn ($label) => [$label, null],
                    PurchaseOrderStateEnum::labels(),
                ),
                'engine'   => function ($query, $elements) {
                    $query->whereIn('purchase_orders.state', $elements);
                },
            ],
        ];
    }

    public function handle(Group|Organisation|OrgAgent|OrgSupplier|OrgPartner|OrgStock|OrgSupplierProduct $parent, $prefix = null): LengthAwarePaginator
    {
        if ($parent instanceof Group) {
            $organisation = $parent->organisations()->first();
        } elseif ($parent instanceof Organisation) {
            $organisation = $parent;
        } else {
            $organisation = $parent->organisation;
        }

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('purchase_orders.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(PurchaseOrder::class);

        if (class_basename($parent) == 'OrgAgent') {
            $query->where('purchase_orders.parent_type', 'OrgAgent')->where('purchase_orders.parent_id', $parent->id);
        } elseif (class_basename($parent) == 'OrgSupplier') {
            $query->where('purchase_orders.parent_type', 'OrgSupplier')->where('purchase_orders.parent_id', $parent->id);
        } elseif (class_basename($parent) == 'OrgPartner') {
            $query->where('purchase_orders.parent_type', 'OrgPartner')->where('purchase_orders.parent_id', $parent->id);
        } elseif (class_basename($parent) == 'Group') {
            $query->where('purchase_orders.group_id', $parent->id);
            $query->leftjoin('organisations', 'purchase_orders.organisation_id', 'organisations.id');
        } elseif ($parent instanceof OrgStock) {
            $query->whereIn('purchase_orders.id', function ($query) use ($parent) {
                $query->select('purchase_order_id')
                    ->from('purchase_order_transactions')
                    ->where('org_stock_id', $parent->id);
            })->with('purchaseOrderTransactions');
        } elseif ($parent instanceof OrgSupplierProduct) {
            $query->leftJoin('purchase_order_transactions', 'purchase_orders.id', '=', 'purchase_order_transactions.purchase_order_id')
                ->where('purchase_order_transactions.org_supplier_product_id', $parent->id)
                ->with('purchaseOrderTransactions');
            $query->distinct('purchase_orders.id');
            $query->orderBy('purchase_orders.id');
        } else {
            $query->where('purchase_orders.organisation_id', $parent->id);
        }

        foreach ($this->getElementGroups() as $key => $elementGroup) {
            $query->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix,
            );
        }

        return $query
            ->defaultSort('-purchase_orders.date')
            ->select([
                'purchase_orders.*',
            ])
            ->selectRaw('purchase_orders.org_exchange * purchase_orders.cost_total as org_total_cost')
            ->selectRaw('\''.$organisation->currency->code.'\' as org_currency_code')
            ->with([
                'parent' => function ($morphTo) {
                    $morphTo->morphWith([
                        OrgSupplier::class => ['supplier'],
                        OrgAgent::class => ['agent'],
                    ]);
                },
            ])
            ->allowedSorts(['reference', 'parent_name', 'date', 'number_current_purchase_order_transactions', 'org_total_cost'])
            ->allowedFilters([$globalSearch])
            ->withBetweenDates(['date'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Group|Organisation|OrgAgent|OrgSupplier|OrgPartner|OrgStock|OrgSupplierProduct $parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table->betweenDates(['date']);

            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch()
                ->withLabelRecord([__('Purchase Order'), __('Purchase Orders')]);

            foreach ($this->getElementGroups() as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements'],
                );
            }

            $table
                ->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon')
                ->column(key: 'reference', label: __('Reference'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'parent_name', label: __('Supplier/Agents'), canBeHidden: false, sortable: true, searchable: true);

            if ($parent instanceof Group) {
                $table->column(key: 'organisation_name', label: __('Organisation'), canBeHidden: false, searchable: true);
            }

            $table
                ->column(key: 'date', label: __('Date'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'org_total_cost', label: __('Amount'), canBeHidden: false, sortable: true, searchable: true, type: 'currency')
                ->defaultSort('-date');
        };
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function maya(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->maya = true;
        $this->initialisation($organisation, $request);

        return $this->handle(parent: $organisation);
    }

    public function inGroup(ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = group();
        $this->initialisationFromGroup(group(), $request);

        return $this->handle(group());
    }

    public function inOrgStock(Organisation $organisation, OrgStock $orgStock, ActionRequest $request, ?string $prefix = null): LengthAwarePaginator
    {
        $this->parent = $orgStock;
        $this->initialisation($organisation, $request);

        return $this->handle($orgStock, $prefix);
    }

    public function inOrgAgent(Organisation $organisation, OrgAgent $orgAgent, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $orgAgent;
        $this->initialisation($organisation, $request);

        return $this->handle($orgAgent);
    }

    public function inOrgPartner(Organisation $organisation, OrgPartner $orgPartner, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $orgPartner;
        $this->initialisation($organisation, $request);

        return $this->handle($orgPartner);
    }

    public function inOrgSupplier(Organisation $organisation, OrgSupplier $orgSupplier, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $orgSupplier;
        $this->initialisation($organisation, $request);

        return $this->handle($orgSupplier);
    }

    public function htmlResponse(LengthAwarePaginator $purchaseOrders, ActionRequest $request): Response
    {
        $title         = __('Purchase Orders');
        $icon          = [
            'icon'  => ['fal', 'fa-clipboard-list'],
            'title' => __('Purchase orders'),
        ];
        $afterTitle    = null;
        $iconRight     = null;
        $subNavigation = null;
        $actions       = [];

        if ($this->parent instanceof OrgAgent) {
            $title         = $this->parent->agent->organisation->name;
            $icon          = [
                'icon'  => ['fal', 'fa-people-arrows'],
                'title' => __('Purchase orders'),
            ];
            $afterTitle    = ['label' => __('Purchase Orders')];
            $iconRight     = ['icon' => 'fal fa-clipboard-list'];
            $subNavigation = $this->getOrgAgentNavigation($this->parent);
            $actions       = [
                [
                    'label' => __('Purchase Order'),
                    'type'  => 'button',
                    'style' => 'create',
                    'route' => [
                        'method'     => 'post',
                        'name'       => 'grp.models.org-agent.purchase-order.store',
                        'parameters' => [
                            'orgAgent' => $this->parent->id,
                        ],
                    ],
                ],
            ];
        } elseif ($this->parent instanceof OrgPartner) {
            $title         = $this->parent->partner->name;
            $icon          = [
                'icon'  => ['fal', 'fa-users-class'],
                'title' => __('Purchase orders'),
            ];
            $afterTitle    = ['label' => __('Purchase Orders')];
            $iconRight     = ['icon' => 'fal fa-clipboard-list'];
            $subNavigation = $this->getOrgPartnerNavigation($this->parent);
            $actions       = [
                [
                    'label' => __('Purchase Order'),
                    'type'  => 'button',
                    'style' => 'create',
                    'route' => [
                        'method'     => 'post',
                        'name'       => 'grp.models.org-partner.purchase-order.store',
                        'parameters' => [
                            'orgPartner' => $this->parent->id,
                        ],
                    ],
                ],
            ];
        } elseif ($this->parent instanceof OrgSupplier) {
            $title         = $this->parent->supplier->name;
            $icon          = [
                'icon'  => ['fal', 'fa-person-dolly'],
                'title' => __('Purchase orders'),
            ];
            $afterTitle    = ['label' => __('Purchase Orders')];
            $iconRight     = ['icon' => 'fal fa-clipboard-list'];
            $subNavigation = $this->getOrgSupplierNavigation($this->parent);
            $actions       = [
                [
                    'label' => __('Purchase Order'),
                    'type'  => 'button',
                    'style' => 'create',
                    'route' => [
                        'method'     => 'post',
                        'name'       => 'grp.models.org-supplier.purchase-order.store',
                        'parameters' => [
                            'orgSupplier' => $this->parent->id,
                        ],
                    ],
                ],
            ];
        }

        return Inertia::render(
            'Procurement/PurchaseOrders',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'title'       => __('purchase orders'),
                'pageHead'    => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'subNavigation' => $subNavigation,
                    'actions'       => $actions,
                ],
                'data'        => PurchaseOrdersResource::collection($purchaseOrders),
            ],
        )->table($this->tableStructure($this->parent));
    }

    public function jsonResponse(LengthAwarePaginator $purchaseOrders): AnonymousResourceCollection
    {
        foreach ($purchaseOrders as $purchaseOrder) {
            if ($purchaseOrder->parent_type === 'OrgSupplier' && $purchaseOrder->relationLoaded('parent')) {
                $purchaseOrder->parent->load('supplier');
            } elseif ($purchaseOrder->parent_type === 'OrgAgent' && $purchaseOrder->relationLoaded('parent')) {
                $purchaseOrder->parent->load('agent');
            }
        }

        return PurchaseOrdersResource::collection($purchaseOrders);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return match ($routeName) {
            'grp.org.procurement.purchase_orders.index' => array_merge(
                ShowProcurementDashboard::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'label' => __('Purchase Orders'),
                            'icon'  => 'fal fa-bars',
                            'route' => [
                                'name'       => 'grp.org.procurement.purchase_orders.index',
                                'parameters' => $routeParameters,
                            ],
                        ]
                    ]
                ]
            ),
            'grp.org.procurement.org_agents.show.purchase-orders.index' => array_merge(
                ShowOrgAgent::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'label' => __('Purchase Orders'),
                            'icon'  => 'fal fa-bars',
                            'route' => [
                                'name'       => 'grp.org.procurement.org_agents.show.purchase-orders.index',
                                'parameters' => $routeParameters,
                            ],
                        ]
                    ]
                ]
            ),
            'grp.org.procurement.org_suppliers.show.purchase_orders.index' => array_merge(
                ShowOrgSupplier::make()->getBreadcrumbs($routeName, $routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'label' => __('Purchase Orders'),
                            'icon'  => 'fal fa-bars',
                            'route' => [
                                'name'       => 'grp.org.procurement.org_suppliers.show.purchase_orders.index',
                                'parameters' => $routeParameters,
                            ],
                        ]
                    ]
                ]
            ),
            'grp.org.procurement.org_partners.show.purchase-orders.index' => array_merge(
                ShowOrgPartner::make()->getBreadcrumbs($this->parent, $routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'label' => __('Purchase Orders'),
                            'icon'  => 'fal fa-bars',
                            'route' => [
                                'name'       => 'grp.org.procurement.org_partners.show.purchase-orders.index',
                                'parameters' => $routeParameters,
                            ],
                        ]
                    ]
                ]
            ),
            'grp.overview.procurement.purchase-orders.index' =>
            array_merge(
                ShowGroupOverviewHub::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'label' => __('Purchase Orders'),
                            'icon'  => 'fal fa-bars',
                            'route' => [
                                'name'       => 'grp.overview.procurement.purchase-orders.index',
                                'parameters' => $routeParameters,
                            ],
                        ]
                    ]
                ]
            ),
        };
    }
}
