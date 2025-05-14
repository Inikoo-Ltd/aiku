<?php
/*
 * author Arya Permana - Kirin
 * created on 14-05-2025-11h-09m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\Shipper\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\Comms\Traits\WithAccountingSubNavigation;
use App\Actions\Inventory\Warehouse\UI\ShowWarehouse;
use App\Actions\OrgAction;
use App\Enums\UI\Dispatch\ShipperTabsEnum;
use App\Http\Resources\Accounting\TopUpsResource;
use App\Http\Resources\Dispatching\ShippersResource;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\TopUp;
use App\Models\Catalogue\Shop;
use App\Models\Dispatching\Shipper;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class ShowShipper extends OrgAction
{
    public function handle(Shipper $shipper): Shipper
    {
        return $shipper;
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, Shipper $shipper, ActionRequest $request): Shipper
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($shipper);
    }

    public function htmlResponse(Shipper $shipper, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Dispatching/Shipper',
            [
                'title'       => __('shipper'),
                'breadcrumbs' => $this->getBreadcrumbs($shipper, $request->route()->getName(), $request->route()->originalParameters()),
                'pageHead' => [
                    'title'     => $shipper->name,
                    'model'     => __('shipper'),
                    'icon'      => [
                        'icon'  => ['fal', 'fa-folder'],
                        'title' => __('shipper')
                    ],
                    // 'actions'    => [
                    //     [
                    //         'type'  => 'button',
                    //         'style' => 'create',
                    //         'label' => __('Create Order'),
                    //         'route' => [
                    //             'name'       => 'retina.models.customer-client.order.store',
                    //             'parameters' => [
                    //                 'customerClient' => $customerClient->id,
                    //                 'platform' => $customerClient->platform->id
                    //             ],
                    //             'method'     => 'post'
                    //         ]
                    //     ]
                    // ]
                ],
                'tabs'          => [
                    'current'    => $this->tab,
                    'navigation' => ShipperTabsEnum::navigation()

                ],

                ShipperTabsEnum::SHOWCASE->value => $this->tab == ShipperTabsEnum::SHOWCASE->value ?
                    fn () => GetShipperShowcase::run($shipper)
                    : Inertia::lazy(fn () => GetShipperShowcase::run($shipper)),
            ]
        );
    }

    public function getBreadcrumbs(Shipper $shipper, string $routeName, array $routeParameters, string $suffix = ''): array
    {
        $headCrumb = function (Shipper $shipper, array $routeParameters, string $suffix) {
            return [
                [

                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Shippers')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $shipper->name,
                        ],

                    ],
                    'suffix'         => $suffix

                ],
            ];
        };

        return match ($routeName) {
            'grp.org.warehouses.show.dispatching.shippers.show',
            => array_merge(
                ShowWarehouse::make()->getBreadcrumbs(
                    Arr::only($routeParameters, ['organisation', 'warehouse'])
                ),
                $headCrumb(
                    $shipper,
                    [
                        'index' => [
                            'name'       => 'grp.org.warehouses.show.dispatching.shippers.index',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'warehouse'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.warehouses.show.dispatching.shippers.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'warehouse', 'shipper'])
                        ]
                    ],
                    $suffix
                ),
            ),
            default => []
        };
    }
}
