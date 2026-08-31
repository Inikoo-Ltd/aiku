<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PartnerShoppingListItem\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\InertiaTable\InertiaTable;
use App\Models\Procurement\PartnerShoppingListItem;
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
    use WithProcurementAuthorisation;

    public function handle(Organisation $seller): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('stocks.code', $value)
                    ->orWhereStartWith('stocks.name', $value)
                    ->orWhereStartWith('organisations.code', $value);
            });
        });

        return QueryBuilder::for(PartnerShoppingListItem::class)
            ->leftJoin('stocks', 'stocks.id', 'partner_shopping_list_items.stock_id')
            ->leftJoin('organisations', 'organisations.id', 'partner_shopping_list_items.organisation_id')
            ->where('partner_shopping_list_items.partner_organisation_id', $seller->id)
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
                'organisations.code as buyer_code',
            ])
            ->defaultSort('-created_at')
            ->allowedFilters([$globalSearch])
            ->allowedSorts(['stock_code', 'buyer_code', 'priority', 'needed_by', 'state', 'created_at'])
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
                ->column(key: 'quantity', label: __('Quantity (SKO)'), canBeHidden: false, align: 'right')
                ->column(key: 'priority', label: __('Priority'), canBeHidden: false, sortable: true)
                ->column(key: 'needed_by', label: __('Needed by'), canBeHidden: false, sortable: true)
                ->column(key: 'state', label: __('State'), canBeHidden: false, sortable: true)
                ->column(key: 'created_at', label: __('Added'), canBeHidden: false, sortable: true)
                ->defaultSort('-created_at');
        };
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function htmlResponse(LengthAwarePaginator $items, ActionRequest $request): Response
    {
        return Inertia::render(
            'Procurement/PartnerShippingList',
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
                'data' => $items,
            ]
        )->table($this->tableStructure());
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowProcurementDashboard::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.procurement.org_partners.shipping_list.index',
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
