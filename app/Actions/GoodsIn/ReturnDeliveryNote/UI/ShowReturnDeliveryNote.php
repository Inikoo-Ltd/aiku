<?php

/*
 * author Louis Perez
 * created on 28-04-2026-13h-56m
 * GitHub: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\GoodsIn\ReturnDeliveryNote\UI;

use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\Dispatching\Picking\Picker\Json\GetPickerUsers;
use App\Actions\GoodsIn\ReturnDeliveryNoteItem\IndexReturnDeliveryNoteItems;
use App\Actions\Helpers\Country\UI\GetAddressData;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\OrgAction;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Actions\Retina\UI\Layout\GetPlatformLogo;
use App\Actions\UI\WithInertia;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\GoodsIn\ReturnDeliveryNote\ReturnDeliveryNoteStateEnum;
use App\Enums\GoodsIn\ReturnDeliveryNoteItem\ReturnDeliveryNoteItemStateEnum;
use App\Enums\UI\Dispatch\DeliveryNoteTabsEnum;
use App\Http\Resources\CRM\CustomerResource;
use App\Http\Resources\Dispatching\DeliveryNoteResource;
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

    /** @noinspection PhpUnusedParameterInspection */
    public function inShop(Organisation $organisation, Shop $shop, ReturnDeliveryNote $returnDeliveryNote, ActionRequest $request): ReturnDeliveryNote
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request)->withTab(DeliveryNoteTabsEnum::values());

        return $this->handle($returnDeliveryNote);
    }

    public function wrappedActions(ReturnDeliveryNote $returnDeliveryNote): array
    {
        $isEditable = false;
        if ($this->parent instanceof Warehouse) {
            $isEditable = true;
        }

        $showCancel = true;

        if (in_array($returnDeliveryNote->state, [
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

    public function getActions(ReturnDeliveryNote $returnDeliveryNote): array
    {
        $isEditable = false;
        if ($this->parent instanceof Warehouse) {
            $isEditable = true;
        } elseif ($this->parent instanceof Shop) {
            return match ($returnDeliveryNote->state) {
                ReturnDeliveryNoteStateEnum::RETURNED => [
                    [
                        'type'    => 'button',
                        'style'   => 'save',
                        'icon'    => 'fas fa-box-check',
                        'label'   => __('Finished Processing'),
                        'key'     => 'finish-processing',
                        'route'   => [
                            'method'        => 'patch',
                            'name'          => 'grp.models.return_delivery_note.state.done',
                            'parameters'    =>  [
                                'returnDeliveryNote'    => $returnDeliveryNote->id
                            ]
                        ],
                    ]
                ],
                default => []
            };
        }

        if (!$isEditable) {
            return [];
        }

        $hasUnHandledItems = ReturnDeliveryNoteItem::where('return_delivery_note_id', $returnDeliveryNote->id)
            ->where('total_expected_qty', '>', 0)
            ->where('is_handled', false)
            ->exists();

        return match ($returnDeliveryNote->state) {
            ReturnDeliveryNoteStateEnum::RECEIVED => [
                [
                    'type'    => 'button',
                    'style'   => 'save',
                    'icon'    => 'fas fa-warehouse-alt',
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
            ReturnDeliveryNoteStateEnum::RETURNING => $hasUnHandledItems ? [
                [
                    'type'   => 'buttonGroup',
                    'key'    => 'picker',
                    'button' => [
                        [
                            'type'    => 'button',
                            'style'   => 'delete',
                            'tooltip' => __('Remove handler from this Delivery Note'),
                            'label'   => __('Remove Handler'),
                            'icon'    => 'fal fa-user-slash',
                            'key'     => 'remove-handler',
                            'route'   => [
                                'method'     => 'patch',
                                'name'       => 'grp.models.return_delivery_note.unassign',
                                'parameters' => [
                                    'returnDeliveryNote' => $returnDeliveryNote->id
                                ]
                            ]
                        ],
                        [
                            'type'    => 'button',
                            'key'     => 'change-handler',
                        ]

                    ],
                ],
            ] : [
                [
                    'type'    => 'button',
                    'style'   => 'save',
                    'icon'    => 'fas fa-box-check',
                    'label'   => __('Set as Returned'),
                    'key'     => 'finish-return',
                    'route'   => [
                        'method'        => 'patch',
                        'name'          => 'grp.models.return_delivery_note.state.returned',
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

        return [
            'state'                        => $returnDeliveryNote->state,
            'state_icon'                   => ReturnDeliveryNoteStateEnum::stateIcon()[$returnDeliveryNote->state->value],
            'state_label'                  => $returnDeliveryNote->state->labels()[$returnDeliveryNote->state->value],
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
            'picker'                       => $returnDeliveryNote->handlerUser,
            'parcels'                      => $deliveryNote->parcels,
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
            'parentDeliveryNote'    => [
                'reference' => $deliveryNote->reference,
                'slug'      => $deliveryNote->slug,
                'id'        => $deliveryNote->id,
            ]
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

        $actions = $this->getActions($returnDeliveryNote);


        $model = __('Return');


        $showChangePickerPacker = $returnDeliveryNote->shop->type !== ShopTypeEnum::DROPSHIPPING;

        $returnDeliveryNote->returnDeliveryNoteItem;

        $props = [
            'title'         => __('Return').' '.$returnDeliveryNote->reference,
            'breadcrumbs'   => $this->getBreadcrumbs(
                $returnDeliveryNote,
                $request->route()->getName(),
                $request->route()->originalParameters(),
            ),
            'pageHead'      => [
                'title'           => $returnDeliveryNote->reference,
                'model'           => $model,
                'icon'            => [
                    'icon'  => 'fal fa-exchange',
                    'title' => __('Return Delivery note')
                ],
                'afterTitle'      => [
                    'label' => $returnDeliveryNote->state->labels()[$returnDeliveryNote->state->value],
                ],
                'actions'         => $actions,
                'wrapped_actions' => $this->wrappedActions($returnDeliveryNote),
            ],
            'isEditable'    => $isEditable,
            'tabs'          => [
                'current'    => $returnDeliveryNote->state == ReturnDeliveryNoteStateEnum::RETURNING
                    ? DeliveryNoteTabsEnum::PENDING_ITEMS->value
                    :
                    $this->tab,
                'navigation' => $returnDeliveryNote->state == ReturnDeliveryNoteStateEnum::RETURNING
                    ?
                    DeliveryNoteTabsEnum::navigation($returnDeliveryNote)
                    :
                    DeliveryNoteTabsEnum::navigationExcept($returnDeliveryNote, [DeliveryNoteTabsEnum::DONE_ITEMS, DeliveryNoteTabsEnum::PENDING_ITEMS])
            ],
            'address'             => [
                'delivery' => AddressResource::make($returnDeliveryNote->deliveryNote->deliveryAddress ?? new Address()),
                'options'  => [
                    'countriesAddressData' => GetAddressData::run()
                ]
            ],
            'timelines'           => $this->getTimeline($returnDeliveryNote),
            'box_stats'           => $this->getBoxStats($returnDeliveryNote),
            'notes'               => $this->getDeliveryNoteNotes($returnDeliveryNote),
            'quick_pickers'       => $this->quickGetPickers(),
            'returned_delivery_note_state' => [
                'value' => $returnDeliveryNote->state,
                'label' => $returnDeliveryNote->state->labels()[$returnDeliveryNote->state->value],
            ],
            'warehouse'           => [
                'slug' => $returnDeliveryNote->warehouse->slug,
            ],
            'organisation'          => [
                'slug' => $returnDeliveryNote->organisation->slug,
            ],

            'delivery_note'     => DeliveryNoteResource::make($returnDeliveryNote->deliveryNote)->toArray(request()),
            'dn_return'         => ReturnDeliveryNoteResource::make($returnDeliveryNote)->toArray(request()),
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
        if ($returnDeliveryNote->state == ReturnDeliveryNoteStateEnum::RETURNING) {
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

        if ($returnDeliveryNote->state == ReturnDeliveryNoteStateEnum::RETURNING) {
            $initArr = array_merge($initArr, [
                DeliveryNoteTabsEnum::PENDING_ITEMS->value => $this->tab == DeliveryNoteTabsEnum::PENDING_ITEMS->value ?
                    fn () => ReturnDeliveryNoteItemsResource::collection(IndexReturnDeliveryNoteItems::run($returnDeliveryNote, DeliveryNoteTabsEnum::PENDING_ITEMS->value, ReturnDeliveryNoteItemStateEnum::HANDLING))
                    : Inertia::lazy(fn () => ReturnDeliveryNoteItemsResource::collection(IndexReturnDeliveryNoteItems::run($returnDeliveryNote, DeliveryNoteTabsEnum::PENDING_ITEMS->value, ReturnDeliveryNoteItemStateEnum::HANDLING))),
                DeliveryNoteTabsEnum::DONE_ITEMS->value => $this->tab == DeliveryNoteTabsEnum::DONE_ITEMS->value ?
                    fn () => ReturnDeliveryNoteItemsResource::collection(IndexReturnDeliveryNoteItems::run($returnDeliveryNote, DeliveryNoteTabsEnum::DONE_ITEMS->value, ReturnDeliveryNoteItemStateEnum::PROCESSED))
                    : Inertia::lazy(fn () => ReturnDeliveryNoteItemsResource::collection(IndexReturnDeliveryNoteItems::run($returnDeliveryNote, DeliveryNoteTabsEnum::DONE_ITEMS->value, ReturnDeliveryNoteItemStateEnum::PROCESSED))),
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
                            'label' => __('Returns')
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
            'grp.org.shops.show.ordering.return_delivery_notes.show'
            => array_merge(
                ShowShop::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $returnDeliveryNote,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.ordering.return_delivery_notes.index',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'shop'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.ordering.return_delivery_notes.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'shop', 'returnDeliveryNote'])
                        ]
                    ],
                    $suffix
                ),
            ),
            'grp.org.warehouses.show.incoming.return_delivery_notes.show'
            => array_merge(
                ShowProcurementDashboard::make()->getBreadcrumbs(
                    Arr::only($routeParameters, ['organisation', 'warehouse'])
                ),
                $headCrumb(
                    $returnDeliveryNote,
                    [
                        'index' => [
                            'name'       => 'grp.org.warehouses.show.incoming.return_delivery_notes.index',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'warehouse'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.warehouses.show.incoming.return_delivery_notes.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'warehouse', 'returnDeliveryNote'])
                        ]
                    ],
                    $suffix
                ),
            ),
            default => []
        };
    }

}
