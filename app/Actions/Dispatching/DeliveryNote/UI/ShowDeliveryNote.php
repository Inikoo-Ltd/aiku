<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 13:48:15 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\CRM\Customer\UI\ShowCustomer;
use App\Actions\Dispatching\DeliveryNoteItem\UI\IndexDeliveryNoteItems;
use App\Actions\Dispatching\DeliveryNoteItem\UI\IndexDeliveryNoteItemsStateHandling;
use App\Actions\Dispatching\DeliveryNoteItem\UI\IndexDeliveryNoteItemsStateUnassigned;
use App\Actions\Dispatching\Picking\Picker\Json\GetPickerUsers;
use App\Actions\Helpers\Country\UI\GetAddressData;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\Inventory\Warehouse\UI\ShowWarehouse;
use App\Actions\Ordering\Order\UI\ShowOrder;
use App\Actions\OrgAction;
use App\Actions\Retina\UI\Layout\GetPlatformLogo;
use App\Actions\UI\WithInertia;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Enums\UI\Dispatch\DeliveryNoteTabsEnum;
use App\Http\Resources\CRM\CustomerResource;
use App\Http\Resources\Dispatching\DeliveryNoteItemsResource;
use App\Http\Resources\Dispatching\DeliveryNoteItemsStateHandlingResource;
use App\Http\Resources\Dispatching\DeliveryNoteItemsStateUnassignedResource;
use App\Http\Resources\Dispatching\DeliveryNoteResource;
use App\Http\Resources\Dispatching\ShipmentsResource;
use App\Http\Resources\Helpers\AddressResource;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\Ordering\PickersResource;
use App\Http\Resources\Procurement\ReturnDeliveryNoteResource;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\GoodsIn\ReturnDeliveryNote;
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

class ShowDeliveryNote extends OrgAction
{
    use AsAction;
    use WithInertia;
    use GetPlatformLogo;

    private Order|Shop|Warehouse|Customer $parent;
    private ReturnDeliveryNote|null $return = null;

    private bool $allowAction = true;

    public function handle(DeliveryNote $deliveryNote): DeliveryNote
    {
        return $deliveryNote;
    }


