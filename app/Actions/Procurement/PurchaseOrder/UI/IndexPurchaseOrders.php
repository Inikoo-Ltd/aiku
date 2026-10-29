<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Tue, 09 May 2023 09:25:51 Central European Summer, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Procurement\PurchaseOrder\UI;

use App\Actions\Procurement\WithParentSiblingsNavigation;
use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\Procurement\OrgAgent\UI\ShowOrgAgent;
use App\Actions\Procurement\OrgAgent\WithOrgAgentSubNavigation;
use App\Actions\Procurement\OrgPartner\UI\ShowOrgPartner;
use App\Actions\Procurement\OrgPartner\WithOrgPartnerSubNavigation;
use App\Actions\Procurement\OrgSupplier\UI\ShowOrgSupplier;
use App\Actions\Procurement\OrgSupplier\WithOrgSupplierSubNavigation;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\SupplyChain\Supplier\PurchaseOrderImport\ImportSupplierPurchaseOrders;
use App\Actions\SupplyChain\Supplier\PurchaseOrderImport\PlanSupplierPurchaseOrderImport;
use App\Actions\SupplyChain\Supplier\WithSupplierSubNavigation;
use App\Actions\SupplyChain\UI\ShowSupplyChainDashboard;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderDeliveryStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStageEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionPriceQueryStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionQuantityQueryStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Http\Resources\Procurement\PurchaseOrdersResource;
use App\InertiaTable\InertiaTable;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\OrgAgent;
use App\Models\SupplyChain\Agent;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilderForSubquery;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexPurchaseOrders extends OrgAction
{
    use WithParentSiblingsNavigation;
    use WithOrgAgentSubNavigation;
    use WithOrgPartnerSubNavigation;
    use WithOrgSupplierSubNavigation;
    use WithSupplierSubNavigation;
    use WithAgentOrganisation;

    private Group|Organisation|OrgAgent|OrgSupplier|OrgPartner|OrgStock|OrgSupplierProduct|Supplier|StockDelivery $parent;

    private bool $onlyIncoming = false;

    private ?Agent $viewingAgent = null;

    public function viewedFrom(Organisation $organisation): static
    {
        $this->viewingAgent = $this->getOrganisationAgent($organisation);

        return $this;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->parent instanceof Group) {
            return $request->user()->authTo("group-overview");
        }

        if ($this->parent instanceof Supplier) {
            $this->canEdit = $request->user()->authTo('supply-chain.edit');

            return $request->user()->authTo('supply-chain.view');
        }
        $this->canEdit = $request->user()->authTo("procurement.{$this->organisation->id}.edit");

        return $request->user()->authTo("procurement.{$this->organisation->id}.view");
    }

    protected function getElementGroups(Group|Organisation|OrgAgent|OrgSupplier|OrgPartner|OrgStock|OrgSupplierProduct|Supplier|StockDelivery $parent): array
    {
        $supplierStats = $this->viewingAgent ? null : match (true) {
            $parent instanceof Supplier => $parent->stats,
            $parent instanceof OrgSupplier => $parent->stats,
            default => null,
        };

        $organisationAgent = $this->getParentOrganisationAgent($parent);

        $elementGroups = [
            'state'          => [
                'label'    => __('State'),
                'elements' => collect(PurchaseOrderStateEnum::labels())
                    ->except($organisationAgent || $this->viewingAgent ? [PurchaseOrderStateEnum::IN_PROCESS->value] : [])
                    ->map(
                        fn ($label, $state) => [
                            $label,
                            $supplierStats?->{'number_purchase_orders_state_'.$state},
                        ],
                    )->all(),
                'engine'   => function ($query, $elements) {
                    $query->whereIn('purchase_orders.state', $elements);
                },
            ],
            'delivery_state' => [
                'label'    => __('Delivery State'),
                'elements' => array_map(
                    fn ($label) => [$label, null],
                    PurchaseOrderDeliveryStateEnum::labels(),
                ),
                'engine'   => function ($query, $elements) {
                    $query->whereIn('purchase_orders.delivery_state', $elements);
                },
            ],
            'attention'      => [
                'label'    => __('Attention'),
                'elements' => [
                    'unconfirmed_over_7_days'  => [__('Unconfirmed over 7 days'), null],
                    'unconfirmed_over_30_days' => [__('Unconfirmed over 30 days'), null],
                    'past_expected_date'       => [__('Past expected date'), null],
                    'in_production'            => [__('In production'), null],
                    'past_eta'                 => [__('Past ETA, no container'), null],
                    'unconfirmed_over_14_days' => [__('Not confirmed after 14 days'), null],
                    'waiting_price_answer'     => [__('New price or quantity to answer'), null],
                ],
                'engine'   => function ($query, $elements) {
                    $query->where(function ($query) use ($elements) {
                        if (in_array('unconfirmed_over_7_days', $elements)) {
                            $query->orWhere(fn ($query) => $query->where('purchase_orders.state', PurchaseOrderStateEnum::SUBMITTED)->where('purchase_orders.submitted_at', '<', now()->subDays(7)));
                        }
                        if (in_array('unconfirmed_over_30_days', $elements)) {
                            $query->orWhere(fn ($query) => $query->where('purchase_orders.state', PurchaseOrderStateEnum::SUBMITTED)->where('purchase_orders.submitted_at', '<', now()->subDays(30)));
                        }
                        if (in_array('past_expected_date', $elements)) {
                            $query->orWhere(fn ($query) => $query->whereIn('purchase_orders.state', [PurchaseOrderStateEnum::SUBMITTED, PurchaseOrderStateEnum::CONFIRMED])->where('purchase_orders.estimated_received_at', '<', now()));
                        }
                        if (in_array('in_production', $elements)) {
                            $query->orWhereRaw(PurchaseOrderStageEnum::inProductionSql());
                        }
                        if (in_array('past_eta', $elements)) {
                            $query->orWhereRaw(PurchaseOrderStageEnum::pastEtaSql());
                        }
                        if (in_array('unconfirmed_over_14_days', $elements)) {
                            $query->orWhereRaw(PurchaseOrderStageEnum::unconfirmedSql(14));
                        }
                        if (in_array('waiting_price_answer', $elements)) {
                            $query->orWhereExists($this->pendingLineAnswersQuery()->select(DB::raw(1)));
                        }
                    });
                },
            ],
            ...($organisationAgent ? [
                'progress' => [
                    'label'    => __('Progress'),
                    'elements' => collect(PurchaseOrderStageEnum::progressLabels())->except('draft')->map(fn ($label) => [$label, null])->all(),
                    'engine'   => function ($query, $elements) {
                        $query->whereIn('purchase_orders.state', [PurchaseOrderStateEnum::SUBMITTED, PurchaseOrderStateEnum::CONFIRMED])
                            ->whereIn(DB::raw(PurchaseOrderStageEnum::progressSql()), $elements);
                    },
                ],
            ] : []),
        ];

        if ($this->onlyIncoming) {
            $elementGroups = Arr::only($elementGroups, ['attention']);
            Arr::forget($elementGroups, ['attention.elements.in_production', 'attention.elements.past_eta']);
        }

        return $elementGroups;
    }

    public function handle(Group|Organisation|OrgAgent|OrgSupplier|OrgPartner|OrgStock|OrgSupplierProduct|Supplier|StockDelivery $parent, $prefix = null): LengthAwarePaginator
    {
        if ($parent instanceof Group) {
            $organisation = $parent->organisations()->first();
        } elseif ($parent instanceof Organisation) {
            $organisation = $parent;
        } elseif ($parent instanceof Supplier) {
            $organisation = null;
        } else {
            $organisation = $parent->organisation;
        }

        $globalSearch = AllowedFilter::callback('global', fn ($query, $value) => $this->whereSearch($query, $value));

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $organisationAgent = $this->getParentOrganisationAgent($parent);

        $query = QueryBuilder::for(PurchaseOrder::class);

        if (class_basename($parent) == 'OrgAgent') {
            $query->where('purchase_orders.agent_id', $parent->agent_id)->where('purchase_orders.organisation_id', $parent->organisation_id);
        } elseif (class_basename($parent) == 'OrgSupplier') {
            $query->where('purchase_orders.parent_type', 'OrgSupplier')->where('purchase_orders.parent_id', $parent->id);
        } elseif (class_basename($parent) == 'OrgPartner') {
            $query->where('purchase_orders.parent_type', 'OrgPartner')->where('purchase_orders.parent_id', $parent->id);
        } elseif (class_basename($parent) == 'Group') {
            $query->where('purchase_orders.group_id', $parent->id);
        } elseif ($parent instanceof Supplier) {
            $query->where('purchase_orders.supplier_id', $parent->id);
        } elseif ($parent instanceof StockDelivery) {
            $query->whereIn('purchase_orders.id', $parent->purchaseOrders()->select('purchase_orders.id'));
        } elseif ($organisationAgent) {
            $query->where('purchase_orders.agent_id', $organisationAgent->id)
                ->where('purchase_orders.state', '!=', PurchaseOrderStateEnum::IN_PROCESS);
        } elseif ($parent instanceof OrgStock) {
            $query->whereIn('purchase_orders.id', function ($query) use ($parent) {
                $query->select('purchase_order_id')
                    ->from('purchase_order_transactions')
                    ->where('org_stock_id', $parent->id);
            })->with('purchaseOrderTransactions');
        } elseif ($parent instanceof OrgSupplierProduct) {
            $productTransactions = PurchaseOrderTransaction::query()
                ->select('purchase_order_id')
                ->selectRaw('SUM(quantity_ordered) as quantity_ordered')
                ->selectRaw('SUM(org_net_amount) as org_net_amount')
                ->where('org_supplier_product_id', $parent->id)
                ->groupBy('purchase_order_id');

            $query->joinSub($productTransactions, 'product_transactions', function ($join) {
                $join->on('purchase_orders.id', '=', 'product_transactions.purchase_order_id');
            });
        } else {
            $query->where('purchase_orders.organisation_id', $parent->id);
        }

        if ($this->viewingAgent) {
            $query->where('purchase_orders.agent_id', $this->viewingAgent->id)
                ->where('purchase_orders.state', '!=', PurchaseOrderStateEnum::IN_PROCESS);
        }

        if ($this->onlyIncoming) {
            $query->where(fn (Builder $query) => GetAgentIncomingPurchaseOrdersCount::whereIncoming($query));
        }

        foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
            $query->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix,
                optional: in_array($key, ['attention', 'progress']),
            );
        }

        $query
            ->select(['purchase_orders.*'])
            ->selectRaw('purchase_orders.org_exchange * purchase_orders.'.($organisationAgent ? 'cost_items' : 'cost_total').' as org_total_cost');

        if ($parent instanceof OrgSupplierProduct) {
            $query->addSelect([
                'product_transactions.quantity_ordered',
                'product_transactions.org_net_amount',
            ]);
        }

        if ($parent instanceof Group || $organisationAgent || $parent instanceof Supplier || $parent instanceof StockDelivery) {
            $query
                ->leftJoin('organisations', 'purchase_orders.organisation_id', 'organisations.id')
                ->leftJoin('currencies', 'organisations.currency_id', 'currencies.id')
                ->addSelect([
                    'organisations.name as organisation_name',
                    'organisations.code as organisation_code',
                    'organisations.slug as organisation_slug',
                    'currencies.code as org_currency_code',
                ]);
        } else {
            $query->selectRaw('\''.$organisation->currency->code.'\' as org_currency_code');
        }

        if ($organisationAgent) {
            $query->selectRaw('count(*) over (partition by purchase_orders.supplier_id) as supplier_purchase_orders');
        }

        $query->selectSub($this->pendingLineAnswersQuery()->selectRaw('count(*)'), 'number_pending_line_answers');

        return $query
            ->defaultSort('-purchase_orders.date')
            ->with([
                'agent:id,slug,name',
                'parent' => function ($morphTo) {
                    $morphTo->morphWith([
                        OrgSupplier::class => ['supplier', 'orgAgent:id,slug'],
                    ]);
                },
            ])
            ->allowedSorts($parent instanceof OrgSupplierProduct
                ? ['reference', 'parent_name', 'date', 'quantity_ordered', 'org_net_amount']
                : ['reference', 'parent_name', 'date', 'number_current_purchase_order_transactions', 'org_total_cost'])
            ->allowedFilters([
                $globalSearch,
                ...($organisationAgent ? [
                    AllowedFilter::callback('organisation', fn ($query, $value) => $this->whereOrganisation($query, $value)),
                    AllowedFilter::callback('supplier', fn ($query, $value) => $this->whereSupplier($query, $value)),
                ] : []),
            ])
            ->withBetweenDates(['date'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    /**
     * Lines of the row's order whose new supplier price or quantity the agent asked about and the organisation has not answered yet.
     * A quantity can be asked about after the order is confirmed, until the line is shipped.
     */
    public static function pendingLineAnswersQuery(): QueryBuilderForSubquery
    {
        return DB::table('purchase_order_transactions')
            ->whereColumn('purchase_order_transactions.purchase_order_id', 'purchase_orders.id')
            ->whereIn('purchase_order_transactions.state', [PurchaseOrderTransactionStateEnum::SUBMITTED->value, PurchaseOrderTransactionStateEnum::CONFIRMED->value])
            ->where(fn ($query) => $query
                ->where('purchase_order_transactions.price_query_state', PurchaseOrderTransactionPriceQueryStateEnum::PENDING->value)
                ->orWhere('purchase_order_transactions.quantity_query_state', PurchaseOrderTransactionQuantityQueryStateEnum::PENDING->value));
    }

    public function tableStructure(Group|Organisation|OrgAgent|OrgSupplier|OrgPartner|OrgStock|OrgSupplierProduct|Supplier|StockDelivery $parent, ?array $modelOperations = null, $prefix = null, bool $withRemoveFromContainer = false): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix, $withRemoveFromContainer) {
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

            foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements'],
                );
            }

            $table
                ->column(key: 'state', label: __('State'), canBeHidden: false)
                ->column(key: 'reference', label: __('Reference'), canBeHidden: false, sortable: true, searchable: true);

            $organisationAgent = $this->getParentOrganisationAgent($parent);

            if ($parent instanceof Group || $parent instanceof Organisation || $parent instanceof OrgAgent || $parent instanceof StockDelivery) {
                $table->column(key: 'parent_name', label: __('Supplier'), canBeHidden: false, sortable: true, searchable: true);
            }

            if ($parent instanceof Group || $parent instanceof Supplier || $organisationAgent) {
                $table->column(key: 'organisation_name', label: $organisationAgent ? __('Client') : __('Organisation'), canBeHidden: false, searchable: true);
            }

            if ($parent instanceof OrgSupplierProduct) {
                $table
                    ->column(key: 'quantity_ordered', label: __('Quantity'), canBeHidden: false, sortable: true, align: 'right', type: 'number')
                    ->column(key: 'date', label: __('Date'), canBeHidden: false, sortable: true, searchable: true, align: 'right')
                    ->column(key: 'org_net_amount', label: __('Amount'), canBeHidden: false, sortable: true, type: 'currency');
            } else {
                $table
                    ->column(key: 'number_current_purchase_order_transactions', label: __('Items'), canBeHidden: false, sortable: true, align: 'right')
                    ->column(key: 'date', label: __('Date'), canBeHidden: false, sortable: true, searchable: true, align: 'right')
                    ->column(key: 'org_total_cost', label: __('Amount'), canBeHidden: false, sortable: true, searchable: true, type: 'currency');
            }

            if ($withRemoveFromContainer) {
                $table->column(key: 'actions', label: '', canBeHidden: false, align: 'right');
            }

            $table->defaultSort('-date');
        };
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function inAgentIncoming(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->onlyIncoming = true;

        return $this->asController($organisation, $request);
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
        $this->viewedFrom($organisation);

        return $this->handle($orgSupplier);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inOrgSupplierInOrgAgent(Organisation $organisation, OrgAgent $orgAgent, OrgSupplier $orgSupplier, ActionRequest $request): LengthAwarePaginator
    {
        return $this->inOrgSupplier($organisation, $orgSupplier, $request);
    }

    public function inSupplier(Supplier $supplier, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $supplier;
        $this->initialisationFromGroup($supplier->group, $request);

        return $this->handle($supplier);
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
        $purchaseOrdersImport = null;

        if ($this->onlyIncoming) {
            $title = __('Incoming POs');
            $icon  = [
                'icon'  => ['fal', 'fa-paper-plane'],
                'title' => __('Purchase orders submitted by clients and not confirmed yet'),
            ];
        } elseif ($this->parent instanceof OrgAgent) {
            $title         = $this->parent->agent->organisation->name;
            $icon          = [
                'icon'  => ['fal', 'fa-people-arrows'],
                'title' => __('Purchase orders'),
            ];
            $afterTitle    = ['label' => __('Purchase Orders')];
            $iconRight     = ['icon' => 'fal fa-clipboard-list'];
            $subNavigation = $this->getOrgAgentNavigation($this->parent);
        } elseif ($this->parent instanceof OrgPartner) {
            $title         = $this->parent->partner->name;
            $icon          = [
                'icon'  => ['fal', 'fa-users-class'],
                'title' => __('Purchase orders'),
            ];
            $afterTitle    = ['label' => __('Purchase Orders')];
            $iconRight     = ['icon' => 'fal fa-clipboard-list'];
            $subNavigation = $this->getOrgPartnerNavigation($this->parent);
            $actions       = $this->parent->partner->is_manufacturing_hub ? [] : [
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
            $actions       = [$this->getOrgSupplierPurchaseOrderAction($this->parent)];
        } elseif ($this->parent instanceof Supplier) {
            $title         = $this->parent->name;
            $icon          = [
                'icon'  => ['fal', 'fa-person-dolly'],
                'title' => __('Purchase orders'),
            ];
            $afterTitle    = ['label' => __('Purchase Orders')];
            $iconRight     = ['icon' => 'fal fa-clipboard-list'];
            $subNavigation = $this->getSupplierNavigation($this->parent);
            $actions       = $this->parent->orgSuppliers()->with('organisation')->get()
                ->filter(fn (OrgSupplier $orgSupplier) => $request->user()->authTo("procurement.$orgSupplier->organisation_id.edit"))
                ->map(fn (OrgSupplier $orgSupplier) => array_merge($this->getOrgSupplierPurchaseOrderAction($orgSupplier), [
                    'label' => __('Purchase order').' '.$orgSupplier->organisation->code,
                ]))
                ->values()
                ->all();

            if (ImportSupplierPurchaseOrders::canImportPurchaseOrders($request->user(), $this->parent)) {
                $purchaseOrdersImport = [
                    'preview_route' => [
                        'name'       => 'grp.models.supplier.purchase_orders.import.preview',
                        'parameters' => ['supplier' => $this->parent->id],
                    ],
                    'import_route'  => [
                        'name'       => 'grp.models.supplier.purchase_orders.import',
                        'parameters' => ['supplier' => $this->parent->id],
                    ],
                    'order_columns' => PlanSupplierPurchaseOrderImport::make()->orderColumnHeadings($this->parent),
                ];
            }
        }

        return Inertia::render(
            'Procurement/PurchaseOrders',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'title'       => __('purchase orders'),
                'navigation'  => $this->getParentSiblingsNavigation($this->parent, $request),
                'pageHead'    => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'subNavigation' => $subNavigation,
                    'actions'       => $actions,
                ],
                'data'          => PurchaseOrdersResource::collection($purchaseOrders),
                'filterOptions' => $this->getFilterOptions($this->parent),
                'purchase_orders_import' => $purchaseOrdersImport,
            ],
        )->table($this->tableStructure($this->parent));
    }

    /**
     * @return array{organisation?: array<int, array{label: string, value: string, count: int, tooltip: string}>, supplier?: array<int, array{label: string, value: string, count: int}>}
     */
    private function getFilterOptions(Group|Organisation|OrgAgent|OrgSupplier|OrgPartner|OrgStock|OrgSupplierProduct|Supplier|StockDelivery $parent): array
    {
        $organisationAgent = $this->getParentOrganisationAgent($parent);
        if (!$organisationAgent) {
            return [];
        }

        $search             = request()->input('filter.global');
        $organisationFilter = request()->input('filter.organisation');
        $supplierFilter     = request()->input('filter.supplier');

        $agentPurchaseOrders = function () use ($parent, $organisationAgent, $search) {
            $query = QueryBuilder::for(PurchaseOrder::class)
                ->where('purchase_orders.agent_id', $organisationAgent->id)
                ->where('purchase_orders.state', '!=', PurchaseOrderStateEnum::IN_PROCESS)
                ->when($this->onlyIncoming, fn ($query) => GetAgentIncomingPurchaseOrdersCount::whereIncoming($query))
                ->when($search, fn ($query) => $this->whereSearch($query, $search));

            foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
                $query->whereElementGroup(
                    key: $key,
                    allowedElements: array_keys($elementGroup['elements']),
                    engine: $elementGroup['engine'],
                    optional: in_array($key, ['attention', 'progress']),
                );
            }

            return $query;
        };

        return [
            'organisation' => $agentPurchaseOrders()
                ->when($supplierFilter, fn ($query) => $this->whereSupplier($query, $supplierFilter))
                ->join('organisations', 'organisations.id', 'purchase_orders.organisation_id')
                ->leftJoin('countries', 'countries.id', 'organisations.country_id')
                ->selectRaw('organisations.slug, organisations.code, organisations.name, countries.name as country_name, count(*) as total')
                ->groupBy('organisations.slug', 'organisations.code', 'organisations.name', 'countries.name')
                ->orderBy('organisations.code')
                ->toBase()
                ->get()
                ->map(fn ($organisation) => [
                    'label'   => strtoupper($organisation->code),
                    'value'   => $organisation->slug,
                    'count'   => (int) $organisation->total,
                    'tooltip' => $organisation->country_name ? "{$organisation->name} ({$organisation->country_name})" : $organisation->name,
                ])
                ->all(),
            'supplier'     => $agentPurchaseOrders()
                ->when($organisationFilter, fn ($query) => $this->whereOrganisation($query, $organisationFilter))
                ->join('suppliers', 'suppliers.id', 'purchase_orders.supplier_id')
                ->selectRaw('suppliers.slug, suppliers.name, count(*) as total')
                ->groupBy('suppliers.slug', 'suppliers.name')
                ->orderByDesc('total')
                ->orderBy('suppliers.name')
                ->toBase()
                ->get()
                ->map(fn ($supplier) => [
                    'label' => $supplier->name,
                    'value' => $supplier->slug,
                    'count' => (int) $supplier->total,
                ])
                ->all(),
        ];
    }

    private function whereSearch($query, string $value): void
    {
        $query->where(function ($query) use ($value) {
            $query->whereAnyWordStartWith('purchase_orders.reference', $value)
                ->orWhereAnyWordStartWith('purchase_orders.parent_name', $value)
                ->orWhereAnyWordStartWith('purchase_orders.agent_order_reference', $value);
        });
    }

    private function whereOrganisation($query, string $slug): void
    {
        $query->whereIn('purchase_orders.organisation_id', Organisation::where('slug', $slug)->select('id'));
    }

    private function whereSupplier($query, string $slug): void
    {
        $query->whereIn('purchase_orders.supplier_id', Supplier::where('slug', $slug)->select('id'));
    }

    public function jsonResponse(LengthAwarePaginator $purchaseOrders): AnonymousResourceCollection
    {
        foreach ($purchaseOrders as $purchaseOrder) {
            if ($purchaseOrder->parent_type === 'OrgSupplier' && $purchaseOrder->relationLoaded('parent')) {
                $purchaseOrder->parent->load('supplier');
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
            'grp.org.agent.incoming_purchase_orders.index' => array_merge(
                ShowProcurementDashboard::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'label' => __('Incoming POs'),
                            'icon'  => 'fal fa-paper-plane',
                            'route' => [
                                'name'       => 'grp.org.agent.incoming_purchase_orders.index',
                                'parameters' => $routeParameters,
                            ],
                        ]
                    ]
                ]
            ),
            'grp.org.procurement.org_agents.show.purchase-orders.index' => array_merge(
                ShowOrgAgent::make()->getBreadcrumbs($routeName, $routeParameters),
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
            'grp.org.procurement.org_suppliers.show.purchase_orders.index',
            'grp.org.procurement.org_agents.show.suppliers.show.purchase_orders.index' => array_merge(
                ShowOrgSupplier::make()->getBreadcrumbs($routeName, $routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'label' => __('Purchase Orders'),
                            'icon'  => 'fal fa-bars',
                            'route' => [
                                'name'       => $routeName,
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
            'grp.supply-chain.suppliers.purchase_orders.index' => array_merge(
                ShowSupplyChainDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'label' => __('Purchase Orders'),
                            'icon'  => 'fal fa-bars',
                            'route' => [
                                'name'       => 'grp.supply-chain.suppliers.purchase_orders.index',
                                'parameters' => $routeParameters,
                            ],
                        ],
                    ],
                ],
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
