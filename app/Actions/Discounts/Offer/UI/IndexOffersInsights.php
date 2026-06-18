<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Discounts\Offer\UI;

use App\Actions\OrgAction;
use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Http\Resources\Catalogue\OffersInsightsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferCampaign;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;

class IndexOffersInsights extends OrgAction
{
    protected function getElementGroups(Shop $shop): array
    {
        return [
            'state' => [
                'label'    => __('State'),
                'elements' => array_merge_recursive(
                    OfferStateEnum::labels(),
                    OfferStateEnum::count($shop)
                ),
                'engine' => function ($query, $elements) {
                    $query->whereIn('offers.state', $elements);
                }
            ],
        ];
    }

    public function handle(Shop $shop, ?OfferCampaign $offerCampaign = null, ?string $offerType = null, ?string $fromDate = null, ?string $toDate = null, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('offers.code', $value)
                    ->orWhereWith('offers.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $redemptionOrders = DB::table('transaction_has_offer_allowances as toa')
            ->join('orders', 'orders.id', '=', 'toa.order_id')
            ->where('orders.shop_id', $shop->id)
            ->whereNotIn('orders.state', [OrderStateEnum::CREATING->value, OrderStateEnum::CANCELLED->value])
            ->whereNull('orders.deleted_at')
            ->when($fromDate, fn ($query) => $query->where('orders.date', '>=', $fromDate))
            ->when($toDate, fn ($query) => $query->where('orders.date', '<=', $toDate))
            ->groupBy('toa.offer_id', 'toa.order_id', 'orders.customer_id')
            ->selectRaw('
                toa.offer_id,
                toa.order_id,
                orders.customer_id,
                MAX(orders.gross_amount) as order_gross_amount,
                MAX(orders.net_amount) as order_net_amount,
                SUM(COALESCE(toa.discounted_amount, 0)) + SUM(COALESCE(toa.free_items_value, 0)) as discount_amount,
                MAX(orders.date) as used_at
            ')
            ->havingRaw('SUM(COALESCE(toa.discounted_amount, 0)) > 0 OR SUM(COALESCE(toa.free_items_value, 0)) > 0 OR SUM(COALESCE(toa.number_of_free_items, 0)) > 0');

        $offerRedemptions = DB::query()->fromSub($redemptionOrders, 'redemption_orders')
            ->groupBy('offer_id')
            ->selectRaw('
                offer_id,
                COUNT(*) as redemptions,
                COUNT(DISTINCT customer_id) as redemption_customers,
                SUM(order_gross_amount) as revenue_gross_amount,
                SUM(order_net_amount) as revenue_net_amount,
                SUM(discount_amount) as discounted_amount,
                MAX(used_at) as last_used_at
            ');

        $query = QueryBuilder::for(Offer::class);
        $query->where('offers.shop_id', $shop->id);

        if ($offerCampaign) {
            $query->where('offers.offer_campaign_id', $offerCampaign->id);
        }

        if ($offerType) {
            $query->where('offers.type', $offerType);
        }

        $offerCreators = DB::table('audits')
            ->join('users', 'users.id', '=', 'audits.user_id')
            ->where('audits.auditable_type', 'Offer')
            ->where('audits.event', 'created')
            ->where('audits.user_type', 'User')
            ->select('audits.auditable_id as offer_id', 'users.contact_name as created_by');

        $query->leftJoin('offer_campaigns', 'offers.offer_campaign_id', '=', 'offer_campaigns.id');
        $query->leftJoinSub($offerRedemptions, 'offer_redemptions', 'offer_redemptions.offer_id', '=', 'offers.id');
        $query->leftJoinSub($offerCreators, 'offer_creators', 'offer_creators.offer_id', '=', 'offers.id');

        foreach ($this->getElementGroups($shop) as $key => $elementGroup) {
            $query->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }

        return $query->defaultSort('-redemptions')
            ->select([
                'offers.id',
                'offers.slug',
                'offers.state',
                'offers.code',
                'offers.name',
                'offers.type',
                'offers.duration',
                'offers.start_at',
                'offers.end_at',
                'offer_campaigns.slug as offer_campaign_slug',
                'offer_campaigns.name as offer_campaign_name',
                DB::raw('COALESCE(offer_redemptions.redemptions, 0) as redemptions'),
                DB::raw('COALESCE(offer_redemptions.redemption_customers, 0) as redemption_customers'),
                DB::raw('COALESCE(offer_redemptions.revenue_gross_amount, 0) as revenue_gross_amount'),
                DB::raw('COALESCE(offer_redemptions.revenue_net_amount, 0) as revenue_net_amount'),
                DB::raw('COALESCE(offer_redemptions.discounted_amount, 0) as discounted_amount'),
                'offer_redemptions.last_used_at',
                'offer_creators.created_by',
            ])
            ->allowedSorts(['code', 'name', 'type', 'redemptions', 'redemption_customers', 'revenue_net_amount', 'discounted_amount', 'last_used_at'])
            ->allowedFilters([$globalSearch, 'code', 'name'])
            ->withPaginator($prefix, tableName: request()->route()?->getName())
            ->withQueryString();
    }

    public function tableStructure(Shop $shop, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($shop, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            foreach ($this->getElementGroups($shop) as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements']
                );
            }

            $table->withGlobalSearch();
            $table->withEmptyState([
                'icons'       => ['fal fa-badge-percent'],
                'title'       => __('No coupons or vouchers found'),
                'description' => __('There are no coupons or vouchers matching the selected filters'),
            ]);

            $table->column(key: 'state', label: '', type: 'icon', sortable: false);
            $table->column(key: 'name', label: __('Name'), sortable: true);
            $table->column(key: 'type', label: __('Type'), sortable: true);
            $table->column(key: 'redemptions', label: __('Redemptions'), sortable: true, align: 'right');
            $table->column(key: 'redemption_customers', label: __('Customers'), sortable: true, align: 'right');
            $table->column(key: 'discounted_amount', label: __('Discount given'), sortable: true, align: 'right');
            $table->column(key: 'avg_discount', label: __('Avg discount'), align: 'right');
            $table->column(key: 'revenue_net_amount', label: __('Revenue influenced'), sortable: true, align: 'right');
            $table->column(key: 'last_used_at', label: __('Last used'), sortable: true, align: 'right');
            $table->column(key: 'created_by', label: __('Created by'), sortable: false);

            $table->defaultSort('-redemptions');
        };
    }

    public function jsonResponse(LengthAwarePaginator $offers): AnonymousResourceCollection
    {
        return OffersInsightsResource::collection($offers);
    }
}
