<?php

namespace App\Actions\Dispatching\PickingSession\UI;

use App\Actions\Dispatching\DeliveryNoteItem\UI\IndexDeliveryNoteItemsInPickingSession;
use App\Actions\OrgAction;
use App\Actions\UI\WithInertia;
use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\Enums\UI\Dispatch\DeliveryNoteTabsEnum;
use App\Enums\UI\Dispatch\PickingSessionTabsEnum;
use App\Http\Resources\Dispatching\PickingSessionDeliveryNoteItemsStateUnassignedResource;
use App\Http\Resources\Dispatching\PickingSessionResource;
use App\Http\Resources\Dispatching\PickingSessionsResource;
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

    public function asController(Organisation $organisation, Warehouse $warehouse, PickingSession $pickingSession, ActionRequest $request): PickingSession
    {
        $this->parent = $warehouse;
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(DeliveryNoteTabsEnum::values());

        return $this->handle($pickingSession);
    }

    public function getTimeline(PickingSession $pickingSession): array
    {
        $timeline = [];

        foreach (PickingSessionStateEnum::cases() as $case) {
            $timestamp = $pickingSession->{$case->snake().'_at'}
                ? $pickingSession->{$case->snake().'_at'}
                : null;

            $timestamp = $timestamp ?: null;

            $timestamp = match ($case) {
                PickingSessionStateEnum::HANDLING => $pickingSession->start_at,
                default => $timestamp ?: null
            };

            $label = $case->labels()[$case->value];
            $label .= ' ('.$pickingSession->user->contact_name.')';
            


            $timeline[$case->value] = [
                'label'       => $label,
                'tooltip'     => $case->labels()[$case->value],
                'key'         => $case->value,
                'format_time' => null,
                'timestamp'   => $timestamp
            ];
        }

        return $timeline;
    }

    public function htmlResponse(PickingSession $pickingSession, ActionRequest $request): Response
    {
        $title = __('Picking Session');


        $actions   = null;
        if($pickingSession->state == PickingSessionStateEnum::IN_PROCESS) {
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
        }

        $props = [
            'title'       => $title,
            'breadcrumbs' => null,
            'navigation'  => null,
            'pageHead'    => [
                'title'      => $pickingSession->reference,
                'model'      => $title,
                'icon'       => [
                    'icon'  => 'fal fa-truck',
                    'title' => $title
                ],
                'afterTitle' => [
                    'label' => $pickingSession->state->labels()[$pickingSession->state->value],
                ],
                'actions'    => $actions,
            ],
            'timelines' => $this->getTimeline($pickingSession),
            'tabs'        => [
                'current'    => $this->tab,
                'navigation' => PickingSessionTabsEnum::navigation()
            ],
            'data' => PickingSessionResource::make($pickingSession)
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
        if ($pickingSession->state == PickingSessionStateEnum::IN_PROCESS) {
            return [
                PickingSessionTabsEnum::ITEMS->value => $this->tab == PickingSessionTabsEnum::ITEMS->value ?
                    fn() => PickingSessionDeliveryNoteItemsStateUnassignedResource::collection(IndexDeliveryNoteItemsInPickingSession::run($pickingSession))
                    : Inertia::lazy(fn() => PickingSessionDeliveryNoteItemsStateUnassignedResource::collection(IndexDeliveryNoteItemsInPickingSession::run($pickingSession))),

            ];
        }

        return [];
    }
}
