<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 13:48:15 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithDispatchingAuthorisation;
use App\Actions\UI\Dispatch\ShowDispatchHub;
use App\Enums\UI\DeliveryNotes\DeliveryNotesTabsEnum;
use App\Http\Resources\Dispatching\DeliveryNotesResource;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexDeliveryNotes extends OrgAction
{
    use WithDeliveryNotesSubNavigation;
    use WithDispatchingAuthorisation;
    use IsDeliveryNotesIndex;


    private string $shopType;

    public function htmlResponse(LengthAwarePaginator $deliveryNotes, ActionRequest $request): Response
    {
        $navigation = DeliveryNotesTabsEnum::navigation();
        if ($this->parent instanceof Group) {
            unset($navigation[DeliveryNotesTabsEnum::STATS->value]);
        }

        $subNavigation = null;
        if ($this->parent instanceof Warehouse) {
            $subNavigation = $this->getDeliveryNotesSubNavigation($this->shopType);
        }

        $title      = __('Delivery notes');
        $model      = '';
        $icon       = [
            'icon'  => ['fal', 'fa-truck'],
            'title' => $title
        ];
        $afterTitle = null;
        $iconRight  = null;
        $actions    = null;


        if ($this->parent instanceof Warehouse) {
            $icon      = ['fal', 'fa-arrow-from-left'];
            $iconRight = [
                'icon' => 'fal fa-truck',
            ];
            $model     = __('Goods Out');
        }

        $todo = $this->bucket == 'unassigned';

        return Inertia::render(
            'Org/Dispatching/DeliveryNotes',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters(),
                ),
                'title'       => $title,
                'pageHead'    => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'subNavigation' => $subNavigation,
                    'actions'       => $actions
                ],
                'data'        => DeliveryNotesResource::collection($deliveryNotes),
                "todo"        => $todo,
                'picking_session_route' => [
                    'name' => 'grp.models.warehouse.picking_session.store',
                    'parameters' => [
                        'warehouse' => $this->warehouse->id
                    ]
                ]
            ]
        )->table($this->tableStructure(parent: $this->parent, bucket: $this->bucket, shopType: $this->shopType));
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $warehouse;
        $this->bucket = 'all';
        $this->shopType = 'all';
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(DeliveryNotesTabsEnum::values());

        return $this->handle(parent: $warehouse, bucket: $this->bucket);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inShopTypes(Organisation $organisation, Warehouse $warehouse, string $shopType, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $warehouse;
        $this->bucket = 'all';
        $this->shopType = $shopType;
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(DeliveryNotesTabsEnum::values());

        return $this->handle(parent: $warehouse, bucket: $this->bucket, shopType: $shopType);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function unassigned(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $warehouse;
        $this->bucket = 'unassigned';
        $this->shopType = 'all';
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(DeliveryNotesTabsEnum::values());

        return $this->handle(parent: $warehouse, bucket: $this->bucket);
    }
    /** @noinspection PhpUnusedParameterInspection */
    public function unassignedShopTypes(Organisation $organisation, Warehouse $warehouse, string $shopType, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $warehouse;
        $this->bucket = 'unassigned';
        $this->shopType = $shopType;
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(DeliveryNotesTabsEnum::values());

        return $this->handle(parent: $warehouse, bucket: $this->bucket, shopType: $shopType);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function queued(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $warehouse;
        $this->bucket = 'queued';
        $this->shopType = 'all';
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(DeliveryNotesTabsEnum::values());

        return $this->handle(parent: $warehouse, bucket: $this->bucket);
    }
    /** @noinspection PhpUnusedParameterInspection */
    public function queuedShopTypes(Organisation $organisation, Warehouse $warehouse, string $shopType, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $warehouse;
        $this->bucket = 'queued';
        $this->shopType = $shopType;
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(DeliveryNotesTabsEnum::values());

        return $this->handle(parent: $warehouse, bucket: $this->bucket, shopType: $shopType);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function handling(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $warehouse;
        $this->bucket = 'handling';
        $this->shopType = 'all';
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(DeliveryNotesTabsEnum::values());

        return $this->handle(parent: $warehouse, bucket: $this->bucket);
    }
    /** @noinspection PhpUnusedParameterInspection */
    public function handlingShopTypes(Organisation $organisation, Warehouse $warehouse, string $shopType, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $warehouse;
        $this->bucket = 'handling';
        $this->shopType = $shopType;
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(DeliveryNotesTabsEnum::values());

        return $this->handle(parent: $warehouse, bucket: $this->bucket, shopType: $shopType);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function handlingBlocked(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $warehouse;
        $this->bucket = 'handling_blocked';
        $this->shopType = 'all';
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(DeliveryNotesTabsEnum::values());

        return $this->handle(parent: $warehouse, bucket: $this->bucket);
    }
    /** @noinspection PhpUnusedParameterInspection */
    public function handlingBlockedShopTypes(Organisation $organisation, Warehouse $warehouse, string $shopType, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $warehouse;
        $this->bucket = 'handling_blocked';
        $this->shopType = $shopType;
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(DeliveryNotesTabsEnum::values());

        return $this->handle(parent: $warehouse, bucket: $this->bucket, shopType: $shopType);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function packed(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $warehouse;
        $this->bucket = 'packed';
        $this->shopType = 'all';
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(DeliveryNotesTabsEnum::values());

        return $this->handle(parent: $warehouse, bucket: $this->bucket);
    }
    /** @noinspection PhpUnusedParameterInspection */
    public function packedShopTypes(Organisation $organisation, Warehouse $warehouse, string $shopType, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $warehouse;
        $this->bucket = 'packed';
        $this->shopType = $shopType;
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(DeliveryNotesTabsEnum::values());

        return $this->handle(parent: $warehouse, bucket: $this->bucket, shopType: $shopType);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function finalised(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $warehouse;
        $this->bucket = 'finalised';
        $this->shopType = 'all';
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(DeliveryNotesTabsEnum::values());

        return $this->handle(parent: $warehouse, bucket: $this->bucket);
    }
    /** @noinspection PhpUnusedParameterInspection */
    public function finalisedShopTypes(Organisation $organisation, Warehouse $warehouse, string $shopType, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $warehouse;
        $this->bucket = 'finalised';
        $this->shopType = $shopType;
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(DeliveryNotesTabsEnum::values());

        return $this->handle(parent: $warehouse, bucket: $this->bucket, shopType: $shopType);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function dispatched(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $warehouse;
        $this->bucket = 'dispatched';
        $this->shopType = 'all';
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(DeliveryNotesTabsEnum::values());

        return $this->handle(parent: $warehouse, bucket: $this->bucket);
    }
    /** @noinspection PhpUnusedParameterInspection */
    public function dispatchedShopTypes(Organisation $organisation, Warehouse $warehouse, string $shopType, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $warehouse;
        $this->bucket = 'dispatched';
        $this->shopType = $shopType;
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(DeliveryNotesTabsEnum::values());

        return $this->handle(parent: $warehouse, bucket: $this->bucket, shopType: $shopType);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function cancelled(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $warehouse;
        $this->bucket = 'cancelled';
        $this->shopType = 'all';
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(DeliveryNotesTabsEnum::values());

        return $this->handle(parent: $warehouse, bucket: $this->bucket);
    }


    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = [], ?string $suffix = null) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Delivery notes'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.warehouses.show.dispatching.delivery-notes',
            'grp.org.warehouses.show.dispatching.unassigned.delivery-notes',
            'grp.org.warehouses.show.dispatching.queued.delivery-notes',
            'grp.org.warehouses.show.dispatching.handling.delivery-notes',
            'grp.org.warehouses.show.dispatching.handling-blocked.delivery-notes',
            'grp.org.warehouses.show.dispatching.packed.delivery-notes',
            'grp.org.warehouses.show.dispatching.finalised.delivery-notes',
            'grp.org.warehouses.show.dispatching.dispatched.delivery-notes' =>
            array_merge(
                ShowDispatchHub::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.org.warehouses.show.dispatching.delivery-notes',
                        'parameters' => array_merge(
                            [
                                '_query' => [
                                    'elements[state]' => 'working'
                                ]
                            ],
                            $routeParameters
                        )
                    ]
                )
            ),
            'grp.org.warehouses.show.dispatching.delivery-notes.shop',
            'grp.org.warehouses.show.dispatching.unassigned.delivery-notes.shop',
            'grp.org.warehouses.show.dispatching.queued.delivery-notes.shop',
            'grp.org.warehouses.show.dispatching.handling.delivery-notes.shop',
            'grp.org.warehouses.show.dispatching.handling-blocked.delivery-notes.shop',
            'grp.org.warehouses.show.dispatching.packed.delivery-notes.shop',
            'grp.org.warehouses.show.dispatching.finalised.delivery-notes.shop',
            'grp.org.warehouses.show.dispatching.dispatched.delivery-notes.shop' =>
            array_merge(
                ShowDispatchHub::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.org.warehouses.show.dispatching.delivery-notes.shop',
                        'parameters' => array_merge(
                            [
                                '_query' => [
                                    'elements[state]' => 'working'
                                ]
                            ],
                            $routeParameters
                        )
                    ],
                    ' - ' . $routeParameters['shopType'] . ' (' . str_replace('_', ' ', $this->bucket) . ')'
                )
            ),
            'grp.org.shops.show.ordering.delivery-notes.index' =>
            array_merge(
                ShowShop::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.org.shops.show.ordering.delivery-notes.index',
                        'parameters' => $routeParameters
                    ]
                )
            ),
            default => []
        };
    }
}
