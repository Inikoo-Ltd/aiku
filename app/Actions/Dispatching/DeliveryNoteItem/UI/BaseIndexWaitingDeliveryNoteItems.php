<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 Apr 2026 22:26:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNoteItem\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithDispatchingAuthorisation;
use App\Actions\UI\Dispatch\ShowDispatchHub;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\UI\Dispatch\WaitingItemsTabsEnum;
use App\Http\Resources\Dispatching\WaitingDeliveryNoteItemsGroupedByItemResource;
use App\Http\Resources\Dispatching\WaitingDNItemsGroupedByDeliveryNoteResource;
use App\Http\Resources\Dispatching\WaitingDNItemsTabsItemizedResource;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

abstract class BaseIndexWaitingDeliveryNoteItems extends OrgAction
{
    use WithDispatchingAuthorisation;

    protected string $shopType = 'all';

    protected string $waitingType = 'warehouse';

    protected bool $readOnly = false;

    abstract protected function getDeliveryNoteState(): DeliveryNoteStateEnum;

    abstract protected function getPageTitle(): string;

    abstract protected function getRouteName(): string;

    protected function getTabNavigation(): array
    {
        return WaitingItemsTabsEnum::navigation();
    }

    public function handle(Warehouse $warehouse): Warehouse
    {
        return $warehouse;
    }

    public function htmlResponse(Warehouse $warehouse, ActionRequest $request): Response
    {
        $groupedByDeliveryNote = IndexWaitingDeliveryNoteItemsGroupedByDeliveryNote::make()->handle(
            warehouse: $warehouse,
            waitingType: $this->waitingType,
            state: $this->getDeliveryNoteState(),
            shopType: $this->shopType,
            prefix: WaitingItemsTabsEnum::GROUPED_BY_DELIVERY_NOTE->value
        );

        $itemized = IndexWaitingDeliveryNoteItemsItemized::make()->handle(
            warehouse: $warehouse,
            waitingType: $this->waitingType,
            state: $this->getDeliveryNoteState(),
            shopType: $this->shopType,
            prefix: WaitingItemsTabsEnum::ITEMIZED->value
        );

        $groupedByItem = IndexWaitingDeliveryNoteItemsGroupedByItem::make()->handle(
            warehouse: $warehouse,
            waitingType: $this->waitingType,
            state: $this->getDeliveryNoteState(),
            shopType: $this->shopType,
            prefix: WaitingItemsTabsEnum::GROUPED_BY_ITEM->value
        );

        $props = [
            'breadcrumbs'                           => $this->getBreadcrumbs($request->route()->originalParameters()),
            'title'                                 => __('Waiting items').' '.$this->organisation->code,
            'pageHead'                              => [
                'title' => $this->getPageTitle(),
                'icon'  => [
                    'icon'  => ['fal', 'fa-hourglass-start'],
                    'title' => __('Waiting items'),
                ],
            ],
            'allow_stock_controller_set_not_picked' => (data_get($this->organisation->settings, 'orders.allow_stock_controller_set_not_picked', false)),
            'is_still_picking'                      => $this->getDeliveryNoteState()->value === DeliveryNoteStateEnum::HANDLING->value,
            'is_read_only'                          => $this->readOnly,
            'tabs'                                  => [
                'current'    => $this->tab,
                'navigation' => $this->getTabNavigation(),
            ],
            WaitingItemsTabsEnum::ITEMIZED->value                   => $this->tab == WaitingItemsTabsEnum::ITEMIZED->value
                ? fn () => WaitingDNItemsTabsItemizedResource::collection($itemized)
                : Inertia::lazy(fn () => WaitingDNItemsTabsItemizedResource::collection($itemized)),
            WaitingItemsTabsEnum::GROUPED_BY_DELIVERY_NOTE->value   => $this->tab == WaitingItemsTabsEnum::GROUPED_BY_DELIVERY_NOTE->value
                ? fn () => WaitingDNItemsGroupedByDeliveryNoteResource::collection($groupedByDeliveryNote)
                : Inertia::lazy(fn () => WaitingDNItemsGroupedByDeliveryNoteResource::collection($groupedByDeliveryNote)),
            WaitingItemsTabsEnum::GROUPED_BY_ITEM->value            => $this->tab == WaitingItemsTabsEnum::GROUPED_BY_ITEM->value
                ? fn () => WaitingDeliveryNoteItemsGroupedByItemResource::collection($groupedByItem)
                : Inertia::lazy(fn () => WaitingDeliveryNoteItemsGroupedByItemResource::collection($groupedByItem)),
        ];

        return Inertia::render('Org/Dispatching/WaitingDeliveryNoteItems', $props)
            ->table(IndexWaitingDeliveryNoteItemsItemized::make()->tableStructure(WaitingItemsTabsEnum::ITEMIZED->value, $this->readOnly))
            ->table(IndexWaitingDeliveryNoteItemsGroupedByDeliveryNote::make()->tableStructure(WaitingItemsTabsEnum::GROUPED_BY_DELIVERY_NOTE->value))
            ->table(IndexWaitingDeliveryNoteItemsGroupedByItem::make()->tableStructure(WaitingItemsTabsEnum::GROUPED_BY_ITEM->value));
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
                            'name'       => $this->getRouteName(),
                            'parameters' => $routeParameters,
                        ],
                        'label' => $this->getPageTitle(),
                        'icon'  => 'fal fa-bars',
                    ],
                ],
            ]
        );
    }
}
