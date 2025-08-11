<?php

namespace App\Actions\Dispatching\PickingSession\UI;

use App\Actions\Dispatching\DeliveryNoteItem\UI\IndexDeliveryNoteItemsInPickingSession;
use App\Actions\Dispatching\DeliveryNoteItem\UI\IndexDeliveryNoteItemsInPickingSessionGrouped;
use App\Actions\Dispatching\DeliveryNoteItem\UI\IndexDeliveryNoteItemsInPickingSessionStateActive;
use App\Actions\Inventory\Warehouse\UI\ShowWarehouse;
use App\Actions\OrgAction;
use App\Actions\UI\WithInertia;
use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\Enums\UI\Dispatch\PickingSessionTabsEnum;
use App\Http\Resources\Dispatching\PickingSessionDeliveryNoteItemsGroupedResource;
use App\Http\Resources\Dispatching\PickingSessionDeliveryNoteItemsStateHandlingResource;
use App\Http\Resources\Dispatching\PickingSessionDeliveryNoteItemsStateUnassignedResource;
use App\Http\Resources\Dispatching\PickingSessionResource;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
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

    private Order|Shop|Warehouse|Customer $parent;

    public function handle(PickingSession $pickingSession): PickingSession
    {

        return $pickingSession;
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, PickingSession $pickingSession, ActionRequest $request): PickingSession
    {
        $this->parent = $warehouse;
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(PickingSessionTabsEnum::values());

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
        $navigation = PickingSessionTabsEnum::navigation();


        if(!request()->get('tab')){
            if ($pickingSession->state == PickingSessionStateEnum::IN_PROCESS) {
                $this->tab = PickingSessionTabsEnum::ITEMS->value;
            } elseif ($pickingSession->state == PickingSessionStateEnum::HANDLING) {
                $this->tab = PickingSessionTabsEnum::ITEMIZED->value;
            } else {
                $this->tab = PickingSessionTabsEnum::GROUPED->value;
            }
        }




        if ($pickingSession->state == PickingSessionStateEnum::IN_PROCESS) {
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
            unset($navigation[PickingSessionTabsEnum::ITEMIZED->value]);
            unset($navigation[PickingSessionTabsEnum::GROUPED->value]);
        } elseif ($pickingSession->state == PickingSessionStateEnum::PICKING_FINISHED || $pickingSession->state == PickingSessionStateEnum::PACKING_FINISHED) {
            unset($navigation[PickingSessionTabsEnum::ITEMIZED->value]);
            unset($navigation[PickingSessionTabsEnum::ITEMS->value]);
        } else {
            unset($navigation[PickingSessionTabsEnum::ITEMS->value]);
        }

        $props = [
            'title'       => $title,
            'breadcrumbs' => $this->getBreadcrumbs(
                $pickingSession,
                $request->route()->getName(),
                $request->route()->originalParameters()
            ),
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
                'navigation' => $navigation,
            ],
            'data' => PickingSessionResource::make($pickingSession)
        ];




        $props = array_merge($props, $this->getItems($pickingSession));

        $inertiaResponse = Inertia::render(
            'Org/Inventory/PickingSession',
            $props
        );
        if ($pickingSession->state == PickingSessionStateEnum::IN_PROCESS) {
            $inertiaResponse->table(IndexDeliveryNoteItemsInPickingSession::make()->tableStructure(parent: $pickingSession, prefix: PickingSessionTabsEnum::ITEMS->value));
        } else {
            $inertiaResponse->table(IndexDeliveryNoteItemsInPickingSessionGrouped::make()->tableStructure(parent: $pickingSession, prefix: PickingSessionTabsEnum::GROUPED->value))
                            ->table(IndexDeliveryNoteItemsInPickingSessionStateActive::make()->tableStructure(prefix: PickingSessionTabsEnum::ITEMIZED->value));
        }

        return $inertiaResponse;
    }

    public function getItems(PickingSession $pickingSession): array
    {
        if ($pickingSession->state == PickingSessionStateEnum::IN_PROCESS) {
            return [
                PickingSessionTabsEnum::ITEMS->value => $this->tab == PickingSessionTabsEnum::ITEMS->value ?
                    fn () => PickingSessionDeliveryNoteItemsStateUnassignedResource::collection(IndexDeliveryNoteItemsInPickingSession::run($pickingSession))
                    : Inertia::lazy(fn () => PickingSessionDeliveryNoteItemsStateUnassignedResource::collection(IndexDeliveryNoteItemsInPickingSession::run($pickingSession))),

            ];
        } else {
            return [
                PickingSessionTabsEnum::GROUPED->value => $this->tab == PickingSessionTabsEnum::GROUPED->value ?
                    fn () => PickingSessionDeliveryNoteItemsGroupedResource::collection(IndexDeliveryNoteItemsInPickingSessionGrouped::run($pickingSession))
                    : Inertia::lazy(fn () => PickingSessionDeliveryNoteItemsGroupedResource::collection(IndexDeliveryNoteItemsInPickingSessionGrouped::run($pickingSession))),
                PickingSessionTabsEnum::ITEMIZED->value => $this->tab == PickingSessionTabsEnum::ITEMIZED->value ?
                    fn () => PickingSessionDeliveryNoteItemsStateHandlingResource::collection(IndexDeliveryNoteItemsInPickingSessionStateActive::run($pickingSession))
                    : Inertia::lazy(fn () => PickingSessionDeliveryNoteItemsStateHandlingResource::collection(IndexDeliveryNoteItemsInPickingSessionStateActive::run($pickingSession))),

            ];
        }


    }

    public function getBreadcrumbs(PickingSession $pickingSession, string $routeName, array $routeParameters, string $suffix = ''): array
    {
        $headCrumb = function (PickingSession $pickingSession, array $routeParameters, string $suffix) {
            return [
                [

                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Picking Session')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $pickingSession->reference,
                        ],

                    ],
                    'suffix'         => $suffix

                ],
            ];
        };

        return match ($routeName) {
            'grp.org.warehouses.show.dispatching.picking_sessions.show',
            => array_merge(
                ShowWarehouse::make()->getBreadcrumbs(
                    Arr::only($routeParameters, ['organisation', 'warehouse'])
                ),
                $headCrumb(
                    $pickingSession,
                    [
                        'index' => [
                            'name'       => 'grp.org.warehouses.show.dispatching.picking_sessions.index',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'warehouse'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.warehouses.show.dispatching.picking_sessions.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'warehouse', 'pickingSession'])
                        ]
                    ],
                    $suffix
                ),
            ),
            default => []
        };
    }

}
