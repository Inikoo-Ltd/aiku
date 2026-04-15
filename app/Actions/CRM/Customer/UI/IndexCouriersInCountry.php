<?php

namespace App\Actions\CRM\Customer\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Dispatching\Shipper;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class IndexCouriersInCountry extends OrgAction
{
    public function handle(Shop $shop, string $countryCode, ?string $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(Shipper::class)
            ->select(
                'shippers.id',
                'shippers.slug',
                'shippers.name',
                DB::raw('COUNT(DISTINCT orders.id) as total_orders'),
                DB::raw('COALESCE(SUM(orders.total_amount), 0) as total_amount'),
                DB::raw('COALESCE(SUM(orders.net_amount), 0) as total_net_amount'),
                DB::raw('COALESCE(AVG(orders.total_amount), 0) as avg_order_amount'),
                DB::raw("'" . $shop->currency->code . "' as currency_code")
            )
            ->join('shipments', 'shipments.shipper_id', '=', 'shippers.id')
            ->join('model_has_shipments', function ($join) {
                $join->on('model_has_shipments.shipment_id', '=', 'shipments.id')
                    ->where('model_has_shipments.model_type', '=', 'DeliveryNote');
            })
            ->join('delivery_notes', 'delivery_notes.id', '=', 'model_has_shipments.model_id')
            ->join('delivery_note_order', 'delivery_note_order.delivery_note_id', '=', 'delivery_notes.id')
            ->join('orders', function ($join) use ($shop) {
                $join->on('orders.id', '=', 'delivery_note_order.order_id')
                    ->where('orders.shop_id', '=', $shop->id)
                    ->whereNull('orders.deleted_at');
            })
            ->join('customers', function ($join) use ($countryCode) {
                $join->on('customers.id', '=', 'orders.customer_id')
                    ->whereRaw("customers.location->>0 = ?", [$countryCode]);
            })
            ->groupBy('shippers.id', 'shippers.slug', 'shippers.name')
            ->allowedSorts(['total_orders', 'total_amount', 'shippers.name'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?array $modelOperations = null, ?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix . 'Page');
            }

            $table
                ->withModelOperations($modelOperations)
                ->withLabelRecord([__('courier'), __('couriers')])
                ->withEmptyState([
                    'title'       => __('No couriers found'),
                    'description' => __('No orders with courier data recorded for this country.'),
                ])
                ->column(key: 'name', label: __('Courier'), sortable: true)
                ->column(key: 'total_orders', label: __('Orders'), sortable: true)
                ->column(key: 'total_amount', label: __('Total Amount'), sortable: true)
                ->column(key: 'total_net_amount', label: __('Net Amount'))
                ->column(key: 'avg_order_amount', label: __('Avg Order'))
                ->defaultSort('-total_orders');
        };
    }
}
