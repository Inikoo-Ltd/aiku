<?php

/*
 * author Louis Perez
 * created on 28-04-2026-13h-56m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\GoodsIn\ReturnDeliveryNote\UI;

use App\Actions\Dispatching\Picking\Picker\Json\GetPickerUsers;
use App\Actions\GoodsIn\ReturnDeliveryNoteItem\IndexReturnDeliveryNoteItems;
use App\Actions\Helpers\Country\UI\GetAddressData;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\Inventory\Warehouse\UI\ShowWarehouse;
use App\Actions\OrgAction;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Actions\Retina\UI\Layout\GetPlatformLogo;
use App\Actions\UI\WithInertia;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\GoodsIn\ReturnDeliveryNote\ReturnDeliveryNoteStateEnum;
use App\Enums\GoodsIn\ReturnDeliveryNoteItem\ReturnDeliveryNoteItemStateEnum;
use App\Enums\UI\Dispatch\DeliveryNoteTabsEnum;
use App\Http\Resources\CRM\CustomerResource;
use App\Http\Resources\Dispatching\ShipmentsResource;
use App\Http\Resources\Helpers\AddressResource;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\Ordering\PickersResource;
use App\Http\Resources\Procurement\ReturnDeliveryNoteItemsResource;
use App\Http\Resources\Procurement\ReturnDeliveryNoteResource;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\GoodsIn\ReturnDeliveryNote;
use App\Models\GoodsIn\ReturnDeliveryNoteItem;
use App\Models\Helpers\Address;
use App\Models\Inventory\Warehouse;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowReturnDeliveryNote extends OrgAction
{
    use AsAction;
    use WithInertia;
    use GetPlatformLogo;

    private Order|Shop|Warehouse|Customer $parent;

    public function handle(ReturnDeliveryNote $returnDeliveryNote): ReturnDeliveryNote
    {
        return $returnDeliveryNote;
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inWarehouse(Organisation $organisation, Warehouse $warehouse, ReturnDeliveryNote $returnDeliveryNote, ActionRequest $request): ReturnDeliveryNote
    {
        $this->parent = $warehouse;
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(DeliveryNoteTabsEnum::values());

        return $this->handle($returnDeliveryNote);
    }

    public function getHandlingActions(ReturnDeliveryNote $returnDeliveryNote): array
    {
        $hasUnHandledItems = ReturnDeliveryNoteItem::where('return_delivery_note_id', $returnDeliveryNote->id)
            ->whereNotNull('processed_at')
            ->exists();

        $actions = [];
        if (!$hasUnHandledItems) {
            $actions[] = [
                'type' => 'button',
                'key'  => 'trigger-set-as-picked',
            ];
        }

        return $actions;
    }

    public function wrappedActions(ReturnDeliveryNote $returnDeliveryNote): array
    {
        $isEditable = false;
        if ($this->parent instanceof Warehouse) {
            $isEditable = true;
        }

        $showCancel = true;

        if (in_array($returnDeliveryNote->return_state, [
            ReturnDeliveryNoteStateEnum::CANCELLED,
            ReturnDeliveryNoteStateEnum::RETURNED
        ])) {
            $showCancel = false;
        }

        $actions = [];
        if ($showCancel && $isEditable) {
            $actions[] = [
                'type'  => 'button',
                'style' => 'cancel',
                'key'   => 'cancel',
                'label' => __('Cancel'),
                'route' => [
                    'method'        => 'patch',
                    'name'          => 'grp.models.return_delivery_note.state.cancel',
                    'parameters'    =>  [
                        'returnDeliveryNote'    => $returnDeliveryNote->id
                    ]
                ]
            ];
        }

        return $actions;
    }

    public function getActions(ReturnDeliveryNote $returnDeliveryNote, ActionRequest $request): array
    {
        $isEditable = false;
        if ($this->parent instanceof Warehouse) {
            $isEditable = true;
        }

        if (!$isEditable) {
            return [];
        }

        return match ($returnDeliveryNote->return_state) {
            ReturnDeliveryNoteStateEnum::RECEIVED => [
                [
                    'type'    => 'button',
                    'style'   => 'save',
                    'icon'    => 'fas fa-warehouse-alt',
                    'tooltip' => __('Process this Return'),
                    'label'   => __('Set as Returning'),
                    'key'     => 'start-picking',
                    'route'   => [
                        'method'        => 'patch',
                        'name'          => 'grp.models.return_delivery_note.state.returning',
                        'parameters'    =>  [
                            'returnDeliveryNote'    => $returnDeliveryNote->id
                        ]
                    ],
                ],
            ],
            default => []
        };
    }

    public function getBoxStats(ReturnDeliveryNote $returnDeliveryNote): array
    {
        $deliveryNote = $returnDeliveryNote->deliveryNote;
        $order     = $returnDeliveryNote->order;
        $estWeight = ($deliveryNote->estimated_weight ?? 0) / 1000;

        $additionalShipmentRoutes = [];

        // TODO LATER
        $trolleys = [];
        // foreach ($returnDeliveryNote->trolleys as $trolley) {
        //     $trolleys[] = [
        //         'id'   => $trolley->id,
        //         'slug' => $trolley->slug,
        //         'name' => $trolley->name,
        //     ];
        // }
        // TODO LATER
        $pickedBays = [];
        // foreach ($returnDeliveryNote->pickedBays as $pickedBay) {
        //     $pickedBays[] = [
        //         'id'   => $pickedBay->id,
        //         'slug' => $pickedBay->slug,
        //         'name' => $pickedBay->code,
        //     ];
        // }

        return [
            'state'                        => $returnDeliveryNote->return_state,
            'state_icon'                   => ReturnDeliveryNoteStateEnum::stateIcon()[$returnDeliveryNote->return_state->value],
            'state_label'                  => $returnDeliveryNote->return_state->labels()[$returnDeliveryNote->return_state->value],
            'customer'                     => array_merge(
                CustomerResource::make($returnDeliveryNote->customer)->getArray(),
                [
                    'addresses' => [
                        'delivery' => AddressResource::make($returnDeliveryNote->deliveryNote->deliveryAddress ?? new Address()),
                    ],
                    'route'     => [
                        'name'       => 'grp.org.shops.show.crm.customers.show',
                        'parameters' => [
                            'organisation' => $returnDeliveryNote->organisation->slug,
                            'shop'         => $returnDeliveryNote->shop->slug,
                            'customer'     => $returnDeliveryNote->customer->slug
                        ]
                    ]
                ]
            ),
            'customer_client'              => $returnDeliveryNote->deliveryNote->customerClient,
            'currency_code'                => $returnDeliveryNote->shop->currency->code,
            'platform'                     => [
                'name' => $deliveryNote->platform?->name,
                'logo' => $deliveryNote->customerSalesChannel?->platform?->code ? $this->getPlatformLogo($deliveryNote->customerSalesChannel->platform->code) : null,
            ],
            'products'                     => [
                'estimated_weight' => $estWeight,
                'number_items'     => $deliveryNote->number_items,
            ],
            'order'                        => [
                'reference' => $order->reference,
                'route'     => [
                    'name'       => 'grp.org.shops.show.ordering.orders.show',
                    'parameters' => [
                        'organisation' => $order->organisation->slug,
                        'shop'         => $order->shop->slug,
                        'order'        => $order->slug
                    ]
                ],
            ],
            'address'                      => [
                'delivery' => AddressResource::make($deliveryNote->deliveryAddress ?? new Address()),
                'options'  => [
                    'countriesAddressData' => GetAddressData::run()
                ]
            ],
            'delivery_address'             => AddressResource::make($deliveryNote->deliveryAddress),
            'picker'                       => $returnDeliveryNote->pickerUser,
            'packer'                       => $returnDeliveryNote->packerUser,
            'picked_bays'                  => $pickedBays,
            'trolleys'                     => $trolleys,
            'parcels'                      => $deliveryNote->parcels, // TODO IS IT NEEDED?
            'shipments'                    => $deliveryNote->shipments ? ShipmentsResource::collection($deliveryNote->shipments()->with('shipper')->get())->toArray(request()) : null, // TODO IS IT NEEDED?
            'shipments_routes'             => [
                ...$additionalShipmentRoutes,
                'fetch_route' => [
                    'name'       => 'grp.json.shippers.index',
                    'parameters' => [
                        'organisation' => $deliveryNote->organisation->slug,
                    ]
                ],

                'delete_route' => [
                    'name'       => 'grp.models.delivery_note.shipment.detach',
                    'parameters' => [
                        'deliveryNote' => $deliveryNote->id
                    ]
                ],
            ], // TODO IS IT NEEDED?
            'shop_type'                    => $returnDeliveryNote->shop->type,
            'shipping_fields'              => [
                'company_name' => $deliveryNote->company_name,
                'contact_name' => $deliveryNote->contact_name,
                'phone'        => $deliveryNote->phone,
                'email'        => $deliveryNote->email,
                'address'      => [
                    'delivery' => AddressResource::make($deliveryNote->deliveryAddress ?? new Address()),
                    'options'  => [
                        'countriesAddressData' => GetAddressData::run()
                    ]
                ]
            ],
            'shipping_fields_update_route' => [
                'name'       => 'grp.models.delivery_note.update_shipping_fields_retry_store_shipping',
                'parameters' => [
                    'deliveryNote' => $deliveryNote->id,
                    'shipper_id'   => null
                ]
            ],
        ];
    }

    public function getTimeline(ReturnDeliveryNote $returnDeliveryNote): array
    {
        $timeline = [];

        foreach (ReturnDeliveryNoteStateEnum::cases() as $case) {
            $timestamp = $returnDeliveryNote->{$case->snake().'_at'}
                ? $returnDeliveryNote->{$case->snake().'_at'}
                : null;

            $timestamp = $timestamp ?: null;

            $timestamp = match ($case) {
                ReturnDeliveryNoteStateEnum::RECEIVED => $returnDeliveryNote->created_at,
                default => $timestamp ?: null
            };

            $formatTime = 'PPp';


            $label = $case->labels()[$case->value];

            $timeline[$case->value] = [
                'label'       => $label,
                'tooltip'     => $case->labels()[$case->value],
                'key'         => $case->value,
                'format_time' => $formatTime,
                'timestamp'   => $timestamp
            ];
        }

        return $timeline;
    }

    public function quickGetPickers(): array
    {
        return PickersResource::collection(GetPickerUsers::run($this->organisation, true))->resolve();
    }

    public function htmlResponse(ReturnDeliveryNote $returnDeliveryNote, ActionRequest $request): Response
    {
        $isEditable = false;
        if ($this->parent instanceof Warehouse) {
            $isEditable = true;
        }

        $actions = $this->getActions($returnDeliveryNote, $request);

        $warning = null;

        // TODO Dunno, need to ask Raul Later
        // if ($deliveryNote->pickingSessions && $deliveryNote->pickingSessions->isNotEmpty()) {
        //     $pickingSessions = $deliveryNote->pickingSessions->map(function ($pickingSession) {
        //         return [
        //             'reference' => $pickingSession->reference,
        //             'route'     => [
        //                 'name'       => 'grp.org.warehouses.show.dispatching.picking_sessions.show',
        //                 'parameters' => [
        //                     'organisation'   => $pickingSession->organisation->slug,
        //                     'warehouse'      => $pickingSession->warehouse->slug,
        //                     'pickingSession' => $pickingSession->slug,
        //                 ],
        //             ],
        //         ];
        //     })->toArray();

        //     $warning = [
        //         'text'             => __('This DeliveryNote is being picked in Picking Sessions'),
        //         'picking_sessions' => $pickingSessions,
        //     ];
        // }

        $model = __('Returned Delivery Note');

        $allowAction = ($returnDeliveryNote->packer_user_id && $returnDeliveryNote->packer_user_id != request()->user()->id);

        if (!$allowAction && $tempPicker = session('temp_handling_delivery_note')) {
            $allowAction = $returnDeliveryNote->id == data_get($tempPicker, 'value') && now()->lt(data_get($tempPicker, 'expires_at'));
        }

        $showChangePickerPacker = $returnDeliveryNote->shop->type !== ShopTypeEnum::DROPSHIPPING;

        $returnDeliveryNote->returnDeliveryNoteItem;
        // $returnDeliveryNote->returnDeliveryNoteItem()->whereNull('processed_at');
        // $returnDeliveryNote->returnDeliveryNoteItem()->whereNotNull('processed_at');

        $props = [
            'title'         => __('Return Delivery note').' '.$returnDeliveryNote->reference,
            'breadcrumbs'   => $this->getBreadcrumbs(
                $returnDeliveryNote,
                $request->route()->getName(),
                $request->route()->originalParameters(),
            ),
            // 'navigation'    => [
            //     'previous' => $this->getPrevious($deliveryNote, $request),
            //     'next'     => $this->getNext($deliveryNote, $request),
            // ], // TODO
            'pageHead'      => [
                'title'           => $returnDeliveryNote->reference,
                'model'           => $model,
                'icon'            => [
                    'icon'  => 'fal fa-exchange',
                    'title' => __('Return Delivery note')
                ],
                'afterTitle'      => [
                    'label' => $returnDeliveryNote->return_state->labels()[$returnDeliveryNote->return_state->value],
                ],
                'actions'         => $actions,
                'wrapped_actions' => $this->wrappedActions($returnDeliveryNote),
            ],
            // 'warning'       => $warning,
            // 'isEditable'    => $isEditable,
            'tabs'          => [
                'current'    => $returnDeliveryNote->return_state !== ReturnDeliveryNoteStateEnum::RECEIVED ? DeliveryNoteTabsEnum::PENDING_ITEMS->value : $this->tab,
                'navigation' => $returnDeliveryNote->return_state !== ReturnDeliveryNoteStateEnum::RECEIVED
                    ?
                    DeliveryNoteTabsEnum::navigation($returnDeliveryNote)
                    :
                    DeliveryNoteTabsEnum::navigationExcept($returnDeliveryNote, [DeliveryNoteTabsEnum::DONE_ITEMS, DeliveryNoteTabsEnum::PENDING_ITEMS])
            ],
            'delivery_note' => ReturnDeliveryNoteResource::make($returnDeliveryNote)->toArray(request()),
            'address'             => [
                'delivery' => AddressResource::make($returnDeliveryNote->deliveryNote->deliveryAddress ?? new Address()),
                'options'  => [
                    'countriesAddressData' => GetAddressData::run()
                ]
            ],
            // 'allowActions'        => $allowAction,
            'timelines'           => $this->getTimeline($returnDeliveryNote),
            'box_stats'           => $this->getBoxStats($returnDeliveryNote),
            // 'shop_type'           => $returnDeliveryNote->shop->type,
            'notes'               => $this->getDeliveryNoteNotes($returnDeliveryNote),
            'quick_pickers'       => $this->quickGetPickers(),
            'routes'              => [
                // TODO ALL ROUTE, for now acts as a placeholder
                'update'                => [
                    'name'       => 'grp.models.delivery_note.update',
                    'parameters' => [
                        'deliveryNote' => $returnDeliveryNote->deliveryNote->id
                    ]
                ],
                'set_queue'             => [
                    'method'     => 'patch',
                    'name'       => 'grp.models.delivery_note.state.in_queue',
                    'parameters' => [
                        'deliveryNote' => $returnDeliveryNote->deliveryNote->id
                    ]
                ],
                'pickers_list'          => [
                    'name'       => 'grp.json.employees.picker_users',
                    'parameters' => [
                        'organisation' => $returnDeliveryNote->deliveryNote->organisation->slug
                    ]
                ],
                'packers_list'          => [
                    'name'       => 'grp.json.employees.packers',
                    'parameters' => [
                        'organisation' => $returnDeliveryNote->deliveryNote->organisation->slug
                    ]
                ],
                'exportPdfRoute'        => [
                    'name'       => 'grp.org.accounting.invoices.download',
                    'parameters' => [
                        'organisation' => $returnDeliveryNote->deliveryNote->organisation->slug,
                        'invoice'      => $returnDeliveryNote->deliveryNote->slug
                    ]
                ],
                'assignSelfTemporarily' => [
                    'name'       => 'grp.org.shops.show.ordering.orders.show.delivery-note.temp-picker',
                    'parameters' => [
                        'organisation' => $returnDeliveryNote->deliveryNote->organisation->slug,
                        'shop'         => $returnDeliveryNote->deliveryNote->shop->slug,
                        'deliveryNote' => $returnDeliveryNote->deliveryNote->slug,
                    ]
                ]
            ],
            'returned_delivery_note_state' => [
                'value' => $returnDeliveryNote->return_state,
                'label' => $returnDeliveryNote->return_state->labels()[$returnDeliveryNote->return_state->value],
            ],
            'warehouse'           => [
                'slug' => $returnDeliveryNote->warehouse->slug,
            ],

            'is_faire_order'                => ($returnDeliveryNote->shop->engine == ShopEngineEnum::FAIRE),
            'showChangePickerPacker'        => $showChangePickerPacker,
            'shop'                               => [
                'type' => $returnDeliveryNote->shop?->type?->value,
            ],
            'return_delivery_note' => [],
        ];


        $props = array_merge($props, $this->getItems($returnDeliveryNote));


        $inertiaResponse = Inertia::render(
            'Org/Dispatching/ReturnDN',
            $props
        );

        $inertiaResponse->table(IndexReturnDeliveryNoteItems::make()->tableStructure($returnDeliveryNote, DeliveryNoteTabsEnum::ITEMS->value));
        if ($returnDeliveryNote->return_state == ReturnDeliveryNoteStateEnum::RETURNING) {
            $inertiaResponse->table(IndexReturnDeliveryNoteItems::make()->tableStructure($returnDeliveryNote, DeliveryNoteTabsEnum::PENDING_ITEMS->value));
            $inertiaResponse->table(IndexReturnDeliveryNoteItems::make()->tableStructure($returnDeliveryNote, DeliveryNoteTabsEnum::DONE_ITEMS->value));
        }
        $inertiaResponse->table(IndexHistory::make()->tableStructure(DeliveryNoteTabsEnum::HISTORY->value));

        return $inertiaResponse;
    }

    public function getItems(ReturnDeliveryNote $returnDeliveryNote): array
    {
        $initArr = [
            DeliveryNoteTabsEnum::ITEMS->value => $this->tab == DeliveryNoteTabsEnum::ITEMS->value ?
                fn () => ReturnDeliveryNoteItemsResource::collection(IndexReturnDeliveryNoteItems::run($returnDeliveryNote, DeliveryNoteTabsEnum::ITEMS->value))
                : Inertia::lazy(fn () => ReturnDeliveryNoteItemsResource::collection(IndexReturnDeliveryNoteItems::run($returnDeliveryNote, DeliveryNoteTabsEnum::ITEMS->value))),
        ];

        if ($returnDeliveryNote->return_state == ReturnDeliveryNoteStateEnum::RETURNING) {
            $initArr = array_merge($initArr, [
                DeliveryNoteTabsEnum::PENDING_ITEMS->value => $this->tab == DeliveryNoteTabsEnum::PENDING_ITEMS->value ?
                    fn () => ReturnDeliveryNoteItemsResource::collection(IndexReturnDeliveryNoteItems::run($returnDeliveryNote, DeliveryNoteTabsEnum::PENDING_ITEMS->value, ReturnDeliveryNoteItemStateEnum::HANDLING))
                    : Inertia::lazy(fn () => ReturnDeliveryNoteItemsResource::collection(IndexReturnDeliveryNoteItems::run($returnDeliveryNote, DeliveryNoteTabsEnum::PENDING_ITEMS->value, ReturnDeliveryNoteItemStateEnum::HANDLING))),
                DeliveryNoteTabsEnum::DONE_ITEMS->value => $this->tab == DeliveryNoteTabsEnum::DONE_ITEMS->value ?
                    fn () => ReturnDeliveryNoteItemsResource::collection(IndexReturnDeliveryNoteItems::run($returnDeliveryNote, DeliveryNoteTabsEnum::DONE_ITEMS->value, ReturnDeliveryNoteItemStateEnum::RETURNED))
                    : Inertia::lazy(fn () => ReturnDeliveryNoteItemsResource::collection(IndexReturnDeliveryNoteItems::run($returnDeliveryNote, DeliveryNoteTabsEnum::DONE_ITEMS->value, ReturnDeliveryNoteItemStateEnum::RETURNED))),
            ]);
        }

        return array_merge($initArr, [
            DeliveryNoteTabsEnum::HISTORY->value => $this->tab == DeliveryNoteTabsEnum::HISTORY->value ?
                fn () => HistoryResource::collection(IndexHistory::run($returnDeliveryNote, DeliveryNoteTabsEnum::HISTORY->value))
                : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($returnDeliveryNote, DeliveryNoteTabsEnum::HISTORY->value)))
        ]);
    }

    public function getDeliveryNoteNotes(ReturnDeliveryNote $returnDeliveryNote): array
    {
        return [
            "note_list" => [
                [
                    "label"       => __("Shipping label message").' ('.__("Customer").')',
                    "note"        => $returnDeliveryNote->shipping_notes ?? '',
                    "information" => __("Note from crm. First 34 char. Will be printed on the shipping label."),
                    "editable"    => true,
                    "bgColor"     => "#38bdf8",
                    "field"       => "shipping_notes"
                ],
                [
                    "label"       => __("Customer's note"),
                    "note"        => $returnDeliveryNote->customer_notes ?? '',
                    "information" => __("This note is from customer in the platform. Not editable."),
                    "editable"    => false,
                    "bgColor"     => "#FF7DBD",
                    "field"       => "customer_notes"
                ],
                // [
                //     "label"       => __("Public"),
                //     "note"        => $returnDeliveryNote->public_notes ?? '',
                //     "information" => __("This note will be visible to public, both staff and the customer can see."),
                //     "editable"    => true,
                //     "bgColor"     => "#94DB84",
                //     "field"       => "public_notes"
                // ],
                [
                    "label"       => __("Order private note"),
                    "note"        => $returnDeliveryNote->internal_notes ?? '',
                    "information" => __("This note is only visible to staff members. You can communicate each other about the order."),
                    "editable"    => true,
                    "bgColor"     => "#FCF4A3",
                    "field"       => "internal_notes"
                ]
            ]
        ];
    }

    public function getBreadcrumbs(ReturnDeliveryNote $returnDeliveryNote, string $routeName, array $routeParameters, string $suffix = ''): array
    {
        $headCrumb = function (ReturnDeliveryNote $returnDeliveryNote, array $routeParameters, string $suffix) {
            return [
                [

                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Return Delivery Note')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $returnDeliveryNote->reference,
                        ],

                    ],
                    'suffix'         => $suffix

                ],
            ];
        };

        return match ($routeName) {
            'grp.org.warehouses.show.incoming.return-delivery-notes.show'
            => array_merge(
                ShowProcurementDashboard::make()->getBreadcrumbs(
                    Arr::only($routeParameters, ['organisation', 'warehouse'])
                ),
                $headCrumb(
                    $returnDeliveryNote,
                    [
                        'index' => [
                            'name'       => 'grp.org.warehouses.show.incoming.return-delivery-notes',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'warehouse'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.warehouses.show.incoming.return-delivery-notes.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'warehouse', 'returnDeliveryNote'])
                        ]
                    ],
                    $suffix
                ),
            ),
            default => []
        };
    }


    // TODO
    // public function getPrevious(DeliveryNote $deliveryNote, ActionRequest $request): ?array
    // {
    //     $query    = DeliveryNote::where('reference', '<', $deliveryNote->reference);
    //     $query    = $this->getNextPrevCommon($query, $deliveryNote, $request);
    //     $previous = $query->orderBy('reference', 'desc')->first();

    //     return $this->getNavigation($previous, $request->route()->getName(), $request->route()->originalParameters());
    // }

    // public function getNext(DeliveryNote $deliveryNote, ActionRequest $request): ?array
    // {
    //     $query = DeliveryNote::where('reference', '>', $deliveryNote->reference);
    //     $query = $this->getNextPrevCommon($query, $deliveryNote, $request);
    //     $next  = $query->orderBy('reference')->first();

    //     return $this->getNavigation($next, $request->route()->getName(), $request->route()->originalParameters());
    // }

    // TODO
    // private function getNextPrevCommon($query, DeliveryNote $deliveryNote, ActionRequest $request)
    // {
    //     if ($request->route()->getName() == 'shops.show.delivery-notes.show') {
    //         $query->where('delivery_notes.shop_id', $deliveryNote->shop_id);
    //     } elseif ($request->route()->getName() == 'grp.org.shops.show.ordering.orders.show.delivery-note') {
    //         $query->leftjoin('delivery_note_order', 'delivery_note_order.delivery_note_id', '=', 'delivery_notes.id');
    //         $query->where('delivery_note_order.order_id', $this->parent->id);
    //     } elseif ($request->route()->getName() == 'grp.org.shops.show.crm.customers.show.delivery_notes.show') {
    //         $query->where('delivery_notes.customer_id', $this->parent->id);
    //     }

    //     return $query;
    // }

    // private function getNavigation(?DeliveryNote $deliveryNote, string $routeName, $routeParameters): ?array
    // {
    //     if (!$deliveryNote) {
    //         return null;
    //     }

    //     return match ($routeName) {
    //         'delivery-notes.show',
    //         'shops.delivery-notes.show' => [
    //             'label' => $deliveryNote->reference,
    //             'route' => [
    //                 'name'       => $routeName,
    //                 'parameters' => [
    //                     'deliveryNote' => $deliveryNote->slug
    //                 ]

    //             ]
    //         ],
    //         'shops.show.delivery-notes.show' => [
    //             'label' => $deliveryNote->reference,
    //             'route' => [
    //                 'name'       => $routeName,
    //                 'parameters' => [
    //                     'shop'         => $deliveryNote->shop->slug,
    //                     'deliveryNote' => $deliveryNote->slug
    //                 ]

    //             ]
    //         ],
    //         'grp.org.warehouses.show.dispatching.delivery_notes.show' => [
    //             'label' => $deliveryNote->reference,
    //             'route' => [
    //                 'name'       => $routeName,
    //                 'parameters' => [
    //                     'organisation' => $deliveryNote->organisation->slug,
    //                     'warehouse'    => $deliveryNote->warehouse->slug,
    //                     'deliveryNote' => $deliveryNote->slug
    //                 ]

    //             ]
    //         ],
    //         'grp.org.shops.show.ordering.delivery-notes.show' => [
    //             'label' => $deliveryNote->reference,
    //             'route' => [
    //                 'name'       => $routeName,
    //                 'parameters' => Arr::only($routeParameters, ['organisation', 'shop', 'deliveryNote'])

    //             ]
    //         ],
    //         'grp.org.shops.show.ordering.orders.show.delivery-note' => [
    //             'label' => $deliveryNote->reference,
    //             'route' => [
    //                 'name'       => $routeName,
    //                 'parameters' => Arr::only($routeParameters, ['organisation', 'shop', 'order', 'deliveryNote'])

    //             ]
    //         ],
    //         'grp.org.shops.show.crm.customers.show.delivery_notes.show' => [
    //             'label' => $deliveryNote->reference,
    //             'route' => [
    //                 'name'       => $routeName,
    //                 'parameters' => Arr::only($routeParameters, ['organisation', 'shop', 'customer', 'deliveryNote'])

    //             ]
    //         ],
    //         default => null
    //     };
    // }
}
