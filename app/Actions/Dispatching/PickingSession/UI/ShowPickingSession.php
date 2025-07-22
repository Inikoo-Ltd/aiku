<?php

namespace App\Actions\Dispatching\PickingSession\UI;

use App\Actions\Dispatching\DeliveryNoteItem\UI\IndexDeliveryNoteItemsInPickingSession;
use App\Actions\OrgAction;
use App\Actions\UI\WithInertia;
use App\Enums\UI\Dispatch\DeliveryNoteTabsEnum;
use App\Enums\UI\Dispatch\PickingSessionTabsEnum;
use App\Http\Resources\Dispatching\DeliveryNoteItemsResource;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Inventory\PickingSession;
use App\Models\Inventory\Warehouse;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowPickingSession extends OrgAction
{
    use AsAction;
    use WithInertia;

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

    public function htmlResponse(PickingSession $pickingSession, ActionRequest $request): Response
    {
        $actions = null;
        $actions[] = [
               'type'    => 'button',
               'style'   => 'save',
               'tooltip' => __('start picking'),
               'label'   => __('Start Picking'),
               'key'     => 'action',
               'route'   => [
                   'method'     => 'patch',
                   'name'       => 'grp.models.picking_session.start_picking',
                   'parameters' => [
                       'pickingSession' => $pickingSession->id
                   ]
               ]
           ];
        // dd(IndexDeliveryNoteItemsInPickingSession::run($pickingSession, PickingSessionTabsEnum::ITEMS->value));
        // dd(DeliveryNoteItemsResource::collection(IndexDeliveryNoteItemsInPickingSession::run($pickingSession)));
        $props = [
            'title'         => __('picking session'),
            'breadcrumbs'   => null,
            'navigation'    => null,
            'pageHead'      => [
                'title'      => $pickingSession->reference,
                'model'      => __('Picking Session'),
                'icon'       => [
                    'icon'  => 'fal fa-truck',
                    'title' => __('picking session')
                ],
                'actions'    => $actions,
            ],
            'tabs'          => [
                'current'    => $this->tab,
                'navigation' => PickingSessionTabsEnum::navigation()
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
