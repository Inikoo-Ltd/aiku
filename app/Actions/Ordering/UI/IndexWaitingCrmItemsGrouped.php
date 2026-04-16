<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 Apr 2026 14:46:11 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Ordering\WithOrderingAuthorisation;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Http\Resources\Dispatching\WaitingDeliveryNoteItemsCrmGroupedResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Dispatching\DeliveryNote;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexWaitingCrmItemsGrouped extends OrgAction
{
    use WithOrderingAuthorisation;

    public function handle(Shop $shop, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereWith('delivery_notes.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(DeliveryNote::class);
        $query->leftjoin('shops', 'delivery_notes.shop_id', '=', 'shops.id')
            ->leftJoin('delivery_note_order', 'delivery_notes.id', '=', 'delivery_note_order.delivery_note_id')
            ->leftJoin('orders', 'delivery_note_order.order_id', '=', 'orders.id')
            ->leftJoin('organisations', 'delivery_notes.organisation_id', '=', 'organisations.id');


        $query->where('delivery_notes.shop_id', $shop->id);
        $query->where('delivery_notes.number_items_waiting_crm', '>', 0);
        $query->where('delivery_notes.state', DeliveryNoteStateEnum::HANDLING_BLOCKED->value);

        return $query->defaultSort('delivery_notes.id')
            ->select([
                'delivery_notes.id as delivery_note_id',
                'delivery_notes.slug as delivery_note_slug',
                'delivery_notes.reference as delivery_note_reference',
                'delivery_notes.state as delivery_note_state',
                'delivery_notes.customer_notes as delivery_note_customer_notes',
                'delivery_notes.public_notes as delivery_note_public_notes',
                'delivery_notes.internal_notes as delivery_note_internal_notes',
                'delivery_notes.shipping_notes as delivery_note_shipping_notes',
                'delivery_notes.is_premium_dispatch as delivery_note_is_premium_dispatch',
                'delivery_notes.has_extra_packing as delivery_note_has_extra_packing',
                'orders.id as order_id',
                'orders.slug as order_slug',
                'orders.reference as order_reference',
                'shops.slug as shop_slug',
                'shops.type as shop_type',
                'shops.engine as shop_engine',
                'organisations.slug as organisation_slug',
            ])
            ->allowedSorts(['delivery_note_reference'])
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
                'title' => __('There is not delivery note with waiting items'),
            ])->defaultSort('delivery_notes.id');

            $table->column(key: 'delivery_note_reference', label: __('Delivery Note'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'items', label: __('Items'), canBeHidden: false);
        };
    }

    public function htmlResponse(Shop $parent, ActionRequest $request): Response
    {
        $items = $this->handle($parent);

        return Inertia::render(
            'Ordering/WaitingCrmItems',
            [
                'breadcrumbs'       => $this->getBreadcrumbs($parent, $request->route()->originalParameters()),
                'title'             => __('Waiting Items').' (CRM)',
                'pageHead'          => [
                    'title' => __('Waiting Items'),
                    'model' => __('Pending Orders'),
                    'icon'  => [
                        'icon'  => ['fal', 'fa-hourglass-start'],
                        'title' => __('Waiting for CRM'),
                    ],
                ],
                'waiting_crm_items' => WaitingDeliveryNoteItemsCrmGroupedResource::collection($items),
            ]
        )->table($this->tableStructure());
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Shop
    {
        $this->initialisationFromShop($shop, $request);

        return $shop;
    }

    public function getBreadcrumbs(Shop $shop, array $routeParameters): array
    {
        return array_merge(
            ShowOrdersBacklog::make()->getBreadcrumbs($shop, $routeParameters),
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
