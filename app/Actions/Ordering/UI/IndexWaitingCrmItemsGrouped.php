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
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexWaitingCrmItemsGrouped extends OrgAction
{
    use WithOrderingAuthorisation;

    public const string READY = 'ready';
    public const string WITH_WAREHOUSE = 'with_warehouse';

    /**
     * Only lines the picker left waiting hold the note back. Lines customer service released to the
     * warehouse (a replacement, or an item sent back) are its own decisions, and hiding the note
     * until they are picked hid every other missing item on it too (HELP-4683).
     */
    public static function whereReadyToContactCustomer($query): void
    {
        $query->where('delivery_notes.state', DeliveryNoteStateEnum::HANDLING_BLOCKED->value)
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('delivery_note_items')
                    ->whereColumn('delivery_note_items.delivery_note_id', 'delivery_notes.id')
                    ->where('delivery_note_items.has_waiting_warehouse', true)
                    ->whereNull('delivery_note_items.waiting_warehouse_released_at');
            });
    }

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
        $query->whereIn('delivery_notes.state', [
            DeliveryNoteStateEnum::HANDLING->value,
            DeliveryNoteStateEnum::HANDLING_BLOCKED->value,
        ]);

        $query->whereRadioFilter(
            allowedElements: [self::READY, self::WITH_WAREHOUSE],
            defaultValue: self::READY,
            engine: function ($query, string $value) {
                if ($value === self::READY) {
                    static::whereReadyToContactCustomer($query);
                } else {
                    $query->whereNot(fn ($query) => static::whereReadyToContactCustomer($query));
                }
            },
            prefix: $prefix
        );

        return $query->defaultSort('delivery_notes.id')
            ->select([
                'delivery_notes.id as delivery_note_id',
                'delivery_notes.slug as delivery_note_slug',
                'delivery_notes.reference as delivery_note_reference',
                'delivery_notes.state as delivery_note_state',
                'delivery_notes.number_items_waiting_crm as delivery_note_number_items_waiting_crm',
                'delivery_notes.number_items_waiting_warehouse as delivery_note_number_items_waiting_warehouse',
                DB::raw('(select count(*) from delivery_note_items where delivery_note_items.delivery_note_id = delivery_notes.id and delivery_note_items.quantity_not_picked > 0) as delivery_note_number_items_not_picked'),
                'delivery_notes.customer_notes as delivery_note_customer_notes',
                'delivery_notes.public_notes as delivery_note_public_notes',
                'orders.internal_notes as delivery_note_internal_notes',
                'delivery_notes.private_warehouse_note as delivery_note_private_warehouse_note',
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

    public function tableStructure(Shop $shop, ?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($shop, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $waiting = DeliveryNote::where('shop_id', $shop->id)
                ->where('number_items_waiting_crm', '>', 0)
                ->whereIn('state', [DeliveryNoteStateEnum::HANDLING, DeliveryNoteStateEnum::HANDLING_BLOCKED]);

            $ready = clone $waiting;
            static::whereReadyToContactCustomer($ready);
            $readyCount = $ready->count();

            $table->radioFilterGroup(
                key: 'radio',
                elements: [
                    ['label' => __('Ready to contact customer').' ('.$readyCount.')', 'value' => self::READY],
                    ['label' => __('Still with warehouse').' ('.((clone $waiting)->count() - $readyCount).')', 'value' => self::WITH_WAREHOUSE],
                ],
                default: self::READY
            );

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
        )->table($this->tableStructure($parent));
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
