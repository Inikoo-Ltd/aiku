<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Tue, 11 Aug 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Ordering\Order\UI;

use App\Actions\CRM\TrafficSource\GetAttributionWindow;
use App\Actions\CRM\TrafficSource\WithAggregatedChannelQueries;
use App\Actions\OrgAction;
use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Http\Resources\Ordering\OrdersResource;
use App\InertiaTable\InertiaTable;
use App\Models\CRM\TrafficSource;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * The orders a marketing channel may claim, listed by the same rule the marketing dashboard counts
 * them by: the customer was touched by the channel before ordering, and no longer ago than the
 * shop's attribution window allows.
 *
 * Above shop level a channel is a type rather than a record - every shop keeps its own traffic
 * source row for the same type - so the parent is the organisation or the group and the type says
 * which channel.
 */
class IndexOrdersInTrafficSource extends OrgAction
{
    use WithAggregatedChannelQueries;

    public function handle(TrafficSource|Organisation|Group $parent, $prefix = null, ?TrafficSourcesTypeEnum $channelType = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereWith('orders.reference', $value)
                    ->orWhereWith('orders.tracking_number', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(Order::class);

        /* The same two exclusions the dashboard counts by: a basket nobody submitted and an order
           that was called off are not orders the channel won. */
        $query->whereNotIn('orders.state', [OrderStateEnum::CREATING, OrderStateEnum::CANCELLED])
            ->whereNull('orders.deleted_at');

        $this->constrainToChannel($query, $parent, $channelType);

        $query->leftJoin('customers', 'orders.customer_id', '=', 'customers.id');
        $query->leftJoin('currencies', 'orders.currency_id', '=', 'currencies.id');
        $query->leftJoin('organisations', 'orders.organisation_id', '=', 'organisations.id');
        $query->leftJoin('shops', 'orders.shop_id', '=', 'shops.id');

        $query->select([
            'orders.id',
            'orders.slug',
            'orders.reference',
            'orders.date',
            'orders.submitted_at',
            'orders.state',
            'orders.net_amount',
            'orders.total_amount',
            'orders.pay_detailed_status',
            'customers.name as customer_name',
            'customers.slug as customer_slug',
            'customers.is_vip as is_customer_vip',
            'currencies.code as currency_code',
            'currencies.id as currency_id',
            'shops.name as shop_name',
            'shops.code as shop_code',
            'shops.slug as shop_slug',
            'organisations.name as organisation_name',
            'organisations.code as organisation_code',
            'organisations.slug as organisation_slug',
        ]);

        /* Only for a single source: the share is read against one shop's window, and above shop level
           the rows come from shops that may each measure over a different one. */
        if ($parent instanceof TrafficSource) {
            $query->addSelect([
                'attribution_share' => $this->attributions(
                    DB::query(),
                    $parent,
                    $channelType,
                    GetAttributionWindow::run($parent->shop)
                )->selectRaw('SUM(p.share)'),
            ]);
        }

        $this->addLastTouchSelect($query, $parent, $channelType);

        return $query->defaultSort('-orders.date')
            ->allowedSorts(['reference', 'date', 'submitted_at', 'last_touch_at', 'net_amount', 'customer_name', 'organisation_code', 'shop_code'])
            ->withBetweenDates(['date'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    /**
     * The touch the channel is claiming the order on, so the row says how long the customer took to
     * buy after being reached rather than only when they bought.
     *
     * One branch per attribution window again, coalesced because a shop belongs to exactly one of
     * them: every branch but its own returns nothing for the row.
     */
    protected function addLastTouchSelect($query, TrafficSource|Organisation|Group $parent, ?TrafficSourcesTypeEnum $channelType): void
    {
        $selects  = [];
        $bindings = [];

        foreach ($this->windowGroups($parent) as $group) {
            $touches = $this->attributions(DB::query(), $parent, $channelType, $group['window'])
                ->whereIn('orders.shop_id', $group['shop_ids'])
                ->selectRaw('MAX(p.last_touch_at)');

            $selects[] = '('.$touches->toSql().')';
            $bindings  = array_merge($bindings, $touches->getBindings());
        }

        if (!$selects) {
            $query->selectRaw('NULL as last_touch_at');

            return;
        }

        $query->selectRaw('COALESCE('.implode(', ', $selects).') as last_touch_at', $bindings);
    }

    /**
     * Orders of the parent's shops whose customer carries a touch from this channel. Written as a
     * branch per attribution window because the window is a shop setting: two shops under the same
     * organisation can disagree on how long a touch keeps earning.
     */
    protected function constrainToChannel($query, TrafficSource|Organisation|Group $parent, ?TrafficSourcesTypeEnum $channelType): void
    {
        $query->where(function ($scoped) use ($parent, $channelType) {
            foreach ($this->windowGroups($parent) as $group) {
                $scoped->orWhere(function ($inShops) use ($group, $parent, $channelType) {
                    $inShops->whereIn('orders.shop_id', $group['shop_ids'])
                        ->whereExists(fn ($exists) => $this->attributions($exists, $parent, $channelType, $group['window'])->select(DB::raw(1)));
                });
            }
        });
    }

    /**
     * @return Collection<int, array{window: int, shop_ids: array<int, int>}>
     */
    protected function windowGroups(TrafficSource|Organisation|Group $parent): Collection
    {
        if ($parent instanceof TrafficSource) {
            return collect([
                [
                    'window'   => GetAttributionWindow::run($parent->shop),
                    'shop_ids' => [$parent->shop_id],
                ],
            ]);
        }

        return $this->shopsByWindow($parent->shops()->get());
    }

    /**
     * The touches that let this channel claim the order: the customer's own, from this channel, and
     * landing inside the window that ends at the order's date.
     */
    protected function attributions($query, TrafficSource|Organisation|Group $parent, ?TrafficSourcesTypeEnum $channelType, int $window)
    {
        $query->from('model_has_traffic_sources as p')
            ->join('traffic_sources as ts', 'ts.id', '=', 'p.traffic_source_id')
            ->whereColumn('p.model_id', 'orders.customer_id')
            ->where('p.model_type', 'Customer');

        if ($parent instanceof TrafficSource) {
            $query->where('p.traffic_source_id', $parent->id);
        } else {
            $query->where('ts.type', $channelType->value);
        }

        $this->constrainToTouchWindow($query, 'orders.date', $window);

        return $query;
    }

    public function tableStructure(TrafficSource|Organisation|Group $parent, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');

                InertiaTable::updateQueryBuilderParameters($prefix);
            }

            $table->betweenDates(['date']);

            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('order'), __('orders')])
                ->withEmptyState([
                    'title' => __('No orders attributed to this channel yet'),
                ]);

            $table->column(key: 'state', label: '', canBeHidden: false, type: 'icon');

            if ($parent instanceof Group) {
                $table->column(key: 'organisation_code', label: __('Org'), canBeHidden: false, sortable: true, searchable: true);
            }

            if (!$parent instanceof TrafficSource) {
                $table->column(key: 'shop_code', label: __('Shop'), canBeHidden: false, sortable: true, searchable: true);
            }

            $table->column(key: 'reference', label: __('Reference'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'date', label: __('Date'), canBeHidden: false, sortable: true, type: 'date');

            /* The date is the day the order is booked to; the submission time says how long after the
               touch the customer actually placed it. */
            $table->column(key: 'submitted_at', label: __('Submitted'), tooltip: __('When the customer submitted the order'), sortable: true, type: 'date_hm');
            $table->column(key: 'customer_name', label: __('Customer'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'last_touch_at', label: __('Touched'), tooltip: __('The most recent touch from this channel the order is claimed on'), sortable: true, type: 'date_hm');
            $table->column(key: 'net_amount', label: __('Net'), canBeHidden: false, sortable: true, type: 'currency');

            /* A customer touched by several channels is only partly this one's, so show the share
               rather than implying the channel earned the whole order beside it. */
            if ($parent instanceof TrafficSource) {
                $table->column(key: 'attribution_share', label: __('Attribution'), canBeHidden: true);
            }

            $table->defaultSort('-date');
        };
    }

    public function jsonResponse(LengthAwarePaginator $orders): AnonymousResourceCollection
    {
        return OrdersResource::collection($orders);
    }
}
