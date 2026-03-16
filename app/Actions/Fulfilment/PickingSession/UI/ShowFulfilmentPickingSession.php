<?php

namespace App\Actions\Fulfilment\PickingSession\UI;

use App\Actions\Inventory\Warehouse\UI\ShowWarehouse;
use App\Actions\OrgAction;
use App\Actions\UI\WithInertia;
use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Enums\UI\Dispatch\PickingSessionTabsEnum;
use App\Http\Resources\Dispatching\PickingSessionResource;
use App\Http\Resources\Fulfilment\PalletReturnItemsUIResource;
use App\Http\Resources\Fulfilment\PalletReturnItemsWithStoredItemsResource;
use App\Models\Inventory\PickingSession;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Actions\Fulfilment\PickingSession\AutoFinishPickingFulfilmentPickingSession;
use App\Actions\Fulfilment\PickingSession\AutoFinishPackingFulfilmentPickingSession;
use App\Http\Resources\Fulfilment\FulfilmentPickingSessionPalletReturnsGroupedResource;

class ShowFulfilmentPickingSession extends OrgAction
{
    use AsAction;
    use WithInertia;

    public function handle(PickingSession $pickingSession): PickingSession
    {
        (new AutoFinishPickingFulfilmentPickingSession())->action($pickingSession);
        (new AutoFinishPackingFulfilmentPickingSession())->action($pickingSession);

        return $pickingSession->fresh();
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, PickingSession $pickingSession, ActionRequest $request): PickingSession
    {
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(PickingSessionTabsEnum::values());
        return $this->handle($pickingSession);
    }

