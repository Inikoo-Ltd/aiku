<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\PartnerShippingList\UI;

use App\Actions\OrgAction;
use App\Actions\Production\PartnerShippingList\GetMixesToPrepare;
use App\Actions\Production\PartnerShippingList\GetMixJobOrders;
use App\Actions\Production\Production\UI\ShowProduction;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Models\HumanResources\Employee;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Ordering\Order;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexPartnerShippingList extends OrgAction
{
    private ?string $groupBy = null;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            'productions-view.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.view",
            "productions_operations.{$this->production->id}.orchestrate",
            "productions_operations.{$this->production->id}.prepare",
            "productions_procurement.{$this->production->id}.view",
        ]);
    }

    public function handle(Organisation $seller): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('stocks.code', $value)
                    ->orWhereStartWith('stocks.name', $value)
                    ->orWhereStartWith('organisations.code', $value);
            });
        });

        $queryBuilder = QueryBuilder::for(PartnerShoppingListItem::class)
            ->leftJoin('stocks', 'stocks.id', 'partner_shopping_list_items.stock_id')
            ->leftJoin('org_stocks', function ($join) use ($seller) {
                $join->on('org_stocks.stock_id', 'stocks.id')
                    ->where('org_stocks.organisation_id', $seller->id);
            })
            ->leftJoin('artefacts', function ($join) {
                $join->on('artefacts.org_stock_id', 'org_stocks.id')
                    ->whereNull('artefacts.deleted_at');
            })
            ->leftJoin('artefact_families', 'artefacts.artefact_family_id', 'artefact_families.id')
            ->leftJoin('employees', 'employees.id', DB::raw("coalesce(
                (select employee_id from artisan_assignments where artisanable_type = 'Artefact' and artisanable_id = artefacts.id order by position limit 1),
                (select employee_id from artisan_assignments where artisanable_type = 'ArtefactFamily' and artisanable_id = artefact_families.id order by position limit 1)
            )"))
            ->leftJoin('organisations', function ($join) {
                $join->on('organisations.id', 'partner_shopping_list_items.organisation_id')
                    ->whereNotNull('partner_shopping_list_items.partner_organisation_id');
            })
            ->leftJoin('transactions', 'transactions.id', 'partner_shopping_list_items.transaction_id')
            ->leftJoin('orders', 'orders.id', 'transactions.order_id')
            ->leftJoin('customers', 'customers.id', 'orders.customer_id')
            ->leftJoin('job_orders', 'job_orders.id', 'partner_shopping_list_items.job_order_id')
            ->leftJoin('employees as job_order_artisans', 'job_order_artisans.id', 'job_orders.employee_id')
            ->where(function ($query) use ($seller) {
                $query->where('partner_shopping_list_items.partner_organisation_id', $seller->id)
                    ->orWhere(function ($query) use ($seller) {
                        $query->whereNull('partner_shopping_list_items.partner_organisation_id')
                            ->where('partner_shopping_list_items.organisation_id', $seller->id);
                    });
            });

        foreach ($this->getElementGroups() as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine']
            );
        }


        return $queryBuilder
            ->select([
                'partner_shopping_list_items.id',
                'partner_shopping_list_items.job_order_id',
                'partner_shopping_list_items.preparing_at',
                'partner_shopping_list_items.quantity_to_produce',
                'partner_shopping_list_items.quantity',
                'partner_shopping_list_items.priority',
                'partner_shopping_list_items.state',
                'partner_shopping_list_items.needed_by',
                'partner_shopping_list_items.notes',
                'partner_shopping_list_items.created_at',
                'stocks.code as stock_code',
                'stocks.name as stock_name',
                'artefact_families.name as family',
                'employees.contact_name as maker',
                'employees.id as maker_id',
                'organisations.code as buyer_code',
                'customers.name as customer_name',
                'orders.reference as order_reference',
                'job_orders.reference as job_order_reference',
                'job_orders.slug as job_order_slug',
                'job_orders.state as job_order_state',
                'job_order_artisans.contact_name as job_order_artisan',
                DB::raw('(select sum(quantity) from job_order_items where job_order_items.job_order_id = job_orders.id and job_order_items.artefact_id = artefacts.id) as job_order_quantity'),
            ])
            ->defaultSort('-created_at')
            ->allowedFilters([$globalSearch])
            ->allowedSorts(['stock_code', 'family', 'maker', 'buyer_code', 'priority', 'needed_by', 'state', 'created_at'])
            ->withPaginator(null, $this->groupBy === 'mixes' ? 1 : ($this->groupBy ? 10000 : null), tableName: request()->route()->getName())
            ->withQueryString();
    }

    /** @return array<string, array{label: string, elements: array<string, array{0: string, 1: int}>, engine: Closure}> */
    public function getElementGroups(): array
    {
        $counts = PartnerShoppingListItem::query()
            ->selectRaw("case when partner_organisation_id is null then 'local' else 'partners' end as source, count(*) as total")
            ->where(function ($query) {
                $query->where('partner_organisation_id', $this->organisation->id)
                    ->orWhere(function ($query) {
                        $query->whereNull('partner_organisation_id')->where('organisation_id', $this->organisation->id);
                    });
            })
            ->groupBy('source')
            ->pluck('total', 'source');

        return [
            'source' => [
                'label'    => __('Source'),
                'elements' => [
                    'partners' => [__('Partners'), $counts['partners'] ?? 0],
                    'local'    => [__('Own customers'), $counts['local'] ?? 0],
                ],
                'engine' => function ($query, $elements) {
                    if (in_array('partners', $elements) && !in_array('local', $elements)) {
                        $query->whereNotNull('partner_shopping_list_items.partner_organisation_id');
                    } elseif (in_array('local', $elements) && !in_array('partners', $elements)) {
                        $query->whereNull('partner_shopping_list_items.partner_organisation_id');
                    }
                },
            ],
        ];
    }

    public function tableStructure(): Closure
    {
        return function (InertiaTable $table) {
            foreach ($this->getElementGroups() as $key => $elementGroup) {
                $table->elementGroup(key: $key, label: $elementGroup['label'], elements: $elementGroup['elements']);
            }

            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('Shipping list item'), __('Shipping list items')])
                ->withEmptyState([
                    'title' => __('Nothing to produce'),
                ])
                ->column(key: 'pick', label: '', canBeHidden: false)
                ->column(key: 'buyer_code', label: __('For'), canBeHidden: false, sortable: true)
                ->column(key: 'stock_code', label: __('Artefact'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'maker', label: __('Artisan'), canBeHidden: false, sortable: true)
                ->column(key: 'quantity', label: __('Qty (SKO)'), canBeHidden: false, align: 'right')
                ->column(key: 'priority', label: __('Priority'), canBeHidden: false, sortable: true)
                ->column(key: 'state', label: __('State'), canBeHidden: false, sortable: true)
                ->column(key: 'job_order_reference', label: __('Job order'), canBeHidden: false)
                ->column(key: 'created_at', label: __('Added'), canBeHidden: false, sortable: true)
                ->defaultSort('-created_at');
        };
    }

    public function asController(Organisation $organisation, Production $production, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($organisation);
    }

    public function byFamily(Organisation $organisation, Production $production, ActionRequest $request): LengthAwarePaginator
    {
        $this->groupBy = 'family';
        $this->initialisationFromProduction($production, $request);

        return $this->handle($organisation);
    }

    public function byArtisan(Organisation $organisation, Production $production, ActionRequest $request): LengthAwarePaginator
    {
        $this->groupBy = 'maker';
        $this->initialisationFromProduction($production, $request);

        return $this->handle($organisation);
    }

    public function mixes(Organisation $organisation, Production $production, ActionRequest $request): LengthAwarePaginator
    {
        $this->groupBy = 'mixes';
        $this->initialisationFromProduction($production, $request);

        return $this->handle($organisation);
    }

    public function board(Organisation $organisation, Production $production, ActionRequest $request): LengthAwarePaginator
    {
        $this->groupBy = 'board';
        $this->initialisationFromProduction($production, $request);

        return $this->handle($organisation);
    }

    public function byFor(Organisation $organisation, Production $production, ActionRequest $request): LengthAwarePaginator
    {
        $this->groupBy = 'buyer_code';
        $this->initialisationFromProduction($production, $request);

        return $this->handle($organisation);
    }

    /** @return array<int, array{label: string, items: array<int, array<string, mixed>>}> */
    public function getGroups(LengthAwarePaginator $items): array
    {
        if ($this->groupBy === 'board') {
            return $this->getBoardLanes($items);
        }

        return collect($items->items())
            ->groupBy(fn ($item) => $item->{$this->groupBy} ?? ($this->groupBy === 'buyer_code' ? $item->customer_name : null) ?? '')
            ->sortKeys()
            ->map(fn ($groupItems, $label) => [
                'label' => $label === '' ? __('Unassigned') : $label,
                'items' => $groupItems->values()->all(),
            ])
            ->values()
            ->all();
    }

    /** @return array<int, array{label: string, items: array<int, array<string, mixed>>}> */
    public function getBoardLanes(LengthAwarePaginator $items): array
    {
        $stageByJobOrderState = [
            ''             => 'backlog',
            'in_process'   => 'assigned',
            'submitted'    => 'assigned',
            'confirmed'    => 'producing',
            'received'     => 'done',
            'not_received' => 'done',
            'booking_in'   => 'done',
            'booked_in'    => 'done',
        ];
        $lanes  = ['backlog' => __('Backlog'), 'preparing' => __('Preparing'), 'assigned' => __('Assigned'), 'producing' => __('Producing'), 'done' => __('Done')];
        $byLane = collect($items->items())->groupBy(function ($item) use ($stageByJobOrderState) {
            if (!$item->job_order_id) {
                return $item->preparing_at ? 'preparing' : 'backlog';
            }

            return $stageByJobOrderState[$item->job_order_state] ?? 'assigned';
        });

        return collect($lanes)
            ->map(fn ($label, $key) => ['label' => $label, 'items' => $byLane->get($key, collect())->values()->all()])
            ->values()
            ->all();
    }

    /** @return array<int, array{id: int, name: string, open_job_orders: int, hidden: bool}> */
    public function getArtisanWorkload(): array
    {
        $hiddenIds = Arr::get($this->production->data, 'hidden_artisan_ids', []);

        return Employee::query()
            ->where('employees.organisation_id', $this->organisation->id)
            ->where('employees.state', EmployeeStateEnum::WORKING)
            ->leftJoin('job_orders', function ($join) {
                $join->on('job_orders.employee_id', 'employees.id')
                    ->where('job_orders.production_id', $this->production->id)
                    ->whereIn('job_orders.state', [JobOrderStateEnum::IN_PROCESS->value, JobOrderStateEnum::SUBMITTED->value, JobOrderStateEnum::CONFIRMED->value]);
            })
            ->groupBy('employees.id', 'employees.contact_name')
            ->orderByRaw('count(job_orders.id), employees.contact_name')
            ->get(['employees.id', 'employees.contact_name', DB::raw('count(job_orders.id) as open_job_orders')])
            ->map(fn (Employee $employee) => [
                'id'              => $employee->id,
                'name'            => $employee->contact_name,
                'open_job_orders' => (int) $employee->open_job_orders,
                'hidden'          => in_array($employee->id, $hiddenIds),
            ])
            ->all();
    }

    public function getSubNavigation(array $routeParameters): array
    {
        $tab = fn (string $label, string $route, string $icon, ?int $number = null) => array_filter([
            'label'    => $label,
            'root'     => $route,
            'route'    => ['name' => $route, 'parameters' => $routeParameters],
            'leftIcon' => ['icon' => ['fal', $icon], 'tooltip' => $label],
            'number'   => $number,
        ]);

        $openItems = PartnerShoppingListItem::query()
            ->where('state', ShoppingListItemStateEnum::OPEN)
            ->where(function ($query) {
                $query->where('partner_organisation_id', $this->organisation->id)
                    ->orWhere(function ($query) {
                        $query->whereNull('partner_organisation_id')->where('organisation_id', $this->organisation->id);
                    });
            })
            ->count();

        return [
            $tab(__('Board'), 'grp.org.productions.show.to_produce.index', 'fa-columns', $openItems) + ['isAnchor' => true],
            $tab(__('All'), 'grp.org.productions.show.to_produce.list', 'fa-bars'),
            $tab(__('By artisan'), 'grp.org.productions.show.to_produce.by_artisan', 'fa-user-hard-hat'),
            $tab(__('By category'), 'grp.org.productions.show.to_produce.by_category', 'fa-layer-group'),
            $tab(__('By buyer'), 'grp.org.productions.show.to_produce.by_for', 'fa-building'),
            $tab(__('Mixes'), 'grp.org.productions.show.to_produce.mixes', 'fa-blender-phone'),
        ];
    }

    public function htmlResponse(LengthAwarePaginator $items, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Production/PartnerShippingList',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('To produce'),
                'pageHead'    => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-truck-loading'],
                        'title' => __('To produce'),
                    ],
                    'title'         => __('To produce'),
                    'subNavigation' => $this->getSubNavigation($request->route()->originalParameters()),
                ],
                'groupBy'      => $this->groupBy,
                'artisanWorkload' => in_array($this->groupBy, ['maker', 'board', 'mixes']) ? $this->getArtisanWorkload() : null,
                'groups'       => $this->groupBy && $this->groupBy !== 'mixes' ? $this->getGroups($items) : null,
                'mixes'        => $this->groupBy === 'mixes' ? GetMixesToPrepare::run($this->production) : null,
                'mixJobOrders' => $this->groupBy === 'mixes' ? GetMixJobOrders::run($this->production) : null,
                'data'         => $items,
                'pickedOrders' => $this->getPickedOrders($this->organisation),
            ]
        )->table($this->tableStructure());
    }

    public function getPickedOrders(Organisation $seller): array
    {
        return Order::query()
            ->join('sales_channels', 'sales_channels.id', 'orders.sales_channel_id')
            ->join('customers', 'customers.id', 'orders.customer_id')
            ->where('orders.organisation_id', $seller->id)
            ->where('orders.state', OrderStateEnum::CREATING)
            ->where('sales_channels.code', 'intercompany')
            ->select([
                'orders.id',
                'orders.reference',
                'orders.net_amount',
                'orders.currency_id',
                'customers.name as buyer_name',
            ])
            ->withCount('transactions')
            ->get()
            ->map(fn (Order $order) => [
                'id'                 => $order->id,
                'reference'          => $order->reference,
                'net_amount'         => $order->net_amount,
                'currency_code'      => $order->currency->code,
                'buyer_name'         => $order->buyer_name,
                'transactions_count' => $order->transactions_count,
            ])
            ->all();
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowProduction::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.productions.show.to_produce.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('To produce'),
                        'icon'  => 'fal fa-truck-loading',
                    ],
                ],
            ]
        );
    }
}
