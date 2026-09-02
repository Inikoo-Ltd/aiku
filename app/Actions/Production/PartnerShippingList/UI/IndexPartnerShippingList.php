<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\PartnerShippingList\UI;

use App\Actions\OrgAction;
use App\Actions\Production\Production\UI\ShowProduction;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Ordering\Order;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
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
            ->leftJoin('employees', 'employees.id', DB::raw('coalesce(artefacts.maker_employee_id, artefact_families.maker_employee_id)'))
            ->leftJoin('organisations', function ($join) {
                $join->on('organisations.id', 'partner_shopping_list_items.organisation_id')
                    ->whereNotNull('partner_shopping_list_items.partner_organisation_id');
            })
            ->leftJoin('transactions', 'transactions.id', 'partner_shopping_list_items.transaction_id')
            ->leftJoin('orders', 'orders.id', 'transactions.order_id')
            ->leftJoin('customers', 'customers.id', 'orders.customer_id')
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
                'organisations.code as buyer_code',
                'customers.name as customer_name',
                'orders.reference as order_reference',
            ])
            ->defaultSort('-created_at')
            ->allowedFilters([$globalSearch])
            ->allowedSorts(['stock_code', 'family', 'maker', 'buyer_code', 'priority', 'needed_by', 'state', 'created_at'])
            ->withPaginator(null, $this->groupBy ? 10000 : null, tableName: request()->route()->getName())
            ->withQueryString();
    }

    /** @return array<string, array{label: string, elements: array<string, array{0: string, 1: int}>, engine: Closure}> */
    public function getElementGroups(): array
    {
        return [
            'source' => [
                'label'    => __('Source'),
                'elements' => [
                    'partners' => [__('Partners'), 0],
                    'local'    => [__('Own customers'), 0],
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
                ->column(key: 'stock_code', label: __('Stock'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'stock_name', label: __('Name'), canBeHidden: false)
                ->column(key: 'family', label: __('Family'), canBeHidden: false, sortable: true)
                ->column(key: 'maker', label: __('Artisan'), canBeHidden: false, sortable: true)
                ->column(key: 'quantity', label: __('Quantity (SKO)'), canBeHidden: false, align: 'right')
                ->column(key: 'priority', label: __('Priority'), canBeHidden: false, sortable: true)
                ->column(key: 'needed_by', label: __('Needed by'), canBeHidden: false, sortable: true)
                ->column(key: 'state', label: __('State'), canBeHidden: false, sortable: true)
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

    public function byFor(Organisation $organisation, Production $production, ActionRequest $request): LengthAwarePaginator
    {
        $this->groupBy = 'buyer_code';
        $this->initialisationFromProduction($production, $request);

        return $this->handle($organisation);
    }

    /** @return array<int, array{label: string, items: array<int, array<string, mixed>>}> */
    public function getGroups(LengthAwarePaginator $items): array
    {
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
            $tab(__('All'), 'grp.org.productions.show.partners.index', 'fa-bars', $openItems) + ['isAnchor' => true],
            $tab(__('By artisan'), 'grp.org.productions.show.partners.by_artisan', 'fa-user-hard-hat'),
            $tab(__('By category'), 'grp.org.productions.show.partners.by_category', 'fa-layer-group'),
            $tab(__('By buyer'), 'grp.org.productions.show.partners.by_for', 'fa-building'),
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
                'groups'       => $this->groupBy ? $this->getGroups($items) : null,
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
                            'name'       => 'grp.org.productions.show.partners.index',
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
