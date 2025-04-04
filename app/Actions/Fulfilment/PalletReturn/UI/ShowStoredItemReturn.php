<?php

/*
 * author Arya Permana - Kirin
 * created on 10-02-2025-08h-58m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Fulfilment\PalletReturn\UI;

use App\Actions\Fulfilment\Fulfilment\UI\ShowFulfilment;
use App\Actions\Fulfilment\FulfilmentCustomer\ShowFulfilmentCustomer;
use App\Actions\Fulfilment\StoredItem\UI\IndexStoredItemsInReturn;
use App\Actions\Fulfilment\WithFulfilmentCustomerSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithFulfilmentShopAuthorisation;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Enums\UI\Fulfilment\PalletReturnTabsEnum;
use App\Http\Resources\Fulfilment\FulfilmentCustomerResource;
use App\Http\Resources\Fulfilment\PalletReturnResource;
use App\Http\Resources\Fulfilment\PalletReturnsResource;
use App\Http\Resources\Helpers\AddressResource;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\PalletReturn;
use App\Actions\Helpers\Country\UI\GetAddressData;
use App\Actions\Helpers\Media\UI\IndexAttachments;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Enums\Fulfilment\RecurringBill\RecurringBillStatusEnum;
use App\Http\Resources\Fulfilment\FulfilmentTransactionsResource;
use App\Http\Resources\Fulfilment\PalletReturnItemsWithStoredItemsResource;
use App\Http\Resources\Helpers\Attachment\AttachmentsResource;
use App\Http\Resources\Helpers\CurrencyResource;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowStoredItemReturn extends OrgAction
{
    use WithFulfilmentShopAuthorisation;
    use WithFulfilmentCustomerSubNavigation;

    private FulfilmentCustomer|Fulfilment $parent;

    public function handle(PalletReturn $palletReturn): PalletReturn
    {
        return $palletReturn;
    }


    public function asController(Organisation $organisation, Fulfilment $fulfilment, PalletReturn $palletReturn, ActionRequest $request): PalletReturn
    {
        $this->parent = $fulfilment;
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(PalletReturnTabsEnum::values());

        return $this->handle($palletReturn);
    }


    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilmentCustomer(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, PalletReturn $palletReturn, ActionRequest $request): PalletReturn
    {
        $this->parent = $fulfilmentCustomer;
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(PalletReturnTabsEnum::values());

        return $this->handle($palletReturn);
    }

    public function htmlResponse(PalletReturn $palletReturn, ActionRequest $request): Response
    {
        $subNavigation = [];
        if ($this->parent instanceof FulfilmentCustomer) {
            $subNavigation = $this->getFulfilmentCustomerSubNavigation($this->parent, $request);
        }

        $actions    = [];
        $navigation = PalletReturnTabsEnum::navigation($palletReturn);
        unset($navigation[PalletReturnTabsEnum::PALLETS->value]);
        $this->tab = $request->get('tab', array_key_first($navigation));


        if ($palletReturn->storedItems()->count() < 1) {
            $tooltipSubmit = !($palletReturn->estimated_delivery_date) ? __('Select estimated date before submit') : __('Select stored item before submit');
            // } elseif ($palletReturn->delivery_address_id === null && $palletReturn->collection_address_id === null) {
            //     $tooltipSubmit = __('Select address before submit');
            //     $isDisabled = true;
            // } else {
            // $tooltipSubmit = __('Confirm');
            $isDisabled    = true;
        } else {
            $tooltipSubmit = !($palletReturn->estimated_delivery_date) ? __('Select estimated date before submit') : __('Confirm');
            $isDisabled    = !($palletReturn->estimated_delivery_date);
        }
        $buttonSubmit = [
            'type'     => 'button',
            'style'    => 'save',
            'tooltip'  => $tooltipSubmit,
            'key'      => 'submit-stored-items',
            'route'    => [
                'method'     => 'post',
                'name'       => 'grp.models.fulfilment-customer.pallet-return.submit_and_confirm',
                'parameters' => [
                    'fulfilmentCustomer' => $palletReturn->fulfilmentCustomer->id,
                    'palletReturn'       => $palletReturn->id
                ]
            ],
            'disabled' => $isDisabled
        ];
        // dd($buttonSubmit);
        if ($this->canEdit) {
            $actions = $palletReturn->state == PalletReturnStateEnum::IN_PROCESS
                ? [
                    [
                        'type'    => 'button',
                        'style'   => 'tertiary',
                        'icon'    => 'fal fa-upload',
                        'label'   => __('upload'),
                        'tooltip' => __('Upload file')
                    ],
                    [
                        'type'   => 'buttonGroup',
                        'key'    => 'upload-add',
                        'button' => [

                            [
                                'type'    => 'button',
                                'style'   => 'secondary',
                                'icon'    => 'fal fa-plus',
                                'label'   => __('add service'),
                                'key'     => 'add-service',
                                'tooltip' => __('Add single service'),
                                'route'   => [
                                    'name'       => 'grp.models.pallet-return.transaction.store',
                                    'parameters' => [
                                        'palletReturn' => $palletReturn->id
                                    ]
                                ]
                            ],
                            [
                                'type'    => 'button',
                                'style'   => 'secondary',
                                'icon'    => 'fal fa-plus',
                                'key'     => 'add-physical-good',
                                'label'   => __('add physical good'),
                                'tooltip' => __('Add physical good'),
                                'route'   => [
                                    'name'       => 'grp.models.pallet-return.transaction.store',
                                    'parameters' => [
                                        'palletReturn' => $palletReturn->id
                                    ]
                                ]
                            ],
                        ]
                    ],
                    $buttonSubmit,
                ]
                : [
                    $palletReturn->state == PalletReturnStateEnum::SUBMITTED ? [
                        'type'    => 'button',
                        'style'   => 'save',
                        'tooltip' => __('confirm'),
                        'label'   => __('confirm'),
                        'key'     => 'confirm',
                        'route'   => [
                            'method'     => 'post',
                            'name'       => 'grp.models.fulfilment-customer.pallet-return.confirm',
                            'parameters' => [
                                'organisation'       => $palletReturn->organisation->slug,
                                'fulfilment'         => $palletReturn->fulfilment->slug,
                                'fulfilmentCustomer' => $palletReturn->fulfilmentCustomer->id,
                                'palletReturn'       => $palletReturn->id
                            ]
                        ]
                    ] : [],
                    $palletReturn->state == PalletReturnStateEnum::CONFIRMED ? [
                        'type'    => 'button',
                        'style'   => 'save',
                        'tooltip' => __('Start picking'),
                        'label'   => __('start picking'),
                        'key'     => 'start picking',
                        'route'   => [
                            'method'     => 'post',
                            'name'       => 'grp.models.fulfilment-customer.pallet-return.picking',
                            'parameters' => [
                                'organisation'       => $palletReturn->organisation->slug,
                                'fulfilment'         => $palletReturn->fulfilment->slug,
                                'fulfilmentCustomer' => $palletReturn->fulfilmentCustomer->id,
                                'palletReturn'       => $palletReturn->id
                            ]
                        ]
                    ] : [],
                    // $palletReturn->state == PalletReturnStateEnum::PICKING ?
                    // [
                    // 'type'    => 'button',
                    // 'style'   => 'save',
                    // 'tooltip' => __('Set all pending as picked'),
                    // 'label'   => __('pick all*'),
                    // 'key'     => 'pick all',
                    // 'route'   => [
                    //     'method'     => 'post',
                    //     'name'       => 'grp.models.pallet-return.pick_all_with_stored_items',
                    //     'parameters' => [
                    //         'palletReturn'       => $palletReturn->id
                    //         ]
                    //     ]
                    // ] : [],
                    $palletReturn->state == PalletReturnStateEnum::PICKED ? [
                        'type'    => 'button',
                        'style'   => 'save',
                        'tooltip' => __('Set as dispatched'),
                        'label'   => __('Dispatch'),
                        'key'     => 'Dispatching',
                        'route'   => [
                            'method'     => 'post',
                            'name'       => 'grp.models.pallet-return.dispatch',
                            'parameters' => [
                                'palletReturn' => $palletReturn->id
                            ]
                        ]
                    ] : [],
                    $palletReturn->state == PalletReturnStateEnum::DISPATCHED && $palletReturn->recurringBill->status == RecurringBillStatusEnum::CURRENT ? [
                        'type'   => 'buttonGroup',
                        'key'    => 'upload-add',
                        'button' => [
                            [
                                'type'    => 'button',
                                'style'   => 'secondary',
                                'icon'    => 'fal fa-plus',
                                'label'   => __('add service'),
                                'key'     => 'add-service',
                                'tooltip' => __('Add single service'),
                                'route'   => [
                                    'name'       => 'grp.models.pallet-return.transaction.store',
                                    'parameters' => [
                                        'palletReturn' => $palletReturn->id
                                    ]
                                ]
                            ],
                            [
                                'type'    => 'button',
                                'style'   => 'secondary',
                                'icon'    => 'fal fa-plus',
                                'key'     => 'add-physical-good',
                                'label'   => __('add physical good'),
                                'tooltip' => __('Add physical good'),
                                'route'   => [
                                    'name'       => 'grp.models.pallet-return.transaction.store',
                                    'parameters' => [
                                        'palletReturn' => $palletReturn->id
                                    ]
                                ]
                            ],
                        ]
                    ] : []
                ];

            $pdfButton = [
                'type'   => 'button',
                'style'  => 'tertiary',
                'label'  => 'PDF',
                'target' => '_blank',
                'icon'   => 'fal fa-file-pdf',
                'key'    => 'action',
                'route'  => [
                    'name'       => 'grp.models.pallet-return.pdf',
                    'parameters' => [
                        'palletReturn' => $palletReturn->id
                    ]
                ]
            ];

            if (in_array($palletReturn->state, [
                PalletReturnStateEnum::IN_PROCESS,
                PalletReturnStateEnum::SUBMITTED
            ])) {
                $actions = array_merge([
                    [
                        'type'    => 'button',
                        'style'   => 'delete',
                        'tooltip' => __('delete'),
                        'key'     => 'delete_return',
                        'route'   => [
                            'method'     => 'patch',
                            'name'       => 'grp.models.pallet-return.delete',
                            'parameters' => [
                                'palletReturn' => $palletReturn->id
                            ]
                        ]
                    ]
                ], $actions);
            } else {
                $actions = array_merge($actions, [$pdfButton]);
            }
        }

        $afterTitle = [
            'label' => '('.__("Customer's SKUs").')'
        ];

        $showGrossAndDiscount = $palletReturn->gross_amount !== $palletReturn->net_amount;

        $downloadRoute = 'grp.org.fulfilments.show.crm.customers.show.pallet_returns.pallets.stored-items.export';

        $recurringBillData = null;
        if ($palletReturn->recurringBill) {
            $recurringBill = $palletReturn->recurringBill;

            if ($this->parent instanceof Fulfilment) {
                $route = [
                    'name'       => 'grp.org.fulfilments.show.operations.recurring_bills.current.show',
                    'parameters' => [
                        'organisation'  => $recurringBill->organisation->slug,
                        'fulfilment'    => $this->parent->slug,
                        'recurringBill' => $recurringBill->slug
                    ]
                ];
            } elseif ($this->parent instanceof FulfilmentCustomer) {
                $route = [
                    'name'       => 'grp.org.fulfilments.show.crm.customers.show.recurring_bills.show',
                    'parameters' => [
                        'organisation'       => $recurringBill->organisation->slug,
                        'fulfilment'         => $this->parent->fulfilment->slug,
                        'fulfilmentCustomer' => $this->parent->slug,
                        'recurringBill'      => $recurringBill->slug
                    ]
                ];
            }
            $recurringBillData = [
                'reference'    => $recurringBill->reference,
                'status'       => $recurringBill->status,
                'total_amount' => $recurringBill->total_amount,
                'route'        => $route
            ];
        }

        $addresses = $palletReturn->fulfilmentCustomer->customer->addresses;

        $processedAddresses = $addresses->map(function ($address) {
            if (!DB::table('model_has_addresses')->where('address_id', $address->id)->where('model_type', '=', 'Customer')->exists()) {
                return $address->setAttribute('can_delete', false)
                    ->setAttribute('can_edit', true);
            }


            return $address->setAttribute('can_delete', true)
                ->setAttribute('can_edit', true);
        });

        $customerAddressId         = $palletReturn->fulfilmentCustomer->customer->address->id;
        $customerDeliveryAddressId = $palletReturn->fulfilmentCustomer->customer->deliveryAddress->id;
        $palletReturnDeliveryAddressIds   = PalletReturn::where('fulfilment_customer_id', $palletReturn->fulfilment_customer_id)
            ->pluck('delivery_address_id')
            ->unique()
            ->toArray();

        $forbiddenAddressIds = array_merge(
            $palletReturnDeliveryAddressIds,
            [$customerAddressId, $customerDeliveryAddressId]
        );

        $processedAddresses->each(function ($address) use ($forbiddenAddressIds) {
            if (in_array($address->id, $forbiddenAddressIds, true)) {
                $address->setAttribute('can_delete', false)
                    ->setAttribute('can_edit', true);
            }
        });

        $addressCollection = AddressResource::collection($processedAddresses);

        // dd($palletReturn->deliveryAddress);
        return Inertia::render(
            'Org/Fulfilment/PalletReturn',
            [
                'title'       => __('pallet return'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($palletReturn, $request),
                    'next'     => $this->getNext($palletReturn, $request),
                ],
                'pageHead'    => [
                    // 'container' => $container,
                    'subNavigation' => $subNavigation,
                    'title'         => $palletReturn->reference,
                    'model'         => __('return'),
                    'afterTitle'    => $afterTitle,
                    'icon'          => [
                        'icon'  => ['fal', 'fa-truck-couch'],
                        'title' => $palletReturn->reference
                    ],
                    'edit'          => $this->canEdit ? [
                        'route' => [
                            'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                            'parameters' => array_values($request->route()->originalParameters())
                        ]
                    ] : false,
                    'actions'       => $actions
                ],

                'interest' => [
                    'pallets_storage' => $palletReturn->fulfilmentCustomer->pallets_storage,
                    'items_storage'   => $palletReturn->fulfilmentCustomer->items_storage,
                    'dropshipping'    => $palletReturn->fulfilmentCustomer->dropshipping,
                ],

                'updateRoute' => [
                    'name'       => 'grp.models.pallet-return.update',
                    'parameters' => [
                        'palletReturn' => $palletReturn->id
                    ]
                ],

                'deleteServiceRoute' => [
                    'name'       => 'org.models.pallet-return.service.delete',
                    'parameters' => [
                        'palletReturn' => $palletReturn->id
                    ]
                ],

                'deletePhysicalGoodRoute' => [
                    'name'       => 'org.models.pallet-return.physical_good.delete',
                    'parameters' => [
                        'palletReturn' => $palletReturn->id
                    ]
                ],

                'routeStorePallet' => [
                    'name'       => 'grp.models.pallet-return.pallet.store',
                    'parameters' => [
                        'palletReturn' => $palletReturn->id
                    ]
                ],
                'attachmentRoutes' => [
                    'attachRoute' => [
                        'name'       => 'grp.models.pallet-return.attachment.attach',
                        'parameters' => [
                            'palletReturn' => $palletReturn->id,
                        ],
                        'method'     => 'post'
                    ],
                    'detachRoute' => [
                        'name'       => 'grp.models.pallet-return.attachment.detach',
                        'parameters' => [
                            'palletReturn' => $palletReturn->id,
                        ],
                        'method'     => 'delete'
                    ]
                ],

                'tabs'       => [
                    'current'    => $this->tab,
                    'navigation' => $navigation
                ],
                'data'       => PalletReturnResource::make($palletReturn),
                'address_update_route'  => [
                    'method'     => 'patch',
                    'name'       => 'retina.models.customer.address.update',
                    'parameters' => [
                        'customer' => $palletReturn->fulfilmentCustomer->customer_id
                    ]
                ],
                'addresses'   => [
                    'isCannotSelect'                => true,
                    'address_list'                  => $addressCollection,
                    'options'                       => [
                        'countriesAddressData' => GetAddressData::run()
                    ],
                    'pinned_address_id'              => $palletReturn->fulfilmentCustomer->customer->delivery_address_id,
                    'home_address_id'                => $palletReturn->fulfilmentCustomer->customer->address_id,
                    'current_selected_address_id'    => $palletReturn->delivery_address_id,
                    'selected_delivery_addresses_id' => $palletReturnDeliveryAddressIds,
                    'routes_list'                    => [
                        'switch_route'                   => [
                            'method'     => 'patch',
                            'name'       => 'grp.models.pallet-return.address.switch',
                            'parameters' => [
                                'palletReturn' => $palletReturn->id
                            ]
                        ],
                        'pinned_route'                   => [
                            'method'     => 'patch',
                            'name'       => 'grp.models.customer.delivery-address.update',
                            'parameters' => [
                                'customer' => $palletReturn->fulfilmentCustomer->customer_id
                            ]
                        ],
                        'delete_route'  => [
                            'method'     => 'delete',
                            'name'       => 'grp.models.customer.delivery-address.delete',
                            'parameters' => [
                                'customer' => $palletReturn->fulfilmentCustomer->customer_id
                            ]
                        ],
                        'store_route' => [
                            'method'      => 'post',
                            'name'        => 'grp.models.customer.address.store',
                            'parameters'  => [
                                'customer' => $palletReturn->fulfilmentCustomer->customer_id
                            ]
                        ]
                    ]
                ],
                'box_stats'  => [
                    'collection_notes'  => $palletReturn->collection_notes ?? '',
                    'recurring_bill'      => $recurringBillData,
                    'fulfilment_customer' => array_merge(
                        FulfilmentCustomerResource::make($palletReturn->fulfilmentCustomer)->getArray(),
                        [
                            'address' => [
                                'value'                       => $palletReturn->is_collection
                                    ?
                                    null
                                    :
                                    AddressResource::make($palletReturn->deliveryAddress),
                                'options'                     => [
                                    'countriesAddressData' => GetAddressData::run()
                                ],
                                'address_customer'            => [
                                    'value'   => AddressResource::make($palletReturn->fulfilmentCustomer->customer->address),
                                    'options' => [
                                        'countriesAddressData' => GetAddressData::run()
                                    ],
                                ],
                                'pinned_address_id'           => $palletReturn->fulfilmentCustomer->customer->delivery_address_id,
                                'home_address_id'             => $palletReturn->fulfilmentCustomer->customer->address_id,
                                'current_selected_address_id' => $palletReturn->delivery_address_id,
                                'routes_address'              => [
                                    'store'  => [
                                        'method'     => 'post',
                                        'name'       => 'grp.models.pallet-return.address.store',
                                        'parameters' => [
                                            'palletReturn' => $palletReturn->id
                                        ]
                                    ],
                                    'delete' => [
                                        'method'     => 'delete',
                                        'name'       => 'grp.models.pallet-return.address.delete',
                                        'parameters' => [
                                            'palletReturn' => $palletReturn->id
                                        ]
                                    ],
                                    'update' => [
                                        'method'     => 'patch',
                                        'name'       => 'grp.models.pallet-return.address.update',
                                        'parameters' => [
                                            'palletReturn' => $palletReturn->id
                                        ]
                                    ]
                                ]
                            ],
                        ]
                    ),
                    'delivery_state'      => PalletReturnStateEnum::stateIcon()[$palletReturn->state->value],
                    'order_summary'       => [
                        [

                            // [
                            //     'label'         => __('Pallets'),
                            //     'quantity'      => $palletReturn->stats->number_pallets ?? 0,
                            //     'price_base'    => '',
                            //     'price_total'   => ''
                            // ],
                            [
                                'label'       => __('Services'),
                                'quantity'    => $palletReturn->stats->number_services ?? 0,
                                'price_base'  => '',
                                'price_total' => $palletReturn->services_amount
                            ],
                            [
                                'label'       => __('Physical Goods'),
                                'quantity'    => $palletReturn->stats->number_physical_goods ?? 0,
                                'price_base'  => '',
                                'price_total' => $palletReturn->goods_amount
                            ],

                        ],
                        $showGrossAndDiscount ? [
                            [
                                'label'       => __('Gross'),
                                'information' => '',
                                'price_total' => $palletReturn->gross_amount
                            ],
                            [
                                'label'       => __('Discounts'),
                                'information' => '',
                                'price_total' => $palletReturn->discount_amount
                            ],
                        ] : [],
                        $showGrossAndDiscount
                            ? [
                            [
                                'label'       => __('Net'),
                                'information' => '',
                                'price_total' => $palletReturn->net_amount
                            ],
                            [
                                'label'       => __('Tax'),
                                'information' => '',
                                'price_total' => $palletReturn->tax_amount
                            ],
                        ]
                            : [
                            [
                                'label'       => __('Net'),
                                'information' => '',
                                'price_total' => $palletReturn->net_amount
                            ],
                            [
                                'label'       => __('Tax'),
                                'information' => '',
                                'price_total' => $palletReturn->tax_amount
                            ],
                        ],
                        [
                            [
                                'label'       => __('Total'),
                                'price_total' => $palletReturn->total_amount
                            ],
                        ],
                        'currency' => CurrencyResource::make($palletReturn->currency),
                        // 'currency_code'                => 'usd',  // TODO
                        // 'number_pallets'               => $palletReturn->stats->number_pallets,
                        // 'number_services'              => $palletReturn->stats->number_services,
                        // 'number_physical_goods'        => $palletReturn->stats->number_physical_goods,
                        // 'pallets_price'                => 0,  // TODO
                        // 'physical_goods_price'         => 0,  // TODO
                        // 'services_price'               => 0,  // TODO
                        // 'total_pallets_price'          => 0,  // TODO
                        // 'total_services_price'         => $palletReturn->stats->total_services_price,
                        // 'total_physical_goods_price'   => $palletReturn->stats->total_physical_goods_price,
                        // 'shipping'                     => [
                        //     'tooltip'           => __('Shipping fee to your address using DHL service.'),
                        //     'fee'               => 11111, // TODO
                        // ],
                        // 'tax'                      => [
                        //     'tooltip'           => __('Tax is based on 10% of total order.'),
                        //     'fee'               => 99999, // TODO
                        // ],
                        // 'total_price'                  => $palletReturn->stats->total_price
                    ]
                ],
                'notes_data' => [
                    [
                        'label'    => __('Customer'),
                        'note'     => $palletReturn->customer_notes ?? '',
                        'editable' => false,
                        'bgColor'  => 'blue',
                        'field'    => 'customer_notes'
                    ],
                    [
                        'label'    => __('Public'),
                        'note'     => $palletReturn->public_notes ?? '',
                        'editable' => true,
                        'bgColor'  => 'pink',
                        'field'    => 'public_notes'
                    ],
                    [
                        'label'    => __('Private'),
                        'note'     => $palletReturn->internal_notes ?? '',
                        'editable' => true,
                        'bgColor'  => 'purple',
                        'field'    => 'internal_notes'
                    ],
                ],

                'service_list_route'       => [
                    'name'       => 'grp.json.fulfilment.return.services.index',
                    'parameters' => [
                        'fulfilment' => $palletReturn->fulfilment->slug,
                        'scope'      => $palletReturn->slug
                    ]
                ],
                'physical_good_list_route' => [
                    'name'       => 'grp.json.fulfilment.return.physical-goods.index',
                    'parameters' => [
                        'fulfilment' => $palletReturn->fulfilment->slug,
                        'scope'      => $palletReturn->slug
                    ]
                ],

                'route_check_stored_items' => [
                    'method'     => 'post',
                    'name'       => 'grp.models.pallet-return.stored_item.store',
                    'parameters' => [
                        $palletReturn->id
                    ]
                ],

                'can_edit_transactions' => true,
                'option_attach_file'    => [
                    [
                        'name' => __('Other'),
                        'code' => 'Other'
                    ]
                ],
                'stored_items_count'    => $palletReturn->storedItems()->count(),

                PalletReturnTabsEnum::STORED_ITEMS->value => $this->tab == PalletReturnTabsEnum::STORED_ITEMS->value ?
                    fn () => PalletReturnItemsWithStoredItemsResource::collection(IndexStoredItemsInReturn::run($palletReturn, PalletReturnTabsEnum::STORED_ITEMS->value)) //todo idk if this is right
                    : Inertia::lazy(fn () => PalletReturnItemsWithStoredItemsResource::collection(IndexStoredItemsInReturn::run($palletReturn, PalletReturnTabsEnum::STORED_ITEMS->value))), //todo idk if this is right

                PalletReturnTabsEnum::SERVICES->value => $this->tab == PalletReturnTabsEnum::SERVICES->value ?
                    fn () => FulfilmentTransactionsResource::collection(IndexServiceInPalletReturn::run($palletReturn))
                    : Inertia::lazy(fn () => FulfilmentTransactionsResource::collection(IndexServiceInPalletReturn::run($palletReturn))),

                PalletReturnTabsEnum::PHYSICAL_GOODS->value => $this->tab == PalletReturnTabsEnum::PHYSICAL_GOODS->value ?
                    fn () => FulfilmentTransactionsResource::collection(IndexPhysicalGoodInPalletReturn::run($palletReturn))
                    : Inertia::lazy(fn () => FulfilmentTransactionsResource::collection(IndexPhysicalGoodInPalletReturn::run($palletReturn))),

                PalletReturnTabsEnum::ATTACHMENTS->value => $this->tab == PalletReturnTabsEnum::ATTACHMENTS->value ?
                    fn () => AttachmentsResource::collection(IndexAttachments::run($palletReturn, PalletReturnTabsEnum::ATTACHMENTS->value))
                    : Inertia::lazy(fn () => AttachmentsResource::collection(IndexAttachments::run($palletReturn, PalletReturnTabsEnum::ATTACHMENTS->value))),
            ]
        )->table(
            IndexStoredItemsInReturn::make()->tableStructure(
                $palletReturn,
                request: $request,
                prefix: PalletReturnTabsEnum::STORED_ITEMS->value
            )
        )->table(
            IndexServiceInPalletReturn::make()->tableStructure(
                $palletReturn,
                prefix: PalletReturnTabsEnum::SERVICES->value
            )
        )->table(
            IndexPhysicalGoodInPalletReturn::make()->tableStructure(
                $palletReturn,
                prefix: PalletReturnTabsEnum::PHYSICAL_GOODS->value
            )
        )->table(IndexAttachments::make()->tableStructure(PalletReturnTabsEnum::ATTACHMENTS->value));
    }


    public function jsonResponse(PalletReturn $palletReturn): PalletReturnsResource
    {
        return new PalletReturnsResource($palletReturn);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, $suffix = ''): array
    {
        $headCrumb = function (PalletReturn $palletReturn, array $routeParameters, string $suffix) {
            return [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Pallet returns')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $palletReturn->reference,
                        ],

                    ],
                    'suffix'         => $suffix
                ],
            ];
        };

        $palletReturn = PalletReturn::where('slug', $routeParameters['palletReturn'])->first();

        return match ($routeName) {
            'grp.org.fulfilments.show.crm.customers.show.pallet_returns.with_stored_items.show' => array_merge(
                ShowFulfilmentCustomer::make()->getBreadcrumbs(Arr::only($routeParameters, ['organisation', 'fulfilment', 'fulfilmentCustomer'])),
                $headCrumb(
                    $palletReturn,
                    [
                        'index' => [
                            'name'       => 'grp.org.fulfilments.show.crm.customers.show.pallet_returns.index',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment', 'fulfilmentCustomer'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.fulfilments.show.crm.customers.show.pallet_returns.with_stored_items.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment', 'fulfilmentCustomer', 'palletReturn'])
                        ]
                    ],
                    $suffix
                )
            ),
            'grp.org.fulfilments.show.operations.pallet-return-with-stored-items.show' => array_merge(
                ShowFulfilment::make()->getBreadcrumbs(Arr::only($routeParameters, ['organisation', 'fulfilment'])),
                $headCrumb(
                    $palletReturn,
                    [
                        'index' => [
                            'name'       => 'grp.org.fulfilments.show.operations.pallet-returns.index',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment', 'palletReturn'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.fulfilments.show.operations.pallet-return-with-stored-items.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment', 'palletReturn'])
                        ]
                    ],
                    $suffix
                )
            ),


            default => []
        };
    }

    public function getPrevious(PalletReturn $palletReturn, ActionRequest $request): ?array
    {
        if ($this->parent instanceof FulfilmentCustomer) {
            $previous = PalletReturn::where('fulfilment_customer_id', $this->parent->id)->where('id', '<', $palletReturn->id)->where('type', PalletReturnTypeEnum::STORED_ITEM)->orderBy('id', 'desc')->first();
        } elseif ($this->parent instanceof Fulfilment) {
            $previous = PalletReturn::where('fulfilment_id', $this->parent->id)->where('id', '<', $palletReturn->id)->where('type', PalletReturnTypeEnum::STORED_ITEM)->orderBy('id', 'desc')->first();
        } else {
            $previous = PalletReturn::where('id', '<', $palletReturn->id)->where('type', PalletReturnTypeEnum::STORED_ITEM)->orderBy('id', 'desc')->first();
        }

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(PalletReturn $palletReturn, ActionRequest $request): ?array
    {
        if ($this->parent instanceof FulfilmentCustomer) {
            $next = PalletReturn::where('fulfilment_customer_id', $this->parent->id)->where('id', '>', $palletReturn->id)->where('type', PalletReturnTypeEnum::PALLET)->orderBy('id')->first();
        } elseif ($this->parent instanceof Fulfilment) {
            $next = PalletReturn::where('fulfilment_id', $this->parent->id)->where('id', '>', $palletReturn->id)->where('type', PalletReturnTypeEnum::PALLET)->orderBy('id')->first();
        } else {
            $next = PalletReturn::where('id', '>', $palletReturn->id)->where('type', PalletReturnTypeEnum::PALLET)->orderBy('id')->first();
        }


        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?PalletReturn $palletReturn, string $routeName): ?array
    {
        if (!$palletReturn) {
            return null;
        }


        return match (class_basename($this->parent)) {
            'FulfilmentCustomer' => [
                'label' => $palletReturn->reference,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation'       => $palletReturn->organisation->slug,
                        'fulfilment'         => $palletReturn->fulfilment->slug,
                        'fulfilmentCustomer' => $palletReturn->fulfilmentCustomer->slug,
                        'palletReturn'       => $palletReturn->reference
                    ]

                ]
            ],
            default => []
        };
    }
}