    public function inOrganisation(DeliveryNote $deliveryNote): DeliveryNote
    {
        return $this->handle($deliveryNote);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inShop(Shop $shop, DeliveryNote $deliveryNote, ActionRequest $request): DeliveryNote
    {
        return $this->handle($deliveryNote);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inWarehouse(Organisation $organisation, Warehouse $warehouse, DeliveryNote $deliveryNote, ActionRequest $request): DeliveryNote
    {
        $this->parent = $warehouse;
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(DeliveryNoteTabsEnum::values());

        return $this->handle($deliveryNote);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inOrderInShop(Organisation $organisation, Shop $shop, Order $order, DeliveryNote $deliveryNote, ActionRequest $request): DeliveryNote
    {
        $this->parent = $order;
        $this->initialisationFromShop($shop, $request)->withTab(DeliveryNoteTabsEnum::values());

        return $this->handle($deliveryNote);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inOrderingInShop(Organisation $organisation, Shop $shop, DeliveryNote $deliveryNote, ActionRequest $request): DeliveryNote
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request)->withTab(DeliveryNoteTabsEnum::values());

        return $this->handle($deliveryNote);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inCustomerInShop(Organisation $organisation, Shop $shop, Customer $customer, DeliveryNote $deliveryNote, ActionRequest $request): DeliveryNote
    {
        $this->parent = $customer;
        $this->initialisationFromShop($shop, $request)->withTab(DeliveryNoteTabsEnum::values());

        return $this->handle($deliveryNote);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inOrderInCustomerInShop(Organisation $organisation, Shop $shop, Customer $customer, Order $order, DeliveryNote $deliveryNote, ActionRequest $request): DeliveryNote
    {
        $this->parent = $customer;
        $this->initialisationFromShop($shop, $request)->withTab(DeliveryNoteTabsEnum::values());

        return $this->handle($deliveryNote);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inOrderInCustomerClientInCustomerInShop(
        Organisation $organisation,
        Shop $shop,
        Customer $customer,
        CustomerSalesChannel $customerSalesChannel,
        CustomerClient $customerClient,
        Order $order,
        DeliveryNote $deliveryNote,
        ActionRequest $request
    ): DeliveryNote {
        $this->parent = $customer;
        $this->initialisationFromShop($shop, $request)->withTab(DeliveryNoteTabsEnum::values());

        return $this->handle($deliveryNote);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inOrderInPlatformInCustomerInShop(Organisation $organisation, Shop $shop, Customer $customer, CustomerSalesChannel $customerSalesChannel, Order $order, DeliveryNote $deliveryNote, ActionRequest $request): DeliveryNote
    {
        $this->parent = $customer;
        $this->initialisationFromShop($shop, $request)->withTab(DeliveryNoteTabsEnum::values());

        return $this->handle($deliveryNote);
    }

    public function getHandlingActions(DeliveryNote $deliveryNote): array
    {
        $hasUnHandledItems = DeliveryNoteItem::where('delivery_note_id', $deliveryNote->id)
            ->where('is_handled', false)
            ->exists();

        $actions = [];
        if (!$hasUnHandledItems && $this->allowAction) {
            if ($deliveryNote->shop->type == ShopTypeEnum::DROPSHIPPING) {
                $actions[] = [
                    'type'    => 'button',
                    'style'   => 'save',
                    'tooltip' => __('Set as packed'),
                    'label'   => __('Set as packed'),
                    'route'   => [
                        'method'     => 'patch',
                        'name'       => 'grp.models.delivery_note.state.packed',
                        'parameters' => [
                            'deliveryNote' => $deliveryNote->id
                        ]
                    ]
                ];
            } else {
                $actions[] = [
                    'type' => 'button',
                    'key'  => 'trigger-set-as-picked-or-packed',
                ];
            }
        }


        return $actions;
    }

    public function wrappedActions(DeliveryNote $deliveryNote): array
    {
        $isEditable = false;
        if ($this->parent instanceof Warehouse) {
            $isEditable = true;
        }


        $showCancel = true;

        if (in_array($deliveryNote->state, [
            DeliveryNoteStateEnum::CANCELLED,
            DeliveryNoteStateEnum::DISPATCHED,
            DeliveryNoteStateEnum::FINALISED
        ])) {
            $showCancel = false;
        }

        if ($deliveryNote->shop->engine == ShopEngineEnum::FAIRE) {
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
                    'method'     => 'patch',
                    'name'       => 'grp.models.delivery_note.state.cancel',
                    'parameters' => [
                        'deliveryNote' => $deliveryNote->id
                    ]
                ]
            ];
        }

        if ($isEditable && $deliveryNote->state == DeliveryNoteStateEnum::PACKING) {
            $actions[] = [
                'type'    => 'button',
                'style'   => 'save',
                'icon'    => 'fal fa-tired',
                'tooltip' => __('Go back to picked'),
                'label'   => __('Undo packing'),
                'key'     => 'unpacking',
                'route'   => [
                    'method'     => 'patch',
                    'name'       => 'grp.models.delivery_note.state.undo_packing',
                    'parameters' => [
                        'deliveryNote' => $deliveryNote->id
                    ]
                ],
            ];
        }

        if ($isEditable && $deliveryNote->state == DeliveryNoteStateEnum::PICKED) {
            $actions[] = [
                'type'    => 'button',
                'style'   => 'negative',
                'icon'    => 'fal fa-tired',
                'tooltip' => __('Go back to picking'),
                'label'   => __('Undo set as picked'),
                'key'     => 'unpicked',
                'route'   => [
                    'method'     => 'patch',
                    'name'       => 'grp.models.delivery_note.state.undo_set_as_picked',
                    'parameters' => [
                        'deliveryNote' => $deliveryNote->id
                    ]
                ],
            ];
        }

        if ($isEditable && in_array($deliveryNote->state, [DeliveryNoteStateEnum::PACKED, DeliveryNoteStateEnum::FINALISED])) {
            $actions[] = [
                'type'    => 'button',
                'style'   => 'negative',
                'icon'    => 'fal fa-tired',
                'tooltip' => __('Unpack the parcels').'. '.__('This will set the state to Packing'),
                'label'   => __('Unpack'),
                'key'     => 'unpack',
                'route'   => [
                    'method'     => 'patch',
                    'name'       => 'grp.models.delivery_note.state.unpacked',
                    'parameters' => [
                        'deliveryNote' => $deliveryNote->id
                    ]
                ],
            ];
        }

        if ($isEditable && $deliveryNote->state == DeliveryNoteStateEnum::HANDLING_BLOCKED) {
            $actions[] = [
                'type'    => 'button',
                'style'   => 'save',
                'icon'    => 'fal fa-check-circle',
                'tooltip' => __('Check if all items are picked and finish waiting'),
                'label'   => __('Auto Finish Waiting'),
                'key'     => 'auto-finish-waiting',
                'route'   => [
                    'method'     => 'patch',
                    'name'       => 'grp.models.delivery_note.state.auto_finish_waiting',
                    'parameters' => [
                        'deliveryNote' => $deliveryNote->id
                    ]
                ],
            ];
        }

        if ($deliveryNote->state == DeliveryNoteStateEnum::DISPATCHED && $deliveryNote->shop?->type != ShopTypeEnum::EXTERNAL) {
            $actions[] = [
                'type' => 'button',
                'key'  => 'return',
            ];
        }

        return $actions;
    }

    public function getActions(DeliveryNote $deliveryNote, ActionRequest $request): array
    {
        $isEditable = false;
        if ($this->parent instanceof Warehouse) {
            $isEditable = true;
        }
        if (!$isEditable) {
            return [];
        }

        $startPickingLabel    = __('Start picking');
        $generateInvoiceLabel = __('Generate Invoice');

        return match ($deliveryNote->state) {
            DeliveryNoteStateEnum::UNASSIGNED => [

                [
                    'type'    => 'button',
                    'style'   => 'save',
                    'icon'    => 'fal fa-smile-wink',
                    'tooltip' => __('Change picker to myself, and start picking'),
                    'label'   => $startPickingLabel,
                    'key'     => 'start-picking',
                    'route'   => [
                        'method'     => 'patch',
                        'name'       => 'grp.models.delivery_note.state.handling',
                        'parameters' => [
                            'deliveryNote' => $deliveryNote->id
                        ]
                    ],
                ],
            ],
            DeliveryNoteStateEnum::QUEUED => [
                [
                    'type'   => 'buttonGroup',
                    'key'    => 'picker',
                    'button' => [
                        [
                            'type'    => 'button',
                            'style'   => 'delete',
                            'tooltip' => __('Remove picker'),
                            'label'   => __('Remove Picker'),
                            'icon'    => 'fal fa-user-slash',
                            'key'     => 'remove-picker',
                            'route'   => [
                                'method'     => 'patch',
                                'name'       => 'grp.models.delivery_note.state.remove-picker',
                                'parameters' => [
                                    'deliveryNote' => $deliveryNote->id
                                ]
                            ]
                        ],
                        [
                            'type'    => 'button',
                            'style'   => 'save',
                            'tooltip' => __('Change picker'),
                            'icon'    => 'fal fa-exchange-alt',
                            'label'   => __('Change Picker'),
                            'key'     => 'change-picker',
                        ]

                    ],

                ],
                $deliveryNote->pickerUser->id == $request->user()->id
                    ? [
                    'type'    => 'button',
                    'style'   => 'save',
                    'tooltip' => $startPickingLabel,
                    'label'   => $startPickingLabel,
                    'key'     => 'start-picking',
                    'route'   => [
                        'method'     => 'patch',
                        'name'       => 'grp.models.delivery_note.state.handling',
                        'parameters' => [
                            'deliveryNote' => $deliveryNote->id
                        ]
                    ]
                ]
                    : [
                    'type'    => 'button',
                    'style'   => 'save',
                    'icon'    => 'fal fa-tired',
                    'tooltip' => __('Change picker to myself, and start picking'),
                    'label'   => __('I will pick this'),
                    'key'     => 'start-picking',
                    'route'   => [
                        'method'     => 'patch',
                        'name'       => 'grp.models.delivery_note.state.handling',
                        'parameters' => [
                            'deliveryNote' => $deliveryNote->id
                        ]
                    ],
                ],
            ],
            DeliveryNoteStateEnum::HANDLING => $this->getHandlingActions($deliveryNote),
            DeliveryNoteStateEnum::PACKING => $this->allowAction ? [
                [
                    'type'    => 'button',
                    'style'   => 'save',
                    'tooltip' => __('Set as packed'),
                    'label'   => __('Set as packed'),
                    'key'     => 'follow-back-end',
                    'route'   => [
                        'method'     => 'patch',
                        'name'       => 'grp.models.delivery_note.state.packed',
                        'parameters' => [
                            'deliveryNote' => $deliveryNote->id
                        ]
                    ]
                ]
            ] : [],
            DeliveryNoteStateEnum::PICKED => [
                [
                    'type'  => 'button',
                    'style' => 'save',
                    'label' => __('Start packing'),
                    'key'   => 'action',
                    'route' => [
                        'method'     => 'patch',
                        'name'       => 'grp.models.delivery_note.state.packing',
                        'parameters' => [
                            'deliveryNote' => $deliveryNote->id
                        ]
                    ]
                ]
            ],
            DeliveryNoteStateEnum::PACKED => [$this->getPackedActions($deliveryNote)],
            DeliveryNoteStateEnum::FINALISED => [
                [
                    'type'    => 'button',
                    'style'   => 'save',
                    'tooltip' => __('Dispatch'),
                    'label'   => __('Dispatch'),
                    'key'     => 'action',
                    'route'   => [
                        'method'     => 'patch',
                        'name'       => 'grp.models.delivery_note.state.dispatched',
                        'parameters' => [
                            'deliveryNote' => $deliveryNote->id
                        ]
                    ]
                ],
                $deliveryNote->orders->first()->invoices->count() == 0 ?
                    [
                        'type'    => 'button',
                        'style'   => '',
                        'tooltip' => $generateInvoiceLabel,
                        'label'   => $generateInvoiceLabel,
                        'key'     => 'action',
                        'route'   => [
                            'method'     => 'patch',
                            'name'       => 'grp.models.order.generate_invoice',
                            'parameters' => [
                                'order' => $deliveryNote->orders->first()->id
                            ]
                        ]
                    ] : [],
            ],
            DeliveryNoteStateEnum::DISPATCHED => [
                $deliveryNote->orders->first()->invoices->count() == 0 ?
                    [
                        'type'    => 'button',
                        'style'   => '',
                        'tooltip' => $generateInvoiceLabel,
                        'label'   => $generateInvoiceLabel,
                        'key'     => 'action',
                        'route'   => [
                            'method'     => 'patch',
                            'name'       => 'grp.models.delivery_note.state.dispatched',
                            'parameters' => [
                                'order' => $deliveryNote->orders->first()->id
                            ]
                        ]
                    ] : [],
                [
                    'type'    => 'button',
                    'style'   => 'cancel',
                    'tooltip' => __('Set Delivery Note as undispatched (back to finalised)'),
                    'label'   => __('Undispatch'),
                    'key'     => 'undispatch',
                    'route'   => [
                        'method'     => 'patch',
                        'name'       => 'grp.models.delivery_note.state.rollback',
                        'parameters' => [
                            'deliveryNote' => $deliveryNote->id
                        ]
                    ]
                ],
            ],
            default => []
        };
    }

    public function getPackedActions(DeliveryNote $deliveryNote): array
    {
        if ($deliveryNote->is_shipping_by_external) {
            if ($deliveryNote->orders->first()->invoices->count() > 0) {
                return [
                    'type'    => 'button',
                    'style'   => 'save',
                    'tooltip' => __('Dispatch'),
                    'label'   => __('Dispatch'),
                    'key'     => 'action',
                    'route'   => [
                        'method'     => 'patch',
                        'name'       => 'grp.models.delivery_note.state.dispatched',
                        'parameters' => [
                            'deliveryNote' => $deliveryNote->id
                        ]
                    ]
                ];
            }
        } elseif (count($deliveryNote->parcels ?? [])) {
            return [
                'type'    => 'button',
                'style'   => 'save',
                'tooltip' => __('Finalise'),
                'label'   => match (true) {
                    $deliveryNote->type === DeliveryNoteTypeEnum::REPLACEMENT && !$deliveryNote->collection_address_id => __('Dispatch'),
                    $deliveryNote->type === DeliveryNoteTypeEnum::REPLACEMENT && $deliveryNote->collection_address_id => __('set as collected'),
                    $deliveryNote->type !== DeliveryNoteTypeEnum::REPLACEMENT && !$deliveryNote->collection_address_id => __('Finalise and Dispatch'),
                    (bool)$deliveryNote->collection_address_id => __('Finalise and set as Collected'),
                    default => __('Finalise and Dispatch')
                },
                'key'     => match (true) {
                    $deliveryNote->type === DeliveryNoteTypeEnum::REPLACEMENT && !$deliveryNote->collection_address_id => 'action',
                    $deliveryNote->type === DeliveryNoteTypeEnum::REPLACEMENT && $deliveryNote->collection_address_id => 'action',
                    $deliveryNote->type !== DeliveryNoteTypeEnum::REPLACEMENT && !$deliveryNote->collection_address_id => 'finalise-and-dispatch',
                    (bool)$deliveryNote->collection_address_id => 'action',
                    default => 'finalise-and-dispatch'
                },
                'route'   => [
                    'method'     => 'patch',
                    'name'       => 'grp.models.delivery_note.state.finalise_and_dispatch',
                    'parameters' => [
                        'deliveryNote' => $deliveryNote->id
                    ]
                ]
            ];
        }


        return [];
    }


    public function getBoxStats(DeliveryNote $deliveryNote): array
    {
        $estWeight = ($deliveryNote->estimated_weight ?? 0) / 1000;
        $order     = $deliveryNote->orders->first();

        $additionalShipmentRoutes = [];
        if ($deliveryNote->is_shipping_by_external) {
            if ($order->shop->engine == ShopEngineEnum::FAIRE) {
                $additionalShipmentRoutes = [
                    'get_external_shipment_route' => [
                        'label'      => __('Get shipment from Faire'),
                        'name'       => 'grp.models.delivery_note.shipment.store_faire',
                        'parameters' => [
                            'deliveryNote' => $deliveryNote->id
                        ]
                    ],
                ];
            } elseif ($order->platform->type == PlatformTypeEnum::TIKTOK) {
                $additionalShipmentRoutes = [
                    'get_external_shipment_route' => [
                        'label'      => __('Get shipment from Tiktok'),
                        'name'       => 'grp.models.delivery_note.shipment.store_tiktok',
                        'parameters' => [
                            'deliveryNote' => $deliveryNote->id
                        ]
                    ]
                ];
            }
        } else {
            $additionalShipmentRoutes = [
                'submit_route' => [
                    'name'       => 'grp.models.delivery_note.shipment.store',
                    'parameters' => [
                        'deliveryNote' => $deliveryNote->id
                    ]
                ]
            ];
        }

        $trolleys = [];
        foreach ($deliveryNote->trolleys as $trolley) {
            $trolleys[] = [
                'id'   => $trolley->id,
                'slug' => $trolley->slug,
                'name' => $trolley->name,
            ];
        }
        $pickedBays = [];
        foreach ($deliveryNote->pickedBays as $pickedBay) {
            $pickedBays[] = [
                'id'   => $pickedBay->id,
                'slug' => $pickedBay->slug,
                'name' => $pickedBay->code,
            ];
        }

        return [
            'state'                        => $deliveryNote->state,
            'state_icon'                   => DeliveryNoteStateEnum::stateIcon()[$deliveryNote->state->value],
            'state_label'                  => $deliveryNote->state->labels()[$deliveryNote->state->value],
            'is_collection'                => (bool)$deliveryNote->collection_address_id,
            'is_shipping_by_external'      => $deliveryNote->is_shipping_by_external,
            'is_replacement'               => $deliveryNote->type === DeliveryNoteTypeEnum::REPLACEMENT,
            'customer'                     => array_merge(
                CustomerResource::make($deliveryNote->customer)->getArray(),
                [
                    'addresses' => [
                        'delivery' => AddressResource::make($deliveryNote->deliveryAddress ?? new Address()),
                    ],
                    'route'     => [
                        'name'       => 'grp.org.shops.show.crm.customers.show',
                        'parameters' => [
                            'organisation' => $deliveryNote->organisation->slug,
                            'shop'         => $deliveryNote->shop->slug,
                            'customer'     => $deliveryNote->customer->slug
                        ]
                    ]
                ]
            ),
            'customer_client'              => $deliveryNote->customerClient,
            'currency_code'                => $deliveryNote->shop->currency->code,
            'external_shop'                => $deliveryNote->shop->type == ShopTypeEnum::EXTERNAL ? [  // TODO: Artha
                                                                                                       'engine_value'            => $deliveryNote->shop->engine->value,
                                                                                                       'engine_label'            => ShopEngineEnum::from($deliveryNote->shop->engine->value)->label(),
                                                                                                       'external_shipping_label' => $deliveryNote->shop->engine == ShopEngineEnum::FAIRE ? __('Ship with Faire') : __('External shipping')
            ] : null,
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
            'picker'                       => $deliveryNote->pickerUser,
            'packer'                       => $deliveryNote->packerUser,
            'picked_bays'                  => $pickedBays,
            'trolleys'                     => $trolleys,
            'parcels'                      => $deliveryNote->parcels,
            'shipments'                    => $deliveryNote->shipments ? ShipmentsResource::collection($deliveryNote->shipments()->with('shipper')->get())->toArray(request()) : null,
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
            ],
            'shop_type'                    => $deliveryNote->shop->type,
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
            'return_dn'                    => ReturnDeliveryNoteResource::collection($deliveryNote->returnedDeliveryNote)
        ];
    }

    public function getTimeline(DeliveryNote $deliveryNote): array
    {
        $timeline = [];

        foreach (DeliveryNoteStateEnum::cases() as $case) {
            $timestamp = $deliveryNote->{$case->snake().'_at'}
                ? $deliveryNote->{$case->snake().'_at'}
                : null;

            $timestamp = $timestamp ?: null;


            if ($case == DeliveryNoteStateEnum::HANDLING_BLOCKED && !$timestamp) {
                continue;
            }

            if ($case == DeliveryNoteStateEnum::CANCELLED && $deliveryNote->state != DeliveryNoteStateEnum::CANCELLED) {
                continue;
            }

            if ($deliveryNote->type === DeliveryNoteTypeEnum::REPLACEMENT && $case == DeliveryNoteStateEnum::FINALISED) {
                continue;
            }

            $timestamp = match ($case) {
                DeliveryNoteStateEnum::UNASSIGNED => $deliveryNote->created_at,
                default => $timestamp ?: null
            };

            $formatTime = 'PPp';


            $label = match ($case) {
                DeliveryNoteStateEnum::UNASSIGNED => __('Created'),
                default => $case->labels()[$case->value]
            };


            if (
                $deliveryNote->state === DeliveryNoteStateEnum::QUEUED && $case == DeliveryNoteStateEnum::QUEUED || $deliveryNote->state === DeliveryNoteStateEnum::HANDLING && $case == DeliveryNoteStateEnum::HANDLING
            ) {
                $label .= ' ('.$deliveryNote->pickerUser?->contact_name.')';
            }


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

    public function htmlResponse(DeliveryNote $deliveryNote, ActionRequest $request): Response
    {
        $isEditable = false;
        if ($this->parent instanceof Warehouse) {
            $isEditable = true;
        }

        $handler = $deliveryNote->picker_user_id;

        if ($deliveryNote->state == DeliveryNoteStateEnum::PACKING) {
            $handler = $deliveryNote->packer_user_id;
        }

        $allowAction = ($handler && $handler == request()->user()->id);

        if (!$allowAction) {
            $tempHandler = session('temp_handling_delivery_note') ?? [];
            $allowAction = $deliveryNote->id == data_get($tempHandler, 'value') && now()->lt(data_get($tempHandler, 'expires_at'));
        }

        $this->allowAction = $allowAction;

        $actions = $this->getActions($deliveryNote, $request);

        $warning = null;

        if ($deliveryNote->pickingSessions && $deliveryNote->pickingSessions->isNotEmpty()) {
            $pickingSessions = $deliveryNote->pickingSessions->map(function ($pickingSession) {
                /** @var PickingSession $pickingSession */
                return [
                    'reference' => $pickingSession->reference,
                    'route'     => [
                        'name'       => 'grp.org.warehouses.show.dispatching.picking_sessions.show',
                        'parameters' => [
                            'organisation'   => $pickingSession->organisation->slug,
                            'warehouse'      => $pickingSession->warehouse->slug,
                            'pickingSession' => $pickingSession->slug,
                        ],
                    ],
                ];
            })->toArray();

            $warning = [
                'text'             => __('This DeliveryNote is being picked in Picking Sessions'),
                'picking_sessions' => $pickingSessions,
            ];
        }

        $model = __('Delivery Note');
        if ($deliveryNote->type == DeliveryNoteTypeEnum::REPLACEMENT) {
            $model = __('Replacement Delivery Note');
        }

        $showChangePickerPacker = $deliveryNote->shop->type !== ShopTypeEnum::DROPSHIPPING;

        // Disable waiting on DS no?
        $allowWaiting = data_get($this->organisation->settings, 'orders.allow_waiting', false) && $deliveryNote->shop?->type !== ShopTypeEnum::DROPSHIPPING;

        if ($deliveryNote->state == DeliveryNoteStateEnum::PACKING) {
            $this->tab = DeliveryNoteTabsEnum::PENDING_ITEMS->value;
        }

        $props = [
            'title'         => __('Delivery note').' '.$deliveryNote->reference,
            'breadcrumbs'   => $this->getBreadcrumbs(
                $deliveryNote,
                $request->route()->getName(),
                $request->route()->originalParameters(),
            ),
            'navigation'    => [
                'previous' => $this->getPrevious($deliveryNote, $request),
                'next'     => $this->getNext($deliveryNote, $request),
            ],
            'pageHead'      => [
                'title'           => $deliveryNote->reference,
                'model'           => $model,
                'icon'            => [
                    'icon'  => 'fal fa-truck',
                    'title' => __('Delivery note')
                ],
                'afterTitle'      => [
                    'label' => $deliveryNote->state->labels()[$deliveryNote->state->value],
                ],
                'actions'         => $actions,
                'wrapped_actions' => $this->wrappedActions($deliveryNote),
            ],
            'warning'       => $warning,
            'is_editable'   => $isEditable,
            'tabs'          => [
                'current'    => $this->tab,
                'navigation' => in_array($deliveryNote->state, [DeliveryNoteStateEnum::PACKING, $deliveryNote->state == DeliveryNoteStateEnum::PACKED])
                    ?
                    DeliveryNoteTabsEnum::navigation($deliveryNote)
                    :
                    DeliveryNoteTabsEnum::navigationExcept($deliveryNote, [DeliveryNoteTabsEnum::DONE_ITEMS, DeliveryNoteTabsEnum::PENDING_ITEMS])
            ],
            'delivery_note' => DeliveryNoteResource::make($deliveryNote)->toArray(request()),

            'address'             => [
                'delivery' => AddressResource::make($deliveryNote->deliveryAddress ?? new Address()),
                'options'  => [
                    'countriesAddressData' => GetAddressData::run()
                ]
            ],
            'allowActions'        => $allowAction,
            'timelines'           => $this->getTimeline($deliveryNote),
            'box_stats'           => $this->getBoxStats($deliveryNote),
            'shop_type'           => $deliveryNote->shop->type,
            'notes'               => $this->getDeliveryNoteNotes($deliveryNote),
            'quick_pickers'       => $this->quickGetPickers(),
            'routes'              => [
                'update'                => [
                    'name'       => 'grp.models.delivery_note.update',
                    'parameters' => [
                        'deliveryNote' => $deliveryNote->id
                    ]
                ],
                'set_queue'             => [
                    'method'     => 'patch',
                    'name'       => 'grp.models.delivery_note.state.in_queue',
                    'parameters' => [
                        'deliveryNote' => $deliveryNote->id
                    ]
                ],
                'pickers_list'          => [
                    'name'       => 'grp.json.employees.picker_users',
                    'parameters' => [
                        'organisation' => $deliveryNote->organisation->slug
                    ]
                ],
                'packers_list'          => [
                    'name'       => 'grp.json.employees.packers',
                    'parameters' => [
                        'organisation' => $deliveryNote->organisation->slug
                    ]
                ],
                'exportPdfRoute'        => [
                    'name'       => 'grp.org.accounting.invoices.download',
                    'parameters' => [
                        'organisation' => $deliveryNote->organisation->slug,
                        'invoice'      => $deliveryNote->slug
                    ]
                ],
                'assignSelfTemporarily' => [
                    'name'       => 'grp.org.shops.show.ordering.orders.show.delivery-note.temp-picker',
                    'parameters' => [
                        'organisation' => $deliveryNote->organisation->slug,
                        'shop'         => $deliveryNote->shop->slug,
                        'deliveryNote' => $deliveryNote->slug,
                    ]
                ]
            ],
            'delivery_note_state' => [
                'value' => $deliveryNote->state,
                'label' => $deliveryNote->state->labels()[$deliveryNote->state->value],
            ],
            'shipments_routes'    => [
                'submit_route' => [
                    'name'       => 'grp.models.delivery_note.shipment.store',
                    'parameters' => [
                        'deliveryNote' => $deliveryNote->id
                    ]
                ],

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
            ],
            'warehouse'           => [
                'slug' => $deliveryNote->warehouse->slug,
            ],
            'organisation'        => [
                'slug' => $deliveryNote->organisation->slug,
            ],

            'is_faire_order' => ($deliveryNote->shop->engine == ShopEngineEnum::FAIRE),

            'allow_waiting'               => $allowWaiting,
            'allow_picker_set_not_picked' => !$allowWaiting || (data_get($this->organisation->settings, 'orders.allow_picker_set_not_picked', false)),
            'showChangePickerPacker'      => $showChangePickerPacker,

            DeliveryNoteTabsEnum::HISTORY->value => $this->tab == DeliveryNoteTabsEnum::HISTORY->value ?
                fn () => HistoryResource::collection(IndexHistory::run($deliveryNote, DeliveryNoteTabsEnum::HISTORY->value))
                : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($deliveryNote, DeliveryNoteTabsEnum::HISTORY->value))),
            'shop'                               => [
                'type' => $deliveryNote->shop?->type?->value,
            ]


        ];


        $props = array_merge($props, $this->getItems($deliveryNote));


        $inertiaResponse = Inertia::render(
            'Org/Dispatching/DeliveryNote',
            $props
        );

        if ($deliveryNote->state == DeliveryNoteStateEnum::UNASSIGNED || $deliveryNote->state == DeliveryNoteStateEnum::QUEUED) {
            $inertiaResponse->table(IndexDeliveryNoteItemsStateUnassigned::make()->tableStructure(deliveryNote: $deliveryNote, prefix: DeliveryNoteTabsEnum::ITEMS->value));
        } elseif ($deliveryNote->state == DeliveryNoteStateEnum::HANDLING) {
            $inertiaResponse->table(IndexDeliveryNoteItemsStateHandling::make()->tableStructure(prefix: DeliveryNoteTabsEnum::ITEMS->value, deliveryNote: $deliveryNote, isEditable: $isEditable));
        } elseif ($deliveryNote->state == DeliveryNoteStateEnum::PACKING || $deliveryNote->state == DeliveryNoteStateEnum::PACKED) {
            $inertiaResponse->table(IndexDeliveryNoteItems::make()->tableStructure($deliveryNote, DeliveryNoteTabsEnum::ITEMS->value, $isEditable));
            $inertiaResponse->table(IndexDeliveryNoteItems::make()->tableStructure($deliveryNote, DeliveryNoteTabsEnum::PENDING_ITEMS->value, $isEditable));
            $inertiaResponse->table(IndexDeliveryNoteItems::make()->tableStructure($deliveryNote, DeliveryNoteTabsEnum::DONE_ITEMS->value, $isEditable));
        } else {
            $inertiaResponse->table(IndexDeliveryNoteItems::make()->tableStructure(parent: $deliveryNote, prefix: DeliveryNoteTabsEnum::ITEMS->value, isEditable: $isEditable));
        }

        $inertiaResponse->table(IndexHistory::make()->tableStructure(DeliveryNoteTabsEnum::HISTORY->value));

        return $inertiaResponse;
    }

    public function getItems(DeliveryNote $deliveryNote): array
    {
        if ($deliveryNote->state == DeliveryNoteStateEnum::UNASSIGNED || $deliveryNote->state == DeliveryNoteStateEnum::QUEUED) {
            return [
                DeliveryNoteTabsEnum::ITEMS->value => $this->tab == DeliveryNoteTabsEnum::ITEMS->value ?
                    fn () => DeliveryNoteItemsStateUnassignedResource::collection(IndexDeliveryNoteItemsStateUnassigned::run($deliveryNote, DeliveryNoteTabsEnum::ITEMS->value))
                    : Inertia::lazy(fn () => DeliveryNoteItemsStateUnassignedResource::collection(IndexDeliveryNoteItemsStateUnassigned::run($deliveryNote, DeliveryNoteTabsEnum::ITEMS->value))),

            ];
        } elseif ($deliveryNote->state == DeliveryNoteStateEnum::HANDLING) {
            return [
                DeliveryNoteTabsEnum::ITEMS->value => $this->tab == DeliveryNoteTabsEnum::ITEMS->value ?
                    fn () => DeliveryNoteItemsStateHandlingResource::collection(IndexDeliveryNoteItemsStateHandling::run($deliveryNote, DeliveryNoteTabsEnum::ITEMS->value))
                    : Inertia::lazy(fn () => DeliveryNoteItemsStateHandlingResource::collection(IndexDeliveryNoteItemsStateHandling::run($deliveryNote, DeliveryNoteTabsEnum::ITEMS->value))),

            ];
        } elseif ($deliveryNote->state == DeliveryNoteStateEnum::PACKING || $deliveryNote->state == DeliveryNoteStateEnum::PACKED) {
            return [
                DeliveryNoteTabsEnum::ITEMS->value => $this->tab == DeliveryNoteTabsEnum::ITEMS->value ?
                    fn () => DeliveryNoteItemsResource::collection(IndexDeliveryNoteItems::run($deliveryNote, DeliveryNoteTabsEnum::ITEMS->value))
                    : Inertia::lazy(fn () => DeliveryNoteItemsResource::collection(IndexDeliveryNoteItems::run($deliveryNote, DeliveryNoteTabsEnum::ITEMS->value))),

                DeliveryNoteTabsEnum::PENDING_ITEMS->value => $this->tab == DeliveryNoteTabsEnum::PENDING_ITEMS->value ?
                    fn () => DeliveryNoteItemsResource::collection(IndexDeliveryNoteItems::run($deliveryNote, DeliveryNoteTabsEnum::PENDING_ITEMS->value, stateFilter: DeliveryNoteItemStateEnum::PACKING))
                    : Inertia::lazy(fn () => DeliveryNoteItemsResource::collection(IndexDeliveryNoteItems::run($deliveryNote, DeliveryNoteTabsEnum::PENDING_ITEMS->value, stateFilter: DeliveryNoteItemStateEnum::PACKING))),

                DeliveryNoteTabsEnum::DONE_ITEMS->value => $this->tab == DeliveryNoteTabsEnum::DONE_ITEMS->value ?
                    fn () => DeliveryNoteItemsResource::collection(IndexDeliveryNoteItems::run($deliveryNote, DeliveryNoteTabsEnum::DONE_ITEMS->value, stateFilter: DeliveryNoteItemStateEnum::PACKED))
                    : Inertia::lazy(fn () => DeliveryNoteItemsResource::collection(IndexDeliveryNoteItems::run($deliveryNote, DeliveryNoteTabsEnum::DONE_ITEMS->value, stateFilter: DeliveryNoteItemStateEnum::PACKED))),
            ];
        }

        return [
            DeliveryNoteTabsEnum::ITEMS->value => $this->tab == DeliveryNoteTabsEnum::ITEMS->value ?
                fn () => DeliveryNoteItemsResource::collection(IndexDeliveryNoteItems::run($deliveryNote, DeliveryNoteTabsEnum::ITEMS->value))
                : Inertia::lazy(fn () => DeliveryNoteItemsResource::collection(IndexDeliveryNoteItems::run($deliveryNote, DeliveryNoteTabsEnum::ITEMS->value))),

        ];
    }

    public function getDeliveryNoteNotes(DeliveryNote $deliveryNote): array
    {
        return [
            "note_list" => [
                [
                    "label"       => __("Shipping label message").' ('.__("Customer").')',
                    "note"        => $deliveryNote->shipping_notes ?? '',
                    "information" => __("Note from crm. First 34 char. Will be printed on the shipping label."),
                    "editable"    => true,
                    "bgColor"     => "#38bdf8",
                    "field"       => "shipping_notes"
                ],
                [
                    "label"       => __("Customer's note"),
                    "note"        => $deliveryNote->customer_notes ?? '',
                    "information" => __("This note is from customer in the platform. Not editable."),
                    "editable"    => false,
                    "bgColor"     => "#FF7DBD",
                    "field"       => "customer_notes"
                ],
                [
                    "label"       => __("Order private note"),
                    "note"        => $deliveryNote->internal_notes ?? '',
                    "information" => __("This note is only visible to staff members. You can communicate each other about the order."),
                    "editable"    => true,
                    "bgColor"     => "#FCF4A3",
                    "field"       => "internal_notes"
                ]
            ]
        ];
    }

    public function getBreadcrumbs(DeliveryNote $deliveryNote, string $routeName, array $routeParameters, string $suffix = ''): array
    {
        $headCrumb = function (DeliveryNote $deliveryNote, array $routeParameters, string $suffix) {
            return [
                [

                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Delivery Note')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $deliveryNote->reference,
                        ],

                    ],
                    'suffix'         => $suffix

                ],
            ];
        };

        return match ($routeName) {
            'grp.org.warehouses.show.dispatching.delivery_notes.show',
            => array_merge(
                ShowWarehouse::make()->getBreadcrumbs(
                    Arr::only($routeParameters, ['organisation', 'warehouse'])
                ),
                $headCrumb(
                    $deliveryNote,
                    [
                        'index' => [
                            'name'       => 'grp.org.warehouses.show.dispatching.delivery-notes',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'warehouse'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.warehouses.show.dispatching.delivery_notes.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'warehouse', 'deliveryNote'])
                        ]
                    ],
                    $suffix
                ),
            ),
            'grp.org.shops.show.ordering.orders.show.delivery-note',
            => array_merge(
                ShowOrder::make()->getBreadcrumbs(
                    $this->parent,
                    $routeName,
                    $routeParameters
                ),
                $headCrumb(
                    $deliveryNote,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.ordering.orders.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'shop', 'order'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.ordering.orders.show.delivery-note',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'shop', 'order', 'deliveryNote'])
                        ]
                    ],
                    $suffix
                ),
            ),
            'grp.org.shops.show.ordering.delivery-notes.show',
            => array_merge(
                ShowShop::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $deliveryNote,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.ordering.delivery-notes.index',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'shop'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.ordering.delivery-notes.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'shop', 'deliveryNote'])
                        ]
                    ],
                    $suffix
                ),
            ),
            'grp.org.shops.show.crm.customers.show.delivery_notes.show',
            => array_merge(
                ShowCustomer::make()->getBreadcrumbs(
                    'grp.org.shops.show.crm.customers.show',
                    $routeParameters
                ),
                $headCrumb(
                    $deliveryNote,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.crm.customers.show.delivery_notes.index',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'shop', 'customer'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.crm.customers.show.delivery_notes.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'shop', 'customer', 'deliveryNote'])
                        ]
                    ],
                    $suffix
                ),
            ),
            default => []
        };
    }


    public function getPrevious(DeliveryNote $deliveryNote, ActionRequest $request): ?array
    {
        $query    = DeliveryNote::where('reference', '<', $deliveryNote->reference);
        $query    = $this->getNextPrevCommon($query, $deliveryNote, $request);
        $previous = $query->orderBy('reference', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName(), $request->route()->originalParameters());
    }

    public function getNext(DeliveryNote $deliveryNote, ActionRequest $request): ?array
    {
        $query = DeliveryNote::where('reference', '>', $deliveryNote->reference);
        $query = $this->getNextPrevCommon($query, $deliveryNote, $request);
        $next  = $query->orderBy('reference')->first();

        return $this->getNavigation($next, $request->route()->getName(), $request->route()->originalParameters());
    }

    private function getNextPrevCommon($query, DeliveryNote $deliveryNote, ActionRequest $request)
    {
        if ($request->route()->getName() == 'shops.show.delivery-notes.show') {
            $query->where('delivery_notes.shop_id', $deliveryNote->shop_id);
        } elseif ($request->route()->getName() == 'grp.org.shops.show.ordering.orders.show.delivery-note') {
            $query->leftjoin('delivery_note_order', 'delivery_note_order.delivery_note_id', '=', 'delivery_notes.id');
            $query->where('delivery_note_order.order_id', $this->parent->id);
        } elseif ($request->route()->getName() == 'grp.org.shops.show.crm.customers.show.delivery_notes.show') {
            $query->where('delivery_notes.customer_id', $this->parent->id);
        }

        return $query;
    }


    private function getNavigation(?DeliveryNote $deliveryNote, string $routeName, $routeParameters): ?array
    {
        if (!$deliveryNote) {
            return null;
        }

        return match ($routeName) {
            'delivery-notes.show',
            'shops.delivery-notes.show' => [
                'label' => $deliveryNote->reference,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'deliveryNote' => $deliveryNote->slug
                    ]

                ]
            ],
            'shops.show.delivery-notes.show' => [
                'label' => $deliveryNote->reference,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'shop'         => $deliveryNote->shop->slug,
                        'deliveryNote' => $deliveryNote->slug
                    ]

                ]
            ],
            'grp.org.warehouses.show.dispatching.delivery_notes.show' => [
                'label' => $deliveryNote->reference,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $deliveryNote->organisation->slug,
                        'warehouse'    => $deliveryNote->warehouse->slug,
                        'deliveryNote' => $deliveryNote->slug
                    ]

                ]
            ],
            'grp.org.shops.show.ordering.delivery-notes.show' => [
                'label' => $deliveryNote->reference,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => Arr::only($routeParameters, ['organisation', 'shop', 'deliveryNote'])

                ]
            ],
            'grp.org.shops.show.ordering.orders.show.delivery-note' => [
                'label' => $deliveryNote->reference,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => Arr::only($routeParameters, ['organisation', 'shop', 'order', 'deliveryNote'])

                ]
            ],
            'grp.org.shops.show.crm.customers.show.delivery_notes.show' => [
                'label' => $deliveryNote->reference,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => Arr::only($routeParameters, ['organisation', 'shop', 'customer', 'deliveryNote'])

                ]
            ],
            default => null
        };
    }
}
