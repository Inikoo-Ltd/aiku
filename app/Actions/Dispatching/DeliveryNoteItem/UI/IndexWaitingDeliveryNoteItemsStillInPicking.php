<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 Apr 2026 19:52:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNoteItem\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithDispatchingAuthorisation;
use App\Actions\UI\Dispatch\ShowDispatchHub;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\UI\Dispatch\WaitingItemsTabsEnum;
use App\Http\Resources\Dispatching\WaitingDeliveryNoteItemsGroupedResource;
use App\Http\Resources\Dispatching\WaitingDeliveryNoteItemsResource;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexWaitingDeliveryNoteItemsStillInPicking extends OrgAction
{
    use WithDispatchingAuthorisation;

    private string $shopType;

    public function handle(Warehouse $warehouse): Warehouse
    {
        return $warehouse;
    }

    public function htmlResponse(Warehouse $warehouse, ActionRequest $request): Response
    {
        $grouped  = IndexWaitingDeliveryNoteItemsGrouped::make()->handle(
            warehouse: $warehouse,
            waitingType: 'warehouse',
            stateType: DeliveryNoteStateEnum::PACKING->value,
            shopType: $this->shopType,
            prefix: WaitingItemsTabsEnum::GROUPED->value
        );
        $itemized = IndexWaitingDeliveryNoteItemsItemized::make()->handle(
            warehouse: $warehouse,
            waitingType: 'warehouse',
            stateType: DeliveryNoteStateEnum::PACKING->value,
            shopType: $this->shopType,
            prefix: WaitingItemsTabsEnum::ITEMIZED->value
        );

        $props = [
            'breadcrumbs'                           => $this->getBreadcrumbs($request->route()->originalParameters()),
            'title'                                 => __('Waiting Items').' '.$this->organisation->code,
            'pageHead'                              => [
                'title' => __('Waiting Items').' ('.__('Still picking').')',
                'icon'  => [
                    'icon'  => ['fal', 'fa-hourglass-start'],
                    'title' => __('Waiting Items'),
                ],
            ],
            'allow_stock_controller_set_not_picked' => (data_get($this->organisation->settings, 'orders.allow_stock_controller_set_not_picked', false)),
            'tabs'                                  => [
                'current'    => $this->tab,
                'navigation' => WaitingItemsTabsEnum::navigation(),
            ],
            WaitingItemsTabsEnum::ITEMIZED->value   => $this->tab == WaitingItemsTabsEnum::ITEMIZED->value
                ? fn () => WaitingDeliveryNoteItemsResource::collection($itemized)
                : Inertia::lazy(fn () => WaitingDeliveryNoteItemsResource::collection($itemized)),
            WaitingItemsTabsEnum::GROUPED->value    => $this->tab == WaitingItemsTabsEnum::GROUPED->value
                ? fn () => WaitingDeliveryNoteItemsGroupedResource::collection($grouped)
                : Inertia::lazy(fn () => WaitingDeliveryNoteItemsGroupedResource::collection($grouped)),
        ];

        return Inertia::render('Org/Dispatching/WaitingDeliveryNoteItems', $props)
            ->table(IndexWaitingDeliveryNoteItemsItemized::make()->tableStructure(WaitingItemsTabsEnum::ITEMIZED->value))
            ->table(IndexWaitingDeliveryNoteItemsGrouped::make()->tableStructure(WaitingItemsTabsEnum::GROUPED->value));
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): Warehouse
    {
        $this->shopType = 'all';
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(WaitingItemsTabsEnum::values());

        return $this->handle($warehouse);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inShopTypes(Organisation $organisation, Warehouse $warehouse, string $shopType, ActionRequest $request): Warehouse
    {
        $this->shopType = $shopType;
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(WaitingItemsTabsEnum::values());

        return $this->handle($warehouse);
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowDispatchHub::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.dispatching.waiting_items_still_picking',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Waiting Items').' ('.__('Still picking').')',
                        'icon'  => 'fal fa-bars',
                    ],
                ],
            ]
        );
    }
}
