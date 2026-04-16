<?php

namespace App\Actions\Fulfilment\PickingSession\UI;

use App\Actions\Inventory\Warehouse\UI\ShowWarehouse;
use App\Actions\OrgAction;
use App\Actions\UI\WithInertia;
use App\Actions\Fulfilment\PalletReturn\UI\GetPalletReturnBoxStats;
use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnItemStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
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
use App\Http\Resources\Fulfilment\FulfilmentPickingSessionStoredItemsGroupedResource;

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
            if (in_array($pickingSession->state, [PickingSessionStateEnum::PICKING_FINISHED, PickingSessionStateEnum::PACKING_FINISHED], true)) {
                $inertiaResponse->table(
                    IndexFulfilmentPickingSessionStoredItemsGrouped::make()
                        ->tableStructure(pickingSession: $pickingSession, prefix: PickingSessionTabsEnum::GROUPED->value)
                );
            }

            if (!in_array($pickingSession->state, [PickingSessionStateEnum::PICKING_FINISHED, PickingSessionStateEnum::PACKING_FINISHED], true)) {
                $inertiaResponse->table(
                    IndexFulfilmentPickingSessionStoredItems::make()
                        ->tableStructure(pickingSession: $pickingSession, prefix: PickingSessionTabsEnum::ITEMS->value)
                );
            }
        } else {
            if (in_array($pickingSession->state, [PickingSessionStateEnum::PICKING_FINISHED, PickingSessionStateEnum::PACKING_FINISHED], true)) {
                $inertiaResponse->table(
                    IndexFulfilmentPickingSessionPalletItemsGrouped::make()
                        ->tableStructure(pickingSession: $pickingSession, prefix: PickingSessionTabsEnum::GROUPED->value)
                );
            }

            if (!in_array($pickingSession->state, [PickingSessionStateEnum::PICKING_FINISHED, PickingSessionStateEnum::PACKING_FINISHED], true)) {
                $inertiaResponse->table(
                    IndexFulfilmentPickingSessionPalletItems::make()
                        ->tableStructure(pickingSession: $pickingSession, prefix: PickingSessionTabsEnum::ITEMS->value)
                );
            }
        }

        return $inertiaResponse;
    }

    public function getItems(PickingSession $pickingSession): array
    {
        $returnType = $this->getPalletReturnType($pickingSession);

        if ($returnType === PalletReturnTypeEnum::STORED_ITEM->value) {
            if ($pickingSession->state === PickingSessionStateEnum::IN_PROCESS) {
                return [
                    PickingSessionTabsEnum::ITEMS->value => $this->tab == PickingSessionTabsEnum::ITEMS->value
                        ? fn () => PalletReturnItemsWithStoredItemsResource::collection(IndexFulfilmentPickingSessionStoredItems::run($pickingSession, PickingSessionTabsEnum::ITEMS->value))
                        : Inertia::lazy(fn () => PalletReturnItemsWithStoredItemsResource::collection(IndexFulfilmentPickingSessionStoredItems::run($pickingSession, PickingSessionTabsEnum::ITEMS->value))),
                ];
            }

            if ($pickingSession->state === PickingSessionStateEnum::HANDLING) {
                return [
                    PickingSessionTabsEnum::GROUPED->value => $this->tab == PickingSessionTabsEnum::GROUPED->value
                        ? fn () => FulfilmentPickingSessionStoredItemsGroupedResource::collection(IndexFulfilmentPickingSessionStoredItemsGrouped::run($pickingSession, PickingSessionTabsEnum::GROUPED->value))
                        : Inertia::lazy(fn () => FulfilmentPickingSessionStoredItemsGroupedResource::collection(IndexFulfilmentPickingSessionStoredItemsGrouped::run($pickingSession, PickingSessionTabsEnum::GROUPED->value))),
                    PickingSessionTabsEnum::ITEMS->value   => $this->tab == PickingSessionTabsEnum::ITEMS->value
                        ? fn () => PalletReturnItemsWithStoredItemsResource::collection(IndexFulfilmentPickingSessionStoredItems::run($pickingSession, PickingSessionTabsEnum::ITEMS->value))
                        : Inertia::lazy(fn () => PalletReturnItemsWithStoredItemsResource::collection(IndexFulfilmentPickingSessionStoredItems::run($pickingSession, PickingSessionTabsEnum::ITEMS->value))),
                ];
            }

            if (in_array($pickingSession->state, [PickingSessionStateEnum::PICKING_FINISHED, PickingSessionStateEnum::PACKING_FINISHED], true)) {
                return [
                    PickingSessionTabsEnum::GROUPED->value => $this->tab == PickingSessionTabsEnum::GROUPED->value
                        ? fn () => FulfilmentPickingSessionStoredItemsGroupedResource::collection(IndexFulfilmentPickingSessionStoredItemsGrouped::run($pickingSession, PickingSessionTabsEnum::GROUPED->value))
                        : Inertia::lazy(fn () => FulfilmentPickingSessionStoredItemsGroupedResource::collection(IndexFulfilmentPickingSessionStoredItemsGrouped::run($pickingSession, PickingSessionTabsEnum::GROUPED->value))),
                ];
            }

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
                'pallet_returns.is_collection',
                'pallet_returns.picker_user_id',
                'pallet_returns.packer_user_id',
                'pallet_returns.platform_id',
                'pallet_returns.customer_sales_channel_id',
                'pallet_returns.delivery_address_id',
                'pallet_returns.parcels',
            ])
            ->with([
                'pickerUser:id,contact_name',
                'packerUser:id,contact_name',
                'platform:id,name',
                'customerSalesChannel:id,name',
                'fulfilmentCustomer.customer:id,contact_name',
                'deliveryAddress:id,address_line_1,address_line_2,locality,administrative_area,postal_code,country_id',
                'deliveryAddress.country:id,name',
                'shipments',
            ])
            ->withCount('items')
            ->get()
            ->map(function ($palletReturn) use ($pickingSession) {
                $baseQuery = $palletReturn->pallets()->whereNot('pallets.state', [PalletStateEnum::DISPATCHED]);
                $palletCount = (clone $baseQuery)->count();
                $completedPickingCount = (clone $baseQuery)
                    ->wherePivotIn('state', [
                        PalletReturnItemStateEnum::PICKED->value,
                        PalletReturnItemStateEnum::NOT_PICKED->value,
                        PalletReturnItemStateEnum::CANCEL->value,
                    ])
                    ->count();
                $canSetAsPicked = $palletCount > 0 && $palletCount === $completedPickingCount;

                $showRouteName = $palletReturn->type?->value === PalletReturnTypeEnum::STORED_ITEM->value
                    ? 'grp.org.warehouses.show.dispatching.pallet-return-with-stored-items.show'
                    : 'grp.org.warehouses.show.dispatching.pallet-returns.show';
                $boxStats = GetPalletReturnBoxStats::run(palletReturn: $palletReturn, parent: $palletReturn->fulfilmentCustomer);

                return [
                    'id'        => $palletReturn->id,
                    'reference' => $palletReturn->reference,
                    'state'     => $palletReturn->state?->value,
                    'type'      => $palletReturn->type?->value,
                    'stateIcon' => PalletReturnStateEnum::stateIcon()[$palletReturn->state->value] ?? null,
                    'stateLabel' => PalletReturnStateEnum::labels()[$palletReturn->state->value] ?? $palletReturn->state?->value,
                    'isCollection' => (bool) $palletReturn->is_collection,
                    'itemsCount' => (int) $palletReturn->items_count,
                    'picker' => $palletReturn->pickerUser ? [
                        'id' => $palletReturn->pickerUser->id,
                        'contact_name' => $palletReturn->pickerUser->contact_name,
                    ] : null,
                    'packer' => $palletReturn->packerUser ? [
                        'id' => $palletReturn->packerUser->id,
                        'contact_name' => $palletReturn->packerUser->contact_name,
                    ] : null,
                    'customer' => [
                        'name' => $palletReturn->fulfilmentCustomer?->customer?->contact_name,
                    ],
                    'platform' => $palletReturn->platform ? [
                        'name' => $palletReturn->platform->name,
                    ] : null,
                    'salesChannel' => $palletReturn->customerSalesChannel ? [
                        'name' => $palletReturn->customerSalesChannel->name,
                    ] : null,
                    'shippingAddress' => $palletReturn->deliveryAddress ? [
                        'address_line_1' => $palletReturn->deliveryAddress->address_line_1,
                        'address_line_2' => $palletReturn->deliveryAddress->address_line_2,
                        'locality' => $palletReturn->deliveryAddress->locality,
                        'administrative_area' => $palletReturn->deliveryAddress->administrative_area,
                        'postal_code' => $palletReturn->deliveryAddress->postal_code,
                        'country' => $palletReturn->deliveryAddress->country?->name,
                    ] : null,
                    'parcels' => $boxStats['parcels'] ?? [],
                    'shipments' => $boxStats['shipments'] ?? [],
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
                    'revertToPickingRoute' => [
                        'name'       => 'grp.models.pallet-return.revert-to-picking',
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
                    'pickerPackerRoutes' => [
                        'pickers_list' => [
                            'name'       => 'grp.json.employees.picker_users',
                            'parameters' => [
                                'organisation' => $pickingSession->organisation->slug,
                            ],
                        ],
                        'packers_list' => [
                            'name'       => 'grp.json.employees.packers',
                            'parameters' => [
                                'organisation' => $pickingSession->organisation->slug,
                            ],
                        ],
                        'update' => [
                            'name'       => 'grp.models.pallet-return.update',
                            'parameters' => [
                                'palletReturn' => $palletReturn->id,
                            ],
                            'method' => 'patch',
                        ],
                    ],
                    'updateRoute' => [
                        'name'       => 'grp.models.pallet-return.update',
                        'parameters' => [
                            'palletReturn' => $palletReturn->id,
                        ],
                        'method' => 'patch',
                    ],
                    'shipmentsRoutes' => [
                        'submit_route' => [
                            'name'       => 'grp.models.pallet-return.shipment_from_warehouse.store',
                            'parameters' => [
                                'palletReturn' => $palletReturn->id
                            ]
                        ],
                        'fetch_route' => [
                            'name'       => 'grp.json.shippers.index',
                            'parameters' => [
                                'organisation' => $pickingSession->organisation->slug,
                            ]
                        ],
                        'delete_route' => [
                            'name'       => 'grp.models.pallet-return.shipment.detach',
                            'parameters' => [
                                'palletReturn' => $palletReturn->id
                            ]
                        ],
                    ],
                    'canSetAsPicked' => $canSetAsPicked,
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
