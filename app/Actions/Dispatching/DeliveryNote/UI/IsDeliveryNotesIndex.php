<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 13 May 2025 19:02:55 Malaysia Time, Plane KL-Bali
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote\UI;

use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Http\Resources\Dispatching\DeliveryNotesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\CRM\WebUser;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Inventory\Warehouse;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Spatie\QueryBuilder\AllowedFilter;

trait IsDeliveryNotesIndex
{
    private Group|Warehouse|Shop|Order|Customer|CustomerClient $parent;
    private string $bucket;

    public function handle(Group|Warehouse|Shop|Order|Customer|CustomerClient $parent, $prefix = null, $bucket = 'all', $shopType = 'all'): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartsWith('delivery_notes.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(DeliveryNote::class);

        $query->leftjoin('customers', 'delivery_notes.customer_id', '=', 'customers.id');
        $query->leftjoin('organisations', 'delivery_notes.organisation_id', '=', 'organisations.id');
        $query->leftjoin('shops', 'delivery_notes.shop_id', '=', 'shops.id');

        if ($shopType != 'all') {
            $query->where('shops.type', $shopType);
        }

        if ($bucket == 'unassigned') {
            $query->where('delivery_notes.state', DeliveryNoteStateEnum::UNASSIGNED);
        } elseif ($bucket == 'queued') {
            $query->where('delivery_notes.state', DeliveryNoteStateEnum::QUEUED);
        } elseif ($bucket == 'handling') {
            $query->where('delivery_notes.state', DeliveryNoteStateEnum::HANDLING);
        } elseif ($bucket == 'handling_blocked') {
            $query->where('delivery_notes.state', DeliveryNoteStateEnum::HANDLING_BLOCKED);
        } elseif ($bucket == 'packed') {
            $query->where('delivery_notes.state', DeliveryNoteStateEnum::PACKED);
        } elseif ($bucket == 'finalised') {
            $query->where('delivery_notes.state', DeliveryNoteStateEnum::FINALISED);
        } elseif ($bucket == 'dispatched') {
            $query->where('delivery_notes.state', DeliveryNoteStateEnum::DISPATCHED);
        } elseif ($bucket == 'cancelled') {
            $query->where('delivery_notes.state', DeliveryNoteStateEnum::CANCELLED);
        } elseif ($bucket == 'dispatched_today') {
            $query->where('delivery_notes.state', DeliveryNoteStateEnum::DISPATCHED)
                ->where('dispatched_at', Carbon::today());
        }


        if ($parent instanceof Warehouse) {
            $query->where('delivery_notes.warehouse_id', $parent->id);
        } elseif ($parent instanceof Group) {
            $query->where('delivery_notes.group_id', $parent->id);
        } elseif ($parent instanceof Order) {
            $query->leftjoin('delivery_note_order', 'delivery_note_order.delivery_note_id', '=', 'delivery_notes.id');
            $query->where('delivery_note_order.order_id', $parent->id);
        } elseif ($parent instanceof Customer) {
            $query->where('delivery_notes.customer_id', $parent->id);
        } elseif ($parent instanceof CustomerClient) {
            $query->where('delivery_notes.customer_client_id', $parent->id);
        } elseif ($parent instanceof Shop) {
            $query->where('delivery_notes.shop_id', $parent->id);
        } else {
            abort(419);
        }


        return $query->defaultSort('-delivery_notes.date')
            ->select([
                'delivery_notes.id',
                'delivery_notes.reference',
                'delivery_notes.date',
                'delivery_notes.state',
                'delivery_notes.created_at',
                'delivery_notes.updated_at',
                'delivery_notes.slug',
                'delivery_notes.type',
                'delivery_notes.state',
                'delivery_notes.weight',
                'delivery_notes.effective_weight',
                'delivery_notes.estimated_weight',
                'shops.slug as shop_slug',
                'customers.slug as customer_slug',
                'customers.name as customer_name',
                'delivery_note_stats.number_items as number_items',
                'shops.name as shop_name',
                'shops.slug as shop_slug',
                'organisations.name as organisation_name',
                'organisations.slug as organisation_slug',
            ])
            ->leftJoin('delivery_note_stats', 'delivery_notes.id', 'delivery_note_stats.delivery_note_id')
            ->allowedSorts(['reference', 'date', 'number_items', 'customer_name', 'type', 'effective_weight'])
            ->allowedFilters([$globalSearch])
            ->withBetweenDates(['date'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure($parent, $prefix = null, $bucket = 'all'): Closure
    {
        $employee = null;
        if(!request()->user() instanceof WebUser) {
            $employee = request()->user()->employees()->first() ?? null;
        }
        $pickerEmployee = null;
        if ($employee) {
            $pickerEmployee = $employee->jobPositions()->where('name', 'Picker')->first();
        }
        return function (InertiaTable $table) use ($parent, $prefix, $bucket, $pickerEmployee) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table->betweenDates(['date']);

            $noResults = __("No delivery notes found");
            if ($parent instanceof Customer) {
                $stats = $parent->stats;
                $noResults = __("Customer has no delivery notes");
            } elseif ($parent instanceof CustomerClient) {
                $stats = $parent->stats;
                $noResults = __("This customer client hasn't place any delivery notes");
            } elseif ($parent instanceof Group) {
                $stats = $parent->orderingStats;
            } else {
                $stats = $parent->salesStats;
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title' => $noResults,
                        'count' => $stats->number_delivery_notes ?? 0
                    ]
                );


            $table->column(key: 'reference', label: __('reference'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'date', label: __('date'), canBeHidden: false, sortable: true, searchable: true);
            if (!$parent instanceof Customer) {
                $table->column(key: 'customer_name', label: __('customer'), canBeHidden: false, sortable: true, searchable: true);
            }
            if ($parent instanceof Group) {
                $table->column(key: 'organisation_name', label: __('organisation'), canBeHidden: false, searchable: true);
                $table->column(key: 'shop_name', label: __('shop'), canBeHidden: false, searchable: true);
            }
            $table->column(key: 'effective_weight', label: __('weight'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'number_items', label: __('items'), canBeHidden: false, sortable: true, searchable: true);
            if ($bucket && $bucket == 'unassigned' && $pickerEmployee) {
                $table->column(key: 'action', label: __('Action'), canBeHidden: false, sortable: false, searchable: false);
            }
        };
    }

    public function jsonResponse(LengthAwarePaginator $deliveryNotes): AnonymousResourceCollection
    {
        return DeliveryNotesResource::collection($deliveryNotes);
    }

}
