<?php

/*
 * author Louis Perez
 * created on 06-10-2026
 * GitHub: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Billables\Leaflet\UI;

use App\Actions\Billables\Packaging\UI\ShowPackagings;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\OrgAction;
use App\Actions\Ordering\Order\UI\IndexOrders;
use App\Enums\UI\Catalogue\LeafletTabsEnum;
use App\Http\Resources\Sales\OrderResource;
use App\Http\Resources\History\HistoryResource;
use App\Actions\Traits\Authorisations\WithBillablesAuthorisation;
use App\Models\Billables\Leaflet;
use App\Models\Billables\Packaging;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowLeaflet extends OrgAction
{
    use WithBillablesAuthorisation;

    public function handle(Leaflet $leaflet): Leaflet
    {
        return $leaflet;
    }

    /**
     * @return array{orders: int, customers: int, last_used_at: string|null, attached: int, copies_printed: int, pending_print: int, last_printed_at: string|null}
     */
    public function stats(Leaflet $leaflet): array
    {
        $printed = DB::table('delivery_note_leaflets')
            ->join('model_has_leaflets', 'model_has_leaflets.id', '=', 'delivery_note_leaflets.model_has_leaflet_id')
            ->where('model_has_leaflets.leaflet_id', $leaflet->id)
            ->selectRaw('coalesce(sum(delivery_note_leaflets.copies) filter (where delivery_note_leaflets.printed_at is not null), 0) as copies_printed')
            ->selectRaw('count(*) filter (where delivery_note_leaflets.printed_at is null) as pending_print')
            ->selectRaw('max(delivery_note_leaflets.printed_at) as last_printed_at')
            ->first();

        $orders = DB::table('model_has_leaflets')
            ->join('orders', 'orders.id', '=', 'model_has_leaflets.model_id')
            ->where('model_has_leaflets.model_type', 'Order')
            ->where('model_has_leaflets.leaflet_id', $leaflet->id)
            ->whereNull('orders.deleted_at')
            ->selectRaw('count(distinct orders.id) as orders, count(distinct orders.customer_id) as customers, max(orders.date) as last_used_at')
            ->first();

        return [
            'orders'          => (int) $orders->orders,
            'customers'       => (int) $orders->customers,
            'last_used_at'    => $orders->last_used_at,
            'attached'        => DB::table('model_has_leaflets')->where('leaflet_id', $leaflet->id)->count(),
            'copies_printed'  => (int) $printed->copies_printed,
            'pending_print'   => (int) $printed->pending_print,
            'last_printed_at' => $printed->last_printed_at,
        ];
    }

    public function htmlResponse(Leaflet $leaflet, ActionRequest $request): Response
    {
        $routeParameters = $request->route()->originalParameters();
        $familyCodes     = $leaflet->family_codes ?? [];

        return Inertia::render(
            'Org/Billables/Leaflet',
            [
                'breadcrumbs'  => $this->getBreadcrumbs($leaflet, $routeParameters),
                'navigation'   => [
                    'previous' => $this->getSibling($leaflet, $routeParameters, '<'),
                    'next'     => $this->getSibling($leaflet, $routeParameters, '>'),
                ],
                'title'        => $leaflet->name,
                'pageHead'     => [
                    'title'   => $leaflet->name,
                    'model'   => __('Leaflet'),
                    'icon'    => [
                        'icon'  => ['fal', 'fa-file-alt'],
                        'title' => __('Leaflet'),
                    ],
                    'actions' => $this->canEdit ? [
                        [
                            'type'  => 'button',
                            'style' => 'edit',
                            'route' => [
                                'name'       => 'grp.org.shops.show.billables.leaflets.edit',
                                'parameters' => $routeParameters,
                            ],
                        ],
                    ] : [],
                ],
                'leaflet'      => [
                    'name'         => $leaflet->name,
                    'type'         => $leaflet->type->value,
                    'type_label'   => $leaflet->type->labels()[$leaflet->type->value] ?? $leaflet->type->value,
                    'price'        => (float) $leaflet->price,
                    'state'        => $leaflet->state->value,
                    'family_codes' => $familyCodes,
                    'created_at'   => $leaflet->created_at,
                    'updated_at'   => $leaflet->updated_at,
                ],
                'packagings'   => Packaging::where('shop_id', $leaflet->shop_id)
                    ->whereIn('family_code', $familyCodes ?: ['__none__'])
                    ->orderBy('code')
                    ->get()
                    ->map(fn (Packaging $packaging) => [
                        'code'  => $packaging->code,
                        'name'  => $packaging->name,
                        'state' => $packaging->state->value,
                        'route' => [
                            'name'       => 'grp.org.shops.show.billables.packagings.show',
                            'parameters' => [...Arr::only($routeParameters, ['organisation', 'shop']), 'packaging' => $packaging->slug],
                        ],
                    ])
                    ->values()
                    ->all(),
                'currencyCode' => $leaflet->shop->currency->code,
                'tabs'         => [
                    'current'    => $this->tab,
                    'navigation' => LeafletTabsEnum::navigation(),
                ],
                'stats'        => Inertia::defer(fn () => $this->stats($leaflet)),
                LeafletTabsEnum::ORDERS->value => $this->tab == LeafletTabsEnum::ORDERS->value
                    ? fn () => OrderResource::collection(IndexOrders::run(parent: $leaflet, prefix: LeafletTabsEnum::ORDERS->value))
                    : Inertia::optional(fn () => OrderResource::collection(IndexOrders::run(parent: $leaflet, prefix: LeafletTabsEnum::ORDERS->value))),
                LeafletTabsEnum::HISTORY->value => $this->tab == LeafletTabsEnum::HISTORY->value
                    ? fn () => HistoryResource::collection(IndexHistory::run($leaflet, LeafletTabsEnum::HISTORY->value))
                    : Inertia::optional(fn () => HistoryResource::collection(IndexHistory::run($leaflet, LeafletTabsEnum::HISTORY->value))),
            ]
        )->table(IndexOrders::make()->tableStructure(parent: $leaflet, prefix: LeafletTabsEnum::ORDERS->value))
            ->table(IndexHistory::make()->tableStructure(prefix: LeafletTabsEnum::HISTORY->value));
    }

    private function getSibling(Leaflet $leaflet, array $routeParameters, string $direction): ?array
    {
        $descending = $direction === '<';

        $sibling = Leaflet::where('shop_id', $leaflet->shop_id)
            ->whereRaw("(name, id) $direction (?, ?)", [$leaflet->name, $leaflet->id])
            ->orderBy('name', $descending ? 'desc' : 'asc')
            ->orderBy('id', $descending ? 'desc' : 'asc')
            ->first();

        if (!$sibling) {
            return null;
        }

        return [
            'label' => $sibling->name,
            'route' => [
                'name'       => 'grp.org.shops.show.billables.leaflets.show',
                'parameters' => [...Arr::only($routeParameters, ['organisation', 'shop']), 'leaflet' => $sibling->id],
            ],
        ];
    }

    public function asController(Organisation $organisation, Shop $shop, Leaflet $leaflet, ActionRequest $request): Leaflet
    {
        $this->initialisationFromShop($shop, $request)->withTab(LeafletTabsEnum::values());

        return $this->handle($leaflet);
    }

    public function getBreadcrumbs(Leaflet $leaflet, array $routeParameters): array
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
                            'name'       => 'grp.org.shops.show.billables.leaflets.show',
                            'parameters' => $routeParameters,
                        ],
                        'label' => $leaflet->name,
                    ],
                ],
            ]
        );
    }
}
