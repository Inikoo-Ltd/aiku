<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 29 Aug 2026 16:30:00 Central European Summer Time, Bratislava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\OrgAction;
use App\Actions\Traits\Dashboards\WithMarketingPeriod;
use App\Enums\Ordering\Order\OrderPayStatusEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexOrderChannels extends OrgAction
{
    use WithMarketingPeriod;

    protected Shop $shop;

    /**
     * One row per platform + sales channel, so Manual splits into Web / API / phone while
     * marketplace platforms keep one row each. Held counts orders sitting at submitted+unpaid
     * right now regardless of the period: it is an ops number, not a period metric.
     */
    public function handle(Shop $shop): array
    {
        $notCancelled = "orders.state NOT IN ('".OrderStateEnum::CANCELLED->value."', '".OrderStateEnum::CREATING->value."')";
        $held         = "orders.state = '".OrderStateEnum::SUBMITTED->value."' AND orders.pay_status = '".OrderPayStatusEnum::UNPAID->value."'";

        $rows = Order::query()
            ->leftJoin('platforms', 'orders.platform_id', 'platforms.id')
            ->leftJoin('sales_channels', 'orders.sales_channel_id', 'sales_channels.id')
            ->where('orders.shop_id', $shop->id)
            ->whereNull('orders.deleted_at')
            ->when($this->periodFrom, fn ($q) => $q->where('orders.date', '>=', $this->periodFrom))
            ->when($this->periodTo, fn ($q) => $q->where('orders.date', '<=', $this->periodTo))
            ->select([
                'platforms.type as platform_type',
                'platforms.name as platform_name',
                'sales_channels.type as sales_channel_type',
                'sales_channels.name as sales_channel_name',
                DB::raw("COUNT(*) FILTER (WHERE $notCancelled) as number_orders"),
                DB::raw("COUNT(*) FILTER (WHERE $held) as number_held_unpaid"),
                DB::raw("COALESCE(SUM(orders.total_amount) FILTER (WHERE $notCancelled), 0) as total_sales"),
                DB::raw('MAX(orders.date) as last_order_at'),
            ])
            ->groupBy('platforms.type', 'platforms.name', 'sales_channels.type', 'sales_channels.name')
            ->orderByDesc('total_sales')
            ->get();

        return [
            'currency_code' => $shop->currency->code,
            'orders_route'  => [
                'name'       => 'grp.org.shops.show.ordering.orders.index',
                'parameters' => ['organisation' => $shop->organisation->slug, 'shop' => $shop->slug],
            ],
            'period_label'  => $this->periodLabels()['period_label'],
            'period_from'   => $this->periodFrom?->toDateString(),
            'period_to'     => $this->periodTo?->toDateString(),
            'rows'          => $rows->map(fn ($row) => [
                'platform_type'      => $row->platform_type ?? 'none',
                'platform_name'      => $row->platform_name ?? __('Shop'),
                'sales_channel_type' => $row->sales_channel_type ?? 'na',
                'sales_channel_name' => $row->sales_channel_name ?? __('Unknown'),
                'number_orders'      => (int) $row->number_orders,
                'number_held_unpaid' => (int) $row->number_held_unpaid,
                'total_sales'        => (float) $row->total_sales,
                'last_order_at'      => $row->last_order_at,
            ])->values()->all(),
        ];
    }

    public function inShop(Organisation $organisation, Shop $shop, ActionRequest $request): array
    {
        $this->shop = $shop;
        $this->initialisation($organisation, $request);
        $this->setMarketingPeriod($request->user()->settings);

        return $this->handle($shop);
    }

    public function htmlResponse(array $orderChannels, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Ordering/OrderChannels',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Order Channels'),
                'pageHead'    => [
                    'icon'  => ['fal', 'fa-code-branch'],
                    'title' => __('Order Channels'),
                ],
                'data'        => $orderChannels,
                'intervals'   => $this->intervalsProp($request->user()->settings),
                'settings'    => [],
            ]
        );
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            (new ShowShop())->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.ordering.channels.index',
                            'parameters' => $routeParameters
                        ],
                        'label' => __('Order Channels'),
                        'icon'  => 'fal fa-bars',
                    ],
                ],
            ]
        );
    }
}
