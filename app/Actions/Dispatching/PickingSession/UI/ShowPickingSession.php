<?php

namespace App\Actions\Dispatching\PickingSession\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\CRM\Customer\UI\ShowCustomer;
use App\Actions\Dispatching\DeliveryNoteItem\UI\IndexDeliveryNoteItems;
use App\Actions\Dispatching\DeliveryNoteItem\UI\IndexDeliveryNoteItemsInPickingSession;
use App\Actions\Dispatching\DeliveryNoteItem\UI\IndexDeliveryNoteItemsInPickingSessionStateActive;
use App\Actions\Dispatching\DeliveryNoteItem\UI\IndexDeliveryNoteItemsInPickingSessionStateInProcess;
use App\Actions\Dispatching\DeliveryNoteItem\UI\IndexDeliveryNoteItemsStateHandling;
use App\Actions\Dispatching\DeliveryNoteItem\UI\IndexDeliveryNoteItemsStateUnassigned;
use App\Actions\Dispatching\Picking\Picker\Json\GetPickerUsers;
use App\Actions\Helpers\Country\UI\GetAddressData;
use App\Actions\Inventory\Warehouse\UI\ShowWarehouse;
use App\Actions\Ordering\Order\UI\ShowOrder;
use App\Actions\OrgAction;
use App\Actions\Retina\UI\Layout\GetPlatformLogo;
use App\Actions\UI\WithInertia;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\Enums\UI\Dispatch\DeliveryNoteTabsEnum;
use App\Enums\UI\Dispatch\PickingSessionTabsEnum;
use App\Http\Resources\CRM\CustomerResource;
use App\Http\Resources\Dispatching\DeliveryNoteItemsResource;
use App\Http\Resources\Dispatching\DeliveryNoteItemsStateHandlingResource;
use App\Http\Resources\Dispatching\DeliveryNoteItemsStateUnassignedResource;
use App\Http\Resources\Dispatching\DeliveryNoteResource;
use App\Http\Resources\Dispatching\ShipmentsResource;
use App\Http\Resources\Helpers\AddressResource;
use App\Http\Resources\Ordering\PickersResource;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Helpers\Address;
use App\Models\Inventory\PickingSession;
use App\Models\Inventory\Warehouse;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowPickingSession extends OrgAction
{
    use AsAction;
    use WithInertia;
    use GetPlatformLogo;

    private Order|Shop|Warehouse|Customer $parent;

    public function handle(PickingSession $pickingSession): PickingSession
    {
        return $pickingSession;
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(Organisation $organisation, Warehouse $warehouse, PickingSession $pickingSession, ActionRequest $request): PickingSession
    {
        $this->parent = $warehouse;
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(DeliveryNoteTabsEnum::values());

        return $this->handle($pickingSession);
    }

    public function getHandlingActions(PickingSession $pickingSession): array
    {
        $actions = [];
        return $actions;
    }

    public function getBoxStats(PickingSession $pickingSession): array
    {
        return [
            'state'            => $pickingSession->state,
        ];
    }

    public function getTimeline(PickingSession $pickingSession): array
    {
        $timeline = [];
        return $timeline;
    }

    public function htmlResponse(PickingSession $pickingSession, ActionRequest $request): Response
    {
        $actions = [];

        $props = [
            'title'         => __('picking session'),
            'breadcrumbs'   => [],
            'navigation'    => [],
            'pageHead'      => [
                'title'      => $pickingSession->reference,
                'model'      => __('Picking Session'),
                'icon'       => [
                    'icon'  => 'fal fa-truck',
                    'title' => __('picking session')
                ],
                'actions'    => $actions,
            ],
        ];


        $props = array_merge($props, $this->getItems($pickingSession));


        $inertiaResponse = Inertia::render(
            'Org/Inventory/PickingSession',
            $props
        );

        $inertiaResponse->table(IndexDeliveryNoteItemsInPickingSession::make()->tableStructure(parent: $pickingSession));
        


        return $inertiaResponse;
    }

    public function getItems(PickingSession $pickingSession): array
    {
        return [
            PickingSessionTabsEnum::ITEMS->value => $this->tab == PickingSessionTabsEnum::ITEMS->value ?
                fn () => DeliveryNoteItemsResource::collection(IndexDeliveryNoteItemsInPickingSession::run($pickingSession))
                : Inertia::lazy(fn () => DeliveryNoteItemsResource::collection(IndexDeliveryNoteItemsInPickingSession::run($pickingSession))),

        ];
    }
}
