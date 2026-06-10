<?php

namespace App\Actions\Dispatching\Shipper\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Ordering\WithOrderingAuthorisation;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Dispatching\Shipper;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexCouriersInShop extends OrgAction
{
    use WithOrderingAuthorisation;

    public function handle(Shop $shop, ?string $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $dateRange = request()->input('between.date');
        $fromDate  = null;
        $toDate    = null;

        if ($dateRange) {
            $parts = explode('-', $dateRange);
            if (count($parts) === 2) {
                $fromDate = Carbon::createFromFormat('Ymd', $parts[0])->startOfDay();
                $toDate   = Carbon::createFromFormat('Ymd', $parts[1])->endOfDay();
            }
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
            ->join('shipments', function ($join) {
                $join->on('shipments.shipper_id', '=', 'shippers.id')
                    ->whereNull('shipments.deleted_at');
            })
            ->join('model_has_shipments', function ($join) {
                $join->on('model_has_shipments.shipment_id', '=', 'shipments.id')
                    ->where('model_has_shipments.model_type', '=', 'DeliveryNote');
            })
            ->join('delivery_notes', function ($join) {
                $join->on('delivery_notes.id', '=', 'model_has_shipments.model_id')
                    ->whereNull('delivery_notes.deleted_at');
            })
            ->join('delivery_note_order', 'delivery_note_order.delivery_note_id', '=', 'delivery_notes.id')
            ->join('orders', function ($join) use ($shop) {
                $join->on('orders.id', '=', 'delivery_note_order.order_id')
                    ->where('orders.shop_id', '=', $shop->id)
                    ->whereNull('orders.deleted_at');
            })
            ->when($fromDate, fn ($q) => $q->where('orders.date', '>=', $fromDate))
            ->when($toDate, fn ($q) => $q->where('orders.date', '<=', $toDate))
            ->defaultSort('-total_orders')
            ->groupBy('shippers.id', 'shippers.slug', 'shippers.name')
            ->allowedSorts(['total_orders', 'total_amount', 'shippers.name'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix . 'Page');
            }

            $table
                ->withLabelRecord([__('courier'), __('couriers')])
                ->withEmptyState([
                    'title'       => __('No couriers found'),
                    'description' => __('No orders with courier data recorded for this shop.'),
                ])
                ->betweenDates(['date'])
                ->column(key: 'name', label: __('Courier'), sortable: true)
                ->column(key: 'total_orders', label: __('Orders'), sortable: true)
                ->column(key: 'total_amount', label: __('Total Amount'), sortable: true)
                ->column(key: 'total_net_amount', label: __('Net Amount'))
                ->column(key: 'avg_order_amount', label: __('Avg Order'))
                ->defaultSort('-total_orders');
        };
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    public function htmlResponse(LengthAwarePaginator $couriers, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Ordering/Couriers',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Couriers'),
                'pageHead'    => [
                    'title' => __('Couriers'),
                    'icon'  => [
                        'icon'  => ['fal', 'fa-shipping-fast'],
                        'title' => __('Couriers'),
                    ],
                ],
                'data' => $couriers,
            ]
        )->table($this->tableStructure());
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowShop::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.ordering.couriers.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Couriers'),
                        'icon'  => 'fal fa-bars',
                    ],
                ],
            ]
        );
    }
}
