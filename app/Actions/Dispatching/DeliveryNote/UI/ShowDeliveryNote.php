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
use App\Actions\Inventory\Warehouse\UI\ShowWarehouse;
use App\Actions\Ordering\Order\UI\ShowOrder;
use App\Actions\OrgAction;
use App\Actions\Retina\UI\Layout\GetPlatformLogo;
use App\Actions\UI\WithInertia;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\UI\Dispatch\DeliveryNoteTabsEnum;
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

    public function getHandlingActions(DeliveryNote $deliveryNote): array
    {
        $hasUnHandledItems = DeliveryNoteItem::where('delivery_note_id', $deliveryNote->id)
            ->where('is_handled', false)
            ->exists();

        $actions = [];

        if (!$hasUnHandledItems) {
            $actions[] = [
                'type'    => 'button',
                'style'   => 'save',
                'tooltip' => __('Set as packed'),
                'label'   => __('Set as packed'),
                'key'     => 'action',
                'route'   => [
                    'method'     => 'patch',
                    'name'       => 'grp.models.delivery_note.state.packed',
                    'parameters' => [
                        'deliveryNote' => $deliveryNote->id
                    ]
                ]
            ];
        }

        $actions[] = [
            'type'    => 'button',
            'style'   => 'save',
            'tooltip' => __('Change picker'),
            'icon'    => 'fal fa-exchange-alt',
            'label'   => __('Change Picker'),
            'key'     => 'change-picker',
        ];


        return $actions;
    }


    public function getActions(DeliveryNote $deliveryNote, ActionRequest $request): array
    {
        $startPickingLabel    = __('Start picking');
        $generateInvoiceLabel = __('Generate Invoice');


        return match ($deliveryNote->state) {
            DeliveryNoteStateEnum::UNASSIGNED => [
                [
                    'type'      => 'button',
                    'style'     => 'save',
                    'tooltip'   => __('Unassigned'),
                    'label'     => __('Put in Queue'),
                    'iconRight' => 'fas fa-arrow-right',
                    'key'       => 'to-queue',
                ],
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

            DeliveryNoteStateEnum::PACKED => [
                count($deliveryNote->parcels ?? []) ? [
                    'type'    => 'button',
                    'style'   => 'save',
                    'tooltip' => __('Finalised'),
                    'label'   => __('Finalise and Dispatch'),
                    'key'     => 'action',
                    'route'   => [
                        'method'     => 'patch',
                        'name'       => 'grp.models.delivery_note.state.finalise_and_dispatch',
                        'parameters' => [
                            'deliveryNote' => $deliveryNote->id
                        ]
                    ]
                ] : [],
            ],
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
                    ] : []
            ],
            default => []
        };
    }

    public function getInvoiceButton(DeliveryNote $deliveryNote): array
    {
        $invoiceButton        = [];
        $generateInvoiceLabel = __('Generate Invoice');

        if (($deliveryNote->state == DeliveryNoteStateEnum::FINALISED || $deliveryNote->state == DeliveryNoteStateEnum::DISPATCHED) && $deliveryNote->orders->first()->invoices->count() == 0) {
            $invoiceButton = [
                [
                    'type'    => 'button',
                    'style'   => 'save',
                    'tooltip' => $generateInvoiceLabel,
                    'label'   => $generateInvoiceLabel,
                    'key'     => 'action',
                    'route'   => [
                        'method'     => 'patch',
                        'name'       => 'grp.models.delivery_note.state.dispatched',
                        'parameters' => [
                            'deliveryNote' => $deliveryNote->id
                        ]
                    ]
                ]
            ];
        }

        return $invoiceButton;
    }

    public function getBoxStats(DeliveryNote $deliveryNote): array
    {
        $estWeight = ($deliveryNote->estimated_weight ?? 0) / 1000;
        $order     = $deliveryNote->orders->first();

        return [
            'state'            => $deliveryNote->state,
            'state_icon'       => DeliveryNoteStateEnum::stateIcon()[$deliveryNote->state->value],
            'state_label'      => $deliveryNote->state->labels()[$deliveryNote->state->value],
            'customer'         => array_merge(
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
            'customer_client'  => $deliveryNote->customerClient,
            'platform'         => [
                'name' => $deliveryNote->platform?->name,
                'logo' => $deliveryNote->customerSalesChannel?->platform?->code ? $this->getPlatformLogo($deliveryNote->customerSalesChannel->platform->code) : null,
            ],
            'products'         => [
                'estimated_weight' => $estWeight,
                'number_items'     => $deliveryNote->number_items,
            ],
            'order'            => [
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
            'address' => [
                'delivery' => AddressResource::make($deliveryNote->deliveryAddress ?? new Address()),
                'options'  => [
                    'countriesAddressData' => GetAddressData::run()
                ]
            ],
            'delivery_address' => AddressResource::make($deliveryNote->deliveryAddress),
            'picker'           => $deliveryNote->pickerUser,
            'packer'           => $deliveryNote->packerUser,
            'parcels'          => $deliveryNote->parcels,
            'shipments'        => $deliveryNote->shipments ? ShipmentsResource::collection($deliveryNote->shipments()->with('shipper')->get())->toArray(request()) : null,
            'shipments_routes'           => [
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


            $timestamp = match ($case) {
                DeliveryNoteStateEnum::UNASSIGNED => $deliveryNote->created_at,
                default => $timestamp ?: null
            };

            $formatTime = match ($case) {
                DeliveryNoteStateEnum::QUEUED => 'PPp',
                default => null
            };

            $label = match ($case) {
                DeliveryNoteStateEnum::UNASSIGNED => __('Created'),
                default => $case->labels()[$case->value]
            };


            if (
                $deliveryNote->state === DeliveryNoteStateEnum::QUEUED && $case == DeliveryNoteStateEnum::QUEUED || $deliveryNote->state === DeliveryNoteStateEnum::HANDLING && $case == DeliveryNoteStateEnum::HANDLING
            ) {
                $label .= ' ('.$deliveryNote->pickerUser->contact_name.')';
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
        $actions = $this->getActions($deliveryNote, $request);

        $props = [
            'title'         => __('delivery note'),
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
                'title'      => $deliveryNote->reference,
                'model'      => __('Delivery Note'),
                'icon'       => [
                    'icon'  => 'fal fa-truck',
                    'title' => __('delivery note')
                ],
                'afterTitle' => [
                    'label' => $deliveryNote->state->labels()[$deliveryNote->state->value],
                ],
                'actions'    => $actions,
                $this->getInvoiceButton($deliveryNote)
            ],
            'tabs'          => [
                'current'    => $this->tab,
                'navigation' => DeliveryNoteTabsEnum::navigation($deliveryNote)
            ],
            'delivery_note' => DeliveryNoteResource::make($deliveryNote)->toArray(request()),

            'address' => [
                'delivery' => AddressResource::make($deliveryNote->deliveryAddress ?? new Address()),
                'options'  => [
                    'countriesAddressData' => GetAddressData::run()
                ]
            ],

            'timelines' => $this->getTimeline($deliveryNote),
            'box_stats' => $this->getBoxStats($deliveryNote),
            'notes'              => $this->getDeliveryNoteNotes($deliveryNote),
            'quick_pickers'       => $this->quickGetPickers(),
            'routes'              => [
                'update'         => [
                    'name'       => 'grp.models.delivery_note.update',
                    'parameters' => [
                        'deliveryNote' => $deliveryNote->id
                    ]
                ],
                'set_queue'      => [
                    'method'     => 'patch',
                    'name'       => 'grp.models.delivery_note.state.in_queue',
                    'parameters' => [
                        'deliveryNote' => $deliveryNote->id
                    ]
                ],
                'pickers_list'   => [
                    'name'       => 'grp.json.employees.picker_users',
                    'parameters' => [
                        'organisation' => $deliveryNote->organisation->slug
                    ]
                ],
                'packers_list'   => [
                    'name'       => 'grp.json.employees.packers',
                    'parameters' => [
                        'organisation' => $deliveryNote->organisation->slug
                    ]
                ],
                'exportPdfRoute' => [
                    'name'       => 'grp.org.accounting.invoices.download',
                    'parameters' => [
                        'organisation' => $deliveryNote->organisation->slug,
                        'invoice'      => $deliveryNote->slug
                    ]
                ],
            ],
            'delivery_note_state' => [
                'value' => $deliveryNote->state,
                'label' => $deliveryNote->state->labels()[$deliveryNote->state->value],
            ],
            'shipments_routes'           => [
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


        ];


        $props = array_merge($props, $this->getItems($deliveryNote));


        $inertiaResponse = Inertia::render(
            'Org/Dispatching/DeliveryNote',
            $props
        );

        if ($deliveryNote->state == DeliveryNoteStateEnum::UNASSIGNED || $deliveryNote->state == DeliveryNoteStateEnum::QUEUED) {
            $inertiaResponse->table(IndexDeliveryNoteItemsStateUnassigned::make()->tableStructure(deliveryNote: $deliveryNote, prefix: DeliveryNoteTabsEnum::ITEMS->value));
        } elseif ($deliveryNote->state == DeliveryNoteStateEnum::HANDLING) {
            $inertiaResponse->table(IndexDeliveryNoteItemsStateHandling::make()->tableStructure(prefix: DeliveryNoteTabsEnum::ITEMS->value));
        } else {
            $inertiaResponse->table(IndexDeliveryNoteItems::make()->tableStructure(parent: $deliveryNote, prefix: DeliveryNoteTabsEnum::ITEMS->value));
        }


        return $inertiaResponse;
    }

    public function getItems(DeliveryNote $deliveryNote): array
    {
        if ($deliveryNote->state == DeliveryNoteStateEnum::UNASSIGNED || $deliveryNote->state == DeliveryNoteStateEnum::QUEUED) {
            return [
                DeliveryNoteTabsEnum::ITEMS->value => $this->tab == DeliveryNoteTabsEnum::ITEMS->value ?
                    fn () => DeliveryNoteItemsStateUnassignedResource::collection(IndexDeliveryNoteItemsStateUnassigned::run($deliveryNote))
                    : Inertia::lazy(fn () => DeliveryNoteItemsStateUnassignedResource::collection(IndexDeliveryNoteItemsStateUnassigned::run($deliveryNote))),

            ];
        } elseif ($deliveryNote->state == DeliveryNoteStateEnum::HANDLING) {
            return [
                DeliveryNoteTabsEnum::ITEMS->value => $this->tab == DeliveryNoteTabsEnum::ITEMS->value ?
                    fn () => DeliveryNoteItemsStateHandlingResource::collection(IndexDeliveryNoteItemsStateHandling::run($deliveryNote))
                    : Inertia::lazy(fn () => DeliveryNoteItemsStateHandlingResource::collection(IndexDeliveryNoteItemsStateHandling::run($deliveryNote))),

            ];
        }

        return [
            DeliveryNoteTabsEnum::ITEMS->value => $this->tab == DeliveryNoteTabsEnum::ITEMS->value ?
                fn () => DeliveryNoteItemsResource::collection(IndexDeliveryNoteItems::run($deliveryNote))
                : Inertia::lazy(fn () => DeliveryNoteItemsResource::collection(IndexDeliveryNoteItems::run($deliveryNote))),

        ];
    }

    public function getDeliveryNoteNotes(DeliveryNote $deliveryNote): array
    {
        return [
            "note_list" => [
                [
                    "label"    => __("Delivery Instructions"),
                    "note"     => $deliveryNote->shipping_notes ?? '',
                    "information" => __("This note will be printed in the shipping label. Both customer and staff can edit this note."),
                    "editable" => true,
                    "bgColor"  => "#38bdf8",
                    "field"    => "shipping_notes"
                ],
                [
                    "label"    => __("Customer"),
                    "note"     => $deliveryNote->customer_notes ?? '',
                    "information" => __("This note is from customer in the platform. Not editable."),
                    "editable" => false,
                    "bgColor"  => "#FF7DBD",
                    "field"    => "customer_notes"
                ],
                [
                    "label"    => __("Public"),
                    "note"     => $deliveryNote->public_notes ?? '',
                    "information" => __("This note will be visible to public, both staff and the customer can see."),
                    "editable" => true,
                    "bgColor"  => "#94DB84",
                    "field"    => "public_notes"
                ],
                [
                    "label"    => __("Private"),
                    "note"     => $deliveryNote->internal_notes ?? '',
                    "information" => __("This note is only visible to staff members. You can communicate each other about this delivery note."),
                    "editable" => true,
                    "bgColor"  => "#FCF4A3",
                    "field"    => "internal_notes"
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
