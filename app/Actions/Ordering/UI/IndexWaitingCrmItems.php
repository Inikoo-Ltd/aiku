<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Apr 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Ordering\WithOrderingAuthorisation;
use App\Http\Resources\Ordering\WaitingCrmItemsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexWaitingCrmItems extends OrgAction
{
    use WithOrderingAuthorisation;

    private Group|Organisation|Shop $parent;

    public function handle(Group|Organisation|Shop $parent, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('org_stocks.code', $value)
                    ->orWhereStartWith('org_stocks.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(DeliveryNoteItem::class);

        $query->join('delivery_notes', 'delivery_note_items.delivery_note_id', '=', 'delivery_notes.id')
            ->leftJoin('delivery_note_order', 'delivery_notes.id', '=', 'delivery_note_order.delivery_note_id')
            ->leftJoin('orders', 'delivery_note_order.order_id', '=', 'orders.id')
            ->leftJoin('shops', 'orders.shop_id', '=', 'shops.id')
            ->leftJoin('organisations', 'orders.organisation_id', '=', 'organisations.id')
            ->leftJoin('org_stocks', 'delivery_note_items.org_stock_id', '=', 'org_stocks.id')
            ->where('delivery_note_items.quantity_waiting_crm', '>', 0);

        if ($parent instanceof Shop) {
            $query->where('delivery_note_items.shop_id', $parent->id);
        } elseif ($parent instanceof Organisation) {
            $query->where('delivery_note_items.organisation_id', $parent->id);
        } else {
            $query->where('delivery_note_items.group_id', $parent->id);
        }

        return $query->defaultSort('org_stocks.code')
            ->select([
                'delivery_note_items.id',
                'delivery_note_items.quantity_waiting_crm',
                'delivery_note_items.notes',
                'org_stocks.code as org_stock_code',
                'org_stocks.name as org_stock_name',
                'org_stocks.slug as org_stock_slug',
                'orders.id as order_id',
                'orders.slug as order_slug',
                'orders.reference as order_reference',
                'shops.slug as shop_slug',
                'shops.type as shop_type',
                'shops.engine as shop_engine',
                'organisations.slug as organisation_slug',
            ])
            ->allowedSorts(['org_stock_code', 'org_stock_name', 'quantity_waiting_crm', 'order_reference'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table->withEmptyState([
                'title' => __('No items waiting for CRM'),
            ])->defaultSort('org_stock_code');

            $table->column(key: 'order_reference', label: __('Order'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'org_stock_code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'org_stock_name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'quantity_waiting_crm', label: __('Waiting Qty'), canBeHidden: false, sortable: true);
        };
    }

    public function htmlResponse(Group|Organisation|Shop $parent, ActionRequest $request): Response
    {
        $items = $this->handle($parent);

        return Inertia::render(
            'Ordering/WaitingCrmItems',
            [
                'breadcrumbs'       => $this->getBreadcrumbs($parent, $request->route()->originalParameters()),
                'title'             => __('Waiting Items') . ' (' . $items->total() . ')',
                'pageHead'          => [
                    'title' => __('Waiting Items'),
                    'model' => __('Pending Orders'),
                    'icon'  => [
                        'icon'  => ['fal', 'fa-hourglass-start'],
                        'title' => __('Waiting for CRM'),
                    ],
                ],
                'waiting_crm_items' => WaitingCrmItemsResource::collection($items),
            ]
        )->table($this->tableStructure());
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Group|Organisation|Shop
    {
        $this->initialisationFromShop($shop, $request);
        $this->parent = $shop;

        return $shop;
    }

    public function inOrganisation(Organisation $organisation, ActionRequest $request): Group|Organisation|Shop
    {
        $this->initialisation($organisation, $request);
        $this->parent = $organisation;

        return $organisation;
    }

    public function inGroup(ActionRequest $request): Group|Organisation|Shop
    {
        $this->initialisationFromGroup(group(), $request);
        $this->parent = group();

        return group();
    }

    public function getBreadcrumbs(Group|Organisation|Shop $parent, array $routeParameters): array
    {
        return array_merge(
            ShowOrdersBacklog::make()->getBreadcrumbs($parent, $routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.ordering.backlog.waiting_items',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Waiting for CRM'),
                        'icon'  => 'fal fa-bars',
                    ],
                ],
            ]
        );
    }
}
