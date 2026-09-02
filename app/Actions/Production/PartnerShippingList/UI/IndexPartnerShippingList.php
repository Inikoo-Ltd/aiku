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
use App\InertiaTable\InertiaTable;
use App\Models\Ordering\Order;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexPartnerShippingList extends OrgAction
{
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
            ->leftJoin('organisations', 'organisations.id', 'partner_shopping_list_items.organisation_id')
            ->where('partner_shopping_list_items.partner_organisation_id', $seller->id);


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
                'organisations.code as buyer_code',
            ])
            ->defaultSort('-created_at')
            ->allowedFilters([$globalSearch])
            ->allowedSorts(['stock_code', 'family', 'buyer_code', 'priority', 'needed_by', 'state', 'created_at'])
            ->withPaginator(null, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(): Closure
    {
        return function (InertiaTable $table) {
            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('Shipping list item'), __('Shipping list items')])
                ->withEmptyState([
                    'title' => __('No partner requests to fulfil'),
                ])
                ->column(key: 'pick', label: '', canBeHidden: false)
                ->column(key: 'buyer_code', label: __('For'), canBeHidden: false, sortable: true)
                ->column(key: 'stock_code', label: __('Stock'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'stock_name', label: __('Name'), canBeHidden: false)
                ->column(key: 'family', label: __('Family'), canBeHidden: false, sortable: true)
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

    public function htmlResponse(LengthAwarePaginator $items, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Production/PartnerShippingList',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Partner shipping list'),
                'pageHead'    => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-truck-loading'],
                        'title' => __('Partner shipping list'),
                    ],
                    'title' => __('Partner shipping list'),
                ],
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
                        'label' => __('Partner shipping list'),
                        'icon'  => 'fal fa-truck-loading',
                    ],
                ],
            ]
        );
    }
}
