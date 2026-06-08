<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Thu, 18 May 2023 14:27:38 Central European Summer, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Ordering\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Ordering\WithOrderingAuthorisation;
use App\Enums\UI\Ordering\OrdersTabsEnum;
use App\Http\Resources\Catalogue\ShopResource;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowOrderingDashboard extends OrgAction
{
    use WithOrderingAuthorisation;

    public function handle(Shop $shop): Shop
    {
        return $shop;
    }



    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Shop
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    public function htmlResponse(Shop $shop, ActionRequest $request): Response
    {
        $excessOrderCount = DB::table('orders')
            ->whereColumn('payment_amount', '>', 'total_amount')
            ->where('shop_id', $shop->id)
            ->whereNull('deleted_at')
            ->count();

        $avgOrderValue = round((float) (DB::table('orders')
            ->where('shop_id', $shop->id)
            ->whereNotIn('state', ['cancelled'])
            ->whereNull('deleted_at')
            ->avg('total_amount') ?? 0), 2);

        $avgOrderWeight = (int) round((float) (DB::table('delivery_notes')
            ->where('shop_id', $shop->id)
            ->whereBetween('effective_weight', [1, 50000])
            ->whereNull('deleted_at')
            ->avg('effective_weight') ?? 0));

        $topCourier = DB::table('delivery_notes as dn')
            ->join('model_has_shipments as mhs', function ($join) {
                $join->on('mhs.model_id', '=', 'dn.id')
                    ->where('mhs.model_type', '=', 'DeliveryNote');
            })
            ->join('shipments as s', 's.id', '=', 'mhs.shipment_id')
            ->join('shippers as sh', 'sh.id', '=', 's.shipper_id')
            ->join('delivery_note_order as dno', 'dno.delivery_note_id', '=', 'dn.id')
            ->join('orders as o', function ($join) use ($shop) {
                $join->on('o.id', '=', 'dno.order_id')
                    ->where('o.shop_id', '=', $shop->id)
                    ->whereNull('o.deleted_at');
            })
            ->whereNull('dn.deleted_at')
            ->whereNull('s.deleted_at')
            ->whereNull('sh.deleted_at')
            ->selectRaw('sh.id, sh.name, COUNT(DISTINCT o.id) as count')
            ->groupBy('sh.id', 'sh.name')
            ->orderByDesc('count')
            ->first();

        $topCountry = DB::table('orders as o')
            ->join('countries as c', 'c.id', '=', 'o.delivery_country_id')
            ->where('o.shop_id', $shop->id)
            ->whereNull('o.deleted_at')
            ->selectRaw('c.name, COUNT(*) as count')
            ->groupBy('c.name')
            ->orderByDesc('count')
            ->first();

        $avgDispatchHours = round((float) (DB::table('orders')
            ->where('shop_id', $shop->id)
            ->whereNull('deleted_at')
            ->whereNotNull('dispatched_at')
            ->whereNotNull('date')
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (dispatched_at - date)) / 3600) as avg_hours')
            ->value('avg_hours') ?? 0), 1);

        return Inertia::render(
            'Org/Ordering/OrderingDashboard',
            [
                'title'       => __('ordering dashboard'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($shop, $request),
                    'next'     => $this->getNext($shop, $request),
                ],
                'pageHead'    => [
                    'title' => __('Ordering'),
                    'model' => '',
                    'icon'  => [
                        'title' => __('Ordering'),
                        'icon'  => 'fal fa-chart-network'
                    ],
                ],
                'stats'       => [
                    [
                        'label' => __('Avg Order Value'),
                        'icon'  => 'fal fa-coin',
                        'color' => '#10b981',
                        'value' => $avgOrderValue,
                    ],
                    [
                        'label' => __('Avg Order Weight (g)'),
                        'icon'  => 'fal fa-weight-hanging',
                        'color' => '#6366f1',
                        'value' => $avgOrderWeight,
                    ],
                    [
                        'label' => __('Avg Dispatch Time (hrs)'),
                        'icon'  => 'fal fa-clock',
                        'color' => '#8b5cf6',
                        'value' => $avgDispatchHours,
                    ],
                    [
                        'label'    => __('Top Courier Used'),
                        'icon'     => 'fal fa-truck',
                        'color'    => '#f59e0b',
                        'value'    => $topCourier?->count ?? 0,
                        'subtitle' => $topCourier?->name,
                        'route'    => [
                            'name'       => 'grp.org.shops.show.ordering.couriers.index',
                            'parameters' => [
                                'organisation' => $shop->organisation->slug,
                                'shop'         => $shop->slug,
                            ],
                        ],
                    ],
                    [
                        'label'    => __('Top Country Dispatched'),
                        'icon'     => 'fal fa-globe',
                        'color'    => '#3b82f6',
                        'value'    => $topCountry?->count ?? 0,
                        'subtitle' => $topCountry?->name,
                        'route'    => [
                            'name'       => 'grp.org.shops.show.ordering.countries.index',
                            'parameters' => [
                                'organisation' => $shop->organisation->slug,
                                'shop'         => $shop->slug,
                            ],
                        ],
                    ],
                    [
                        'label'           => __('Orders Excesses Payment'),
                        'is_negative'     => true,
                        'route'           => [
                            'name'       => 'grp.org.shops.show.ordering.orders.index',
                            'parameters' => [
                                'organisation' => $shop->organisation->slug,
                                'shop'         => $shop->slug,
                                'tab'          => OrdersTabsEnum::EXCESS_ORDERS->value,
                            ]
                        ],
                        'icon'            => 'fal fa-shopping-cart',
                        "backgroundColor" => "#ff000011",
                        'value'           => $excessOrderCount,
                    ],
                ],


            ]
        );
    }


    public function jsonResponse(Shop $shop): ShopResource
    {
        return new ShopResource($shop);
    }


    public function getPrevious(Shop $shop, ActionRequest $request): ?array
    {
        $previous = Shop::where('code', '<', $shop->code)->where('organisation_id', $this->organisation->id)->orderBy('code', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(Shop $shop, ActionRequest $request): ?array
    {
        $next = Shop::where('code', '>', $shop->code)->where('organisation_id', $this->organisation->id)->orderBy('code')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?Shop $shop, string $routeName): ?array
    {
        if (!$shop) {
            return null;
        }

        return match ($routeName) {
            'grp.org.shops.show.ordering.dashboard' => [
                'label' => $shop->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $this->organisation->slug,
                        'shop'         => $shop->slug
                    ]

                ]
            ]
        };
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return
            array_merge(
                ShowShop::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.shops.show.ordering.dashboard',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Ordering'),
                        ]
                    ]
                ]
            );
    }
}
