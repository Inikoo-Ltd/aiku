<?php

/*
 * author Louis Perez
 * created on 06-10-2026
 * GitHub: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Billables\Packaging\UI;

use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\OrgAction;
use App\Actions\Ordering\Order\UI\IndexOrders;
use App\Enums\UI\Catalogue\PackagingTabsEnum;
use App\Http\Resources\Sales\OrderResource;
use App\Http\Resources\History\HistoryResource;
use App\Actions\Traits\Authorisations\WithBillablesAuthorisation;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Http\Resources\Helpers\ImageResource;
use App\Models\Billables\Leaflet;
use App\Models\Billables\Packaging;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowPackaging extends OrgAction
{
    use WithBillablesAuthorisation;

    public function handle(Packaging $packaging): Packaging
    {
        return $packaging;
    }

    /**
     * @return array{orders: int, customers: int, default_for_customers: int, last_used_at: string|null, revenue: float}
     */
    public function stats(Packaging $packaging): array
    {
        $orders = DB::table('orders')
            ->where('packaging_id', $packaging->id)
            ->whereNotIn('state', [OrderStateEnum::CREATING->value, OrderStateEnum::CANCELLED->value])
            ->whereNull('deleted_at')
            ->selectRaw('count(*) as orders, count(distinct customer_id) as customers, max(date) as last_used_at')
            ->first();

        return [
            'orders'                => (int) $orders->orders,
            'customers'             => (int) $orders->customers,
            'last_used_at'          => $orders->last_used_at,
            'default_for_customers' => DB::table('customer_has_packagings')->where('packaging_id', $packaging->id)->count(),
            'revenue'               => round((int) $orders->orders * (float) $packaging->price, 2),
        ];
    }

    public function htmlResponse(Packaging $packaging, ActionRequest $request): Response
    {
        $routeParameters = $request->route()->originalParameters();

        return Inertia::render(
            'Org/Billables/Packaging',
            [
                'breadcrumbs'  => $this->getBreadcrumbs($packaging, $routeParameters),
                'navigation'   => [
                    'previous' => $this->getSibling($packaging, $routeParameters, '<'),
                    'next'     => $this->getSibling($packaging, $routeParameters, '>'),
                ],
                'title'        => $packaging->code,
                'pageHead'     => [
                    'title'   => $packaging->code,
                    'model'   => __('Packaging'),
                    'icon'    => [
                        'icon'  => ['fal', 'fa-box-open'],
                        'title' => __('Packaging'),
                    ],
                    'actions' => $this->canEdit ? [
                        [
                            'type'  => 'button',
                            'style' => 'edit',
                            'route' => [
                                'name'       => 'grp.org.shops.show.billables.packagings.edit',
                                'parameters' => $routeParameters,
                            ],
                        ],
                    ] : [],
                ],
                'packaging'    => [
                    'code'        => $packaging->code,
                    'name'        => $packaging->name,
                    'family_code' => $packaging->family_code,
                    'type'        => $packaging->type->value,
                    'type_label'  => $packaging->type->labels()[$packaging->type->value] ?? $packaging->type->value,
                    'price'       => (float) $packaging->price,
                    'width'       => $packaging->width,
                    'height'      => $packaging->height,
                    'depth'       => $packaging->depth,
                    'state'       => $packaging->state->value,
                    'image'       => $packaging->image ? ImageResource::make($packaging->image)->resolve() : null,
                    'created_at'  => $packaging->created_at,
                    'updated_at'  => $packaging->updated_at,
                ],
                'leaflets'     => Leaflet::where('shop_id', $packaging->shop_id)
                    ->whereRaw('jsonb_exists(family_codes, ?)', [$packaging->family_code])
                    ->orderBy('name')
                    ->get()
                    ->map(fn (Leaflet $leaflet) => [
                        'name'  => $leaflet->name,
                        'state' => $leaflet->state->value,
                        'route' => [
                            'name'       => 'grp.org.shops.show.billables.leaflets.show',
                            'parameters' => [...Arr::only($routeParameters, ['organisation', 'shop']), 'leaflet' => $leaflet->getRouteKey()],
                        ],
                    ])
                    ->values()
                    ->all(),
                'currencyCode' => $packaging->shop->currency->code,
                'tabs'         => [
                    'current'    => $this->tab,
                    'navigation' => PackagingTabsEnum::navigation(),
                ],
                'stats'        => Inertia::defer(fn () => $this->stats($packaging)),
                PackagingTabsEnum::ORDERS->value => $this->tab == PackagingTabsEnum::ORDERS->value
                    ? fn () => OrderResource::collection(IndexOrders::run(parent: $packaging, prefix: PackagingTabsEnum::ORDERS->value))
                    : Inertia::optional(fn () => OrderResource::collection(IndexOrders::run(parent: $packaging, prefix: PackagingTabsEnum::ORDERS->value))),
                PackagingTabsEnum::HISTORY->value => $this->tab == PackagingTabsEnum::HISTORY->value
                    ? fn () => HistoryResource::collection(IndexHistory::run($packaging, PackagingTabsEnum::HISTORY->value))
                    : Inertia::optional(fn () => HistoryResource::collection(IndexHistory::run($packaging, PackagingTabsEnum::HISTORY->value))),
            ]
        )->table(IndexOrders::make()->tableStructure(parent: $packaging, prefix: PackagingTabsEnum::ORDERS->value))
            ->table(IndexHistory::make()->tableStructure(prefix: PackagingTabsEnum::HISTORY->value));
    }

    private function getSibling(Packaging $packaging, array $routeParameters, string $direction): ?array
    {
        $sibling = Packaging::where('shop_id', $packaging->shop_id)
            ->where('code', $direction, $packaging->code)
            ->orderBy('code', $direction === '<' ? 'desc' : 'asc')
            ->first();

        if (!$sibling) {
            return null;
        }

        return [
            'label' => $sibling->code,
            'route' => [
                'name'       => 'grp.org.shops.show.billables.packagings.show',
                'parameters' => [...Arr::only($routeParameters, ['organisation', 'shop']), 'packaging' => $sibling->slug],
            ],
        ];
    }

    public function asController(Organisation $organisation, Shop $shop, Packaging $packaging, ActionRequest $request): Packaging
    {
        $this->initialisationFromShop($shop, $request)->withTab(PackagingTabsEnum::values());

        return $this->handle($packaging);
    }

    public function getBreadcrumbs(Packaging $packaging, array $routeParameters): array
    {
        return array_merge(
            ShowPackagings::make()->getBreadcrumbs(
                routeName: 'grp.org.shops.show.billables.packagings.index',
                routeParameters: Arr::only($routeParameters, ['organisation', 'shop']),
            ),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.billables.packagings.show',
                            'parameters' => $routeParameters,
                        ],
                        'label' => $packaging->code,
                    ],
                ],
            ]
        );
    }
}