    public function getTimeline(PickingSession $pickingSession): array
    {
        $timeline = [];
        foreach (PickingSessionStateEnum::cases() as $case) {
            $timestamp = $pickingSession->{$case->snake().'_at'} ?? null;

            if ($case == PickingSessionStateEnum::HANDLING) {
                $timestamp = $pickingSession->start_at;
            }

            $label = $case->labels()[$case->value];
            if ($pickingSession->user) {
                $label .= ' (' . $pickingSession->user->contact_name . ')';
            }

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
        $title      = __('Fulfilment Picking Session');
        $actions    = null;
        $navigation = PickingSessionTabsEnum::navigationExcept([
            PickingSessionTabsEnum::ITEMIZED,
        ]);

        if (array_key_exists(PickingSessionTabsEnum::GROUPED->value, $navigation)) {
            $navigation[PickingSessionTabsEnum::GROUPED->value]['title'] = __('Group by Pallet return');
        }

        if ($pickingSession->state === PickingSessionStateEnum::IN_PROCESS) {
            unset($navigation[PickingSessionTabsEnum::GROUPED->value]);
        }

        if (in_array($pickingSession->state, [PickingSessionStateEnum::PICKING_FINISHED, PickingSessionStateEnum::PACKING_FINISHED], true)) {
            unset($navigation[PickingSessionTabsEnum::ITEMS->value]);
        }

        if (!request()->input('tab')) {
            if (in_array($pickingSession->state, [PickingSessionStateEnum::PICKING_FINISHED, PickingSessionStateEnum::PACKING_FINISHED], true)) {
                $this->tab = PickingSessionTabsEnum::GROUPED->value;
            } else {
                $this->tab = PickingSessionTabsEnum::ITEMS->value;
            }
        }

        if ($pickingSession->state == PickingSessionStateEnum::IN_PROCESS) {
            $actions[] = [
                'type'    => 'button',
                'style'   => 'save',
                'tooltip' => __('Start picking'),
                'label'   => __('Start Picking'),
                'key'     => 'action',
                'route'   => [
                    'method'     => 'patch',
                    'name'       => 'grp.models.warehouse.fulfilment_picking_session.start_picking',
                    'parameters' => [
                        'warehouse'      => $pickingSession->warehouse->id,
                        'pickingSession' => $pickingSession->id
                    ]
                ]
            ];
        }

        $props = [
            'title'       => $title,
            'breadcrumbs' => $this->getBreadcrumbs(
                $pickingSession,
                $request->route()->getName(),
                $request->route()->originalParameters()
            ),
            'navigation'  => $navigation,
            'pageHead'    => [
                'title'      => $pickingSession->reference,
                'model'      => $title,
                'icon'       => [
                    'icon'  => 'fal fa-truck-loading',
                    'title' => $title
                ],
                'afterTitle' => [
                    'label' => $pickingSession->state->labels()[$pickingSession->state->value],
                ],
                'actions'    => $actions,
            ],
            'timelines'   => $this->getTimeline($pickingSession),
            'tabs'        => [
                'current'    => $this->tab,
                'navigation' => $navigation,
            ],
            'data'        => PickingSessionResource::make($pickingSession),
            'returnType'  => $this->getPalletReturnType($pickingSession),
            'dispatchableReturns' => $this->getDispatchableReturns($pickingSession),
        ];

        $props = array_merge($props, $this->getItems($pickingSession));

        $inertiaResponse = Inertia::render(
            'Org/Inventory/PickingSession', // Reusing the same Vue component if possible
            $props
        );

        $returnType = $this->getPalletReturnType($pickingSession);

        if ($returnType === PalletReturnTypeEnum::STORED_ITEM->value) {
            $inertiaResponse->table(
                IndexFulfilmentPickingSessionStoredItems::make()
                    ->tableStructure(pickingSession: $pickingSession, prefix: PickingSessionTabsEnum::ITEMS->value)
            );
        } else {
            if (in_array($pickingSession->state, [PickingSessionStateEnum::HANDLING, PickingSessionStateEnum::PICKING_FINISHED, PickingSessionStateEnum::PACKING_FINISHED], true)) {
                $inertiaResponse->table(
                    IndexFulfilmentPickingSessionPalletItemsGrouped::make()
                        ->tableStructure(pickingSession: $pickingSession, prefix: PickingSessionTabsEnum::GROUPED->value)
                );
            }

            $inertiaResponse->table(
                IndexFulfilmentPickingSessionPalletItems::make()
                    ->tableStructure(pickingSession: $pickingSession, prefix: PickingSessionTabsEnum::ITEMS->value)
            );
        }

        return $inertiaResponse;
    }

    public function getItems(PickingSession $pickingSession): array
    {
        $returnType = $this->getPalletReturnType($pickingSession);

        if ($returnType === PalletReturnTypeEnum::STORED_ITEM->value) {
            return [
                PickingSessionTabsEnum::ITEMS->value => $this->tab == PickingSessionTabsEnum::ITEMS->value
                    ? fn () => PalletReturnItemsWithStoredItemsResource::collection(IndexFulfilmentPickingSessionStoredItems::run($pickingSession, PickingSessionTabsEnum::ITEMS->value))
                    : Inertia::lazy(fn () => PalletReturnItemsWithStoredItemsResource::collection(IndexFulfilmentPickingSessionStoredItems::run($pickingSession, PickingSessionTabsEnum::ITEMS->value))),
            ];
        }

        if ($pickingSession->state === PickingSessionStateEnum::IN_PROCESS) {
            return [
                PickingSessionTabsEnum::ITEMS->value => $this->tab == PickingSessionTabsEnum::ITEMS->value
                    ? fn () => PalletReturnItemsUIResource::collection(IndexFulfilmentPickingSessionPalletItems::run($pickingSession, PickingSessionTabsEnum::ITEMS->value))
                    : Inertia::lazy(fn () => PalletReturnItemsUIResource::collection(IndexFulfilmentPickingSessionPalletItems::run($pickingSession, PickingSessionTabsEnum::ITEMS->value))),
            ];
        }

        if ($pickingSession->state === PickingSessionStateEnum::HANDLING) {
            return [
                PickingSessionTabsEnum::GROUPED->value => $this->tab == PickingSessionTabsEnum::GROUPED->value
                    ? fn () => FulfilmentPickingSessionPalletReturnsGroupedResource::collection(IndexFulfilmentPickingSessionPalletItemsGrouped::run($pickingSession, PickingSessionTabsEnum::GROUPED->value))
                    : Inertia::lazy(fn () => FulfilmentPickingSessionPalletReturnsGroupedResource::collection(IndexFulfilmentPickingSessionPalletItemsGrouped::run($pickingSession, PickingSessionTabsEnum::GROUPED->value))),
                PickingSessionTabsEnum::ITEMS->value   => $this->tab == PickingSessionTabsEnum::ITEMS->value
                    ? fn () => PalletReturnItemsUIResource::collection(IndexFulfilmentPickingSessionPalletItems::run($pickingSession, PickingSessionTabsEnum::ITEMS->value))
                    : Inertia::lazy(fn () => PalletReturnItemsUIResource::collection(IndexFulfilmentPickingSessionPalletItems::run($pickingSession, PickingSessionTabsEnum::ITEMS->value))),
            ];
        }

        if (in_array($pickingSession->state, [PickingSessionStateEnum::PICKING_FINISHED, PickingSessionStateEnum::PACKING_FINISHED], true)) {
            return [
                PickingSessionTabsEnum::GROUPED->value => $this->tab == PickingSessionTabsEnum::GROUPED->value
                    ? fn () => FulfilmentPickingSessionPalletReturnsGroupedResource::collection(IndexFulfilmentPickingSessionPalletItemsGrouped::run($pickingSession, PickingSessionTabsEnum::GROUPED->value))
                    : Inertia::lazy(fn () => FulfilmentPickingSessionPalletReturnsGroupedResource::collection(IndexFulfilmentPickingSessionPalletItemsGrouped::run($pickingSession, PickingSessionTabsEnum::GROUPED->value))),
            ];
        }

        return [
            PickingSessionTabsEnum::ITEMS->value => $this->tab == PickingSessionTabsEnum::ITEMS->value
                ? fn () => PalletReturnItemsUIResource::collection(IndexFulfilmentPickingSessionPalletItems::run($pickingSession, PickingSessionTabsEnum::ITEMS->value))
                : Inertia::lazy(fn () => PalletReturnItemsUIResource::collection(IndexFulfilmentPickingSessionPalletItems::run($pickingSession, PickingSessionTabsEnum::ITEMS->value))),
        ];
    }

    private function getPalletReturnType(PickingSession $pickingSession): string
    {
        $type = $pickingSession->palletReturns()
            ->toBase()
            ->selectRaw('distinct pallet_returns.type as type')
            ->pluck('type')
            ->first();

        if (!is_string($type) || $type === '') {
            return PalletReturnTypeEnum::PALLET->value;
        }

        return PalletReturnTypeEnum::tryFrom($type)?->value ?? PalletReturnTypeEnum::PALLET->value;
    }

    private function getDispatchableReturns(PickingSession $pickingSession): array
    {
        return $pickingSession->palletReturns()
            ->select([
                'pallet_returns.id',
                'pallet_returns.slug',
                'pallet_returns.reference',
                'pallet_returns.type',
                'pallet_returns.state',
                'pallet_returns.fulfilment_id',
                'pallet_returns.fulfilment_customer_id',
            ])
            ->get()
            ->map(function ($palletReturn) use ($pickingSession) {
                $showRouteName = $palletReturn->type?->value === PalletReturnTypeEnum::STORED_ITEM->value
                    ? 'grp.org.warehouses.show.dispatching.pallet-return-with-stored-items.show'
                    : 'grp.org.warehouses.show.dispatching.pallet-returns.show';

                return [
                    'id'        => $palletReturn->id,
                    'reference' => $palletReturn->reference,
                    'state'     => $palletReturn->state?->value,
                    'type'      => $palletReturn->type?->value,
                    'showRoute' => [
                        'name'       => $showRouteName,
                        'parameters' => [
                            'organisation' => $pickingSession->organisation->slug,
                            'warehouse'    => $pickingSession->warehouse->slug,
                            'palletReturn' => $palletReturn->slug,
                        ],
                        'method' => 'get',
                    ],
                    'dispatchRoute' => [
                        'name'       => 'grp.models.pallet-return.dispatch',
                        'parameters' => [
                            'palletReturn' => $palletReturn->id,
                        ],
                        'method' => 'post',
                    ],
                    'pickAllRoute' => [
                        'name'       => 'grp.models.fulfilment-customer.pallet-return.picked',
                        'parameters' => [
                            'organisation'       => $pickingSession->organisation->slug,
                            'fulfilment'         => $palletReturn->fulfilment->slug,
                            'fulfilmentCustomer' => $palletReturn->fulfilment_customer_id,
                            'palletReturn'       => $palletReturn->id,
                        ],
                        'method' => 'post',
                    ],
                    'cancelRoute' => [
                        'name'       => 'grp.models.pallet-return.cancel',
                        'parameters' => [
                            'palletReturn' => $palletReturn->id,
                        ],
                        'method' => 'patch',
                    ],
                    'canDispatch' => $palletReturn->state?->value === 'picked',
                ];
            })
            ->values()
            ->all();
    }

    public function getBreadcrumbs(PickingSession $pickingSession, string $routeName, array $routeParameters, string $suffix = ''): array
    {
        // Adapt breadcrumbs if needed, for now reuse warehouse structure
        return ShowWarehouse::make()->getBreadcrumbs(
            Arr::only($routeParameters, ['organisation', 'warehouse'])
        );
    }
}
