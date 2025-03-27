<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Mar 2023 17:41:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Fulfilment\PalletReturn\UI;

use App\Actions\Fulfilment\PalletReturn\UI\IndexPhysicalGoodInPalletReturn;
use App\Actions\Fulfilment\PalletReturn\UI\IndexServiceInPalletReturn;
use App\Actions\Helpers\Country\UI\GetAddressData;
use App\Actions\Helpers\Media\UI\IndexAttachments;
use App\Actions\Retina\Fulfilment\UI\ShowRetinaStorageDashboard;
use App\Actions\RetinaAction;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Enums\UI\Fulfilment\RetinaPalletReturnTabsEnum;
use App\Http\Resources\Fulfilment\FulfilmentCustomerResource;
use App\Http\Resources\Fulfilment\FulfilmentTransactionsResource;
use App\Http\Resources\Fulfilment\PalletReturnItemsUIResource;
use App\Http\Resources\Fulfilment\PalletReturnResource;
use App\Http\Resources\Fulfilment\PalletReturnsResource;
use App\Http\Resources\Helpers\AddressResource;
use App\Http\Resources\Helpers\Attachment\AttachmentsResource;
use App\Http\Resources\Helpers\CurrencyResource;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Helpers\Address;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowRetinaPalletReturn extends RetinaAction
{
    private FulfilmentCustomer $parent;

    public function handle(PalletReturn $palletReturn): PalletReturn
    {
        return $palletReturn;
    }


    public function authorize(ActionRequest $request): bool
    {
        if ($this->customer->id == $request->route()->parameter('palletReturn')->fulfilmentCustomer->customer_id) {
            return true;
        }

        return false;
    }


    public function asController(PalletReturn $palletReturn, ActionRequest $request): PalletReturn
    {
        $this->parent = $request->user()->customer->fulfilmentCustomer;
        $this->initialisation($request)->withTab(RetinaPalletReturnTabsEnum::values());

        return $this->handle($palletReturn);
    }

    public function htmlResponse(PalletReturn $palletReturn, ActionRequest $request): Response
    {
        $navigation = RetinaPalletReturnTabsEnum::navigation($palletReturn);

        if ($palletReturn->type == PalletReturnTypeEnum::PALLET) {
            unset($navigation[RetinaPalletReturnTabsEnum::STORED_ITEMS->value]);
        } else {
            unset($navigation[RetinaPalletReturnTabsEnum::GOODS->value]);
        }

        if ($palletReturn->type == PalletReturnTypeEnum::STORED_ITEM) {
            $afterTitle = [
                'label' => '('.__("Customer's sKUs").')'
            ];
        } else {
            $afterTitle = [
                'label' => '('.__('Whole goods').')'
            ];
        }

        $showGrossAndDiscount = $palletReturn->gross_amount !== $palletReturn->net_amount;

        if ($palletReturn->type == PalletReturnTypeEnum::PALLET) {
            $downloadRoute = 'retina.fulfilment.storage.pallet_returns.pallets.uploads.templates';
        } else {
            $downloadRoute = 'retina.fulfilment.storage.pallet_returns.stored-items.uploads.templates';
        };

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
                    'type'    => 'button',
                    'key'     => 'modal-add-pallet',
                ],
                $palletReturn->pallets()->count() > 0 ? [
                    'type'    => 'button',
                    'style'   => 'save',
                    'tooltip' => __('submit'),
                    'label'   => __('submit'),
                    'key'     => 'action',
                    'route'   => [
                        'method'     => 'post',
                        'name'       => 'retina.models.pallet-return.submit',
                        'parameters' => [
                            'palletReturn' => $palletReturn->id
                        ]
                    ]
                ] : [],
            ]
            : [
                $palletReturn->state != PalletReturnStateEnum::DISPATCHED && $palletReturn->state != PalletReturnStateEnum::CANCEL ? [
                    'type'    => 'button',
                    'style'   => 'negative',
                    'icon'    => 'fal fa-times',
                    'tooltip' => __('cancel'),
                    'label'   => __('cancel return'),
                    'key'     => 'action',
                    'route'   => [
                        'method'     => 'post',
                        'name'       => 'retina.models.pallet-return.cancel',
                        'parameters' => [
                            'palletReturn' => $palletReturn->id
                        ]
                    ]
                ] : []
            ];

        if (in_array($palletReturn->state, [
            PalletReturnStateEnum::IN_PROCESS,
        ])) {
            $actions = array_merge([
                [
                    'type'    => 'button',
                    'style'   => 'delete',
                    'tooltip' => __('delete'),
                    'label'   => __('delete'),
                    'key'     => 'delete_return',
                    'ask_why' => false,
                    'route'   => [
                        'method'     => 'patch',
                        'name'       => 'retina.models.pallet-return.delete',
                        'parameters' => [
                            'palletReturn' => $palletReturn->id
                        ]
                    ]
                ]
            ], $actions);
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

        $customerAddressId         = $palletReturn->customer->address->id;
        $customerDeliveryAddressId = $palletReturn->customer->deliveryAddress->id;
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

        return Inertia::render(
            'Storage/RetinaPalletReturn',
            [
                'title'       => __('goods return'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($palletReturn, $request),
                    'next'     => $this->getNext($palletReturn, $request),
                ],
                'pageHead'    => [
                    'title'      => $palletReturn->reference,
                    'icon'       => [
                        'icon'  => ['fal', 'fa-truck-couch'],
                        'title' => $palletReturn->reference
                    ],
                    'afterTitle' => $afterTitle,
                    'model'      => __('goods return'),
                    'actions'    => $actions
                ],

                'service_list_route'       => [
                    'name'       => 'retina.json.fulfilment.return.services.index',
                    'parameters' => [
                        'fulfilment' => $palletReturn->fulfilment->slug,
                        'scope'      => $palletReturn->slug
                    ]
                ],
                'physical_good_list_route' => [
                    'name'       => 'retina.json.fulfilment.return.physical-goods.index',
                    'parameters' => [
                        'fulfilment' => $palletReturn->fulfilment->slug,
                        'scope'      => $palletReturn->slug
                    ]
                ],

                'stored_items_add_route' => [
                    'name'       => 'retina.models.pallet-return.stored_item.store',
                    'parameters' => [
                        'palletReturn' => $palletReturn->id
                    ]
                ],
                'updateRoute'            => [
                    'route' => [
                        'name'       => 'retina.models.pallet-return.update',
                        'parameters' => [
                            'palletReturn' => $palletReturn->id
                        ]
                    ]
                ],

                'deleteServiceRoute' => [
                    'name'       => 'retina.models.pallet-return.service.delete',
                    'parameters' => [
                        'palletReturn' => $palletReturn->id
                    ]
                ],

                'deletePhysicalGoodRoute' => [
                    'name'       => 'retina.models.pallet-return.physical_good.delete',
                    'parameters' => [
                        'palletReturn' => $palletReturn->id
                    ]
                ],

                'interest'           => [
                    'pallets_storage' => $palletReturn->fulfilmentCustomer->pallets_storage,
                    'items_storage'   => $palletReturn->fulfilmentCustomer->items_storage,
                    'dropshipping'    => $palletReturn->fulfilmentCustomer->dropshipping,
                ],


                'upload_pallet' => [
                    'title' => [
                        'label' => __('Upload storing pallet'),
                        'information' => __('The list of column file: reference')
                    ],
                    'progressDescription'   => __('Adding pallets to the Return'),
                    'preview_template'    => [
                        'unique_column' => [
                            'reference'  => [
                                'label' => __('The pallets should already stored in warehouse. Both system reference and your reference is valid.'),
                            ]
                        ],
                        'header' => ['reference'],
                        'rows' => [
                            [
                                'reference' => 'PALLET1',
                            ],
                        ]
                    ],
                    'upload_spreadsheet' => [
                        'event'           => 'action-progress',
                        'channel'         => 'retina.personal.'.$palletReturn->organisation_id,
                        'required_fields' => ['reference'],
                        // 'template'        => [
                        //     'label' => 'Download template (.xlsx)',
                        // ],
                        'route'           => [
                            'upload'   => [
                                'name'       => 'retina.models.pallet-return.pallet-return-item.upload',
                                'parameters' => [
                                    'palletReturn' => $palletReturn->id
                                ]
                            ],
                            'history'  => [
                                'name'       => 'retina.fulfilment.storage.pallet_returns.uploads.history',
                                'parameters' => [
                                    'palletReturn' => $palletReturn->slug
                                ]
                            ],
                            // 'download' => [
                            //     'name'       => $downloadRoute,
                            //     'parameters' => [
                            //         'fulfilmentCustomer' => $palletReturn->fulfilmentCustomer->slug,
                            //         'type'               => 'xlsx'
                            //     ]
                            // ],
                        ],
                    ],
                ],

                'routeStorePallet' => [
                    'name'       => 'retina.models.pallet-return.pallet.store',
                    'parameters' => [
                        'palletReturn' => $palletReturn->id
                    ]
                ],


                'attachmentRoutes' => [
                    'attachRoute' => [
                        'name'       => 'retina.models.pallet-return.attachment.attach',
                        'parameters' => [
                            'palletReturn' => $palletReturn->id,
                        ],
                        'method'     => 'post'
                    ],
                    'detachRoute' => [
                        'name'       => 'retina.models.pallet-return.attachment.detach',
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
                'addresses'   => [
                    'isCannotSelect'                => true,
                    'address_list'                  => $addressCollection,
                    'options'                       => [
                        'countriesAddressData' => GetAddressData::run()
                    ],
                    'pinned_address_id'              => $palletReturn->fulfilmentCustomer->customer->delivery_address_id,
                    'home_address_id'                => $palletReturn->fulfilmentCustomer->customer->address_id,
                    'current_selected_address_id'    => $palletReturn->fulfilmentCustomer->customer->delivery_address_id,
                    'selected_delivery_addresses_id' => $palletReturnDeliveryAddressIds,
                    'routes_list'                    => [
                        'pinned_route'                   => [
                            'method'     => 'patch',
                            'name'       => 'retina.models.customer.delivery-address.update',
                            'parameters' => [
                                'customer' => $palletReturn->fulfilmentCustomer->customer_id
                            ]
                        ],
                        'delete_route'  => [
                            'method'     => 'delete',
                            'name'       => 'retina.models.customer.delivery-address.delete',
                            'parameters' => [
                                'customer' => $palletReturn->fulfilmentCustomer->customer_id
                            ]
                        ],
                        'store_route' => [
                            'method'      => 'post',
                            'name'        => 'retina.models.customer.delivery-address.store',
                            'parameters'  => [
                                'customer' => $palletReturn->fulfilmentCustomer->customer_id
                            ]
                        ]
                    ]
                ],
                'box_stats'  => [
                    'collection_notes'  => $palletReturn->collection_notes ?? '',
                    'fulfilment_customer' => array_merge(
                        FulfilmentCustomerResource::make($palletReturn->fulfilmentCustomer)->getArray(),
                        [
                            'address' => [
                                // 'value'   => AddressResource::make($palletReturn->deliveryAddress ?? new Address()),
                                'value'            => $palletReturn->is_collection
                                    ?
                                    null
                                    :
                                    AddressResource::make($palletReturn->deliveryAddress),
                                'options'          => [
                                    'countriesAddressData' => GetAddressData::run()
                                ],
                                'address_customer' => [
                                    'value'   => AddressResource::make($palletReturn->fulfilmentCustomer->customer->address),
                                    'options' => [
                                        'countriesAddressData' => GetAddressData::run()
                                    ],
                                ],
                                'routes_address'   => [
                                    'store'  => [
                                        'method'     => 'post',
                                        'name'       => 'retina.models.pallet-return.address.store',
                                        'parameters' => [
                                            'palletReturn' => $palletReturn->id
                                        ]
                                    ],
                                    'delete' => [
                                        'method'     => 'delete',
                                        'name'       => 'retina.models.pallet-return.address.delete',
                                        'parameters' => [
                                            'palletReturn' => $palletReturn->id
                                        ]
                                    ],
                                    'update' => [
                                        'method'     => 'patch',
                                        'name'       => 'retina.models.pallet-return.address.update',
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
                            //     'price_base'    => 999,
                            //     'price_total'   => 1111 ?? 0
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
                                'label'       => __('Tax').' '.$palletReturn->taxCategory->rate * 100 .'%',
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
                                'label'       => __('Tax').' '.$palletReturn->taxCategory->rate * 100 .'%',
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
                    'return'    => [
                        'label'    => __("Return's note"),
                        'note'     => $palletReturn->customer_notes ?? '',
                        'editable' => true,
                        // 'bgColor'         => 'blue',
                        'field'    => 'customer_notes'
                    ],
                    'warehouse' => [
                        'label'    => __('Note from warehouse'),
                        'note'     => $palletReturn->public_notes ?? '',
                        'editable' => false,
                        // 'bgColor'         => 'pink',
                        'field'    => 'public_notes'
                    ],
                ],

                'route_check_stored_items' => [
                    'method'     => 'post',
                    'name'       => 'retina.models.pallet-return.stored_item.store',
                    'parameters' => [
                        $palletReturn->id
                    ]
                ],
                'pallets_route' => [
                    'method'     => 'get',
                    'name'       => 'retina.json.pallet-return.pallets.index',
                    'parameters' => [
                        'palletReturn'  => $palletReturn->slug
                    ]
                ],
                'option_attach_file' => [
                    [
                        'name' => __('Other'),
                        'code' => 'Other'
                    ]
                ],
                'data'               => PalletReturnResource::make($palletReturn),

                RetinaPalletReturnTabsEnum::GOODS->value => $this->tab == RetinaPalletReturnTabsEnum::GOODS->value ?
                    fn () => PalletReturnItemsUIResource::collection(IndexRetinaPalletsInReturnPalletWholePallets::run($palletReturn, RetinaPalletReturnTabsEnum::GOODS->value))
                    : Inertia::lazy(fn () => PalletReturnItemsUIResource::collection(IndexRetinaPalletsInReturnPalletWholePallets::run($palletReturn, RetinaPalletReturnTabsEnum::GOODS->value))),

                RetinaPalletReturnTabsEnum::SERVICES->value => $this->tab == RetinaPalletReturnTabsEnum::SERVICES->value ?
                    fn () => FulfilmentTransactionsResource::collection(IndexServiceInPalletReturn::run($palletReturn, RetinaPalletReturnTabsEnum::SERVICES->value))
                    : Inertia::lazy(fn () => FulfilmentTransactionsResource::collection(IndexServiceInPalletReturn::run($palletReturn, RetinaPalletReturnTabsEnum::SERVICES->value))),

                RetinaPalletReturnTabsEnum::PHYSICAL_GOODS->value => $this->tab == RetinaPalletReturnTabsEnum::PHYSICAL_GOODS->value ?
                    fn () => FulfilmentTransactionsResource::collection(IndexPhysicalGoodInPalletReturn::run($palletReturn, RetinaPalletReturnTabsEnum::PHYSICAL_GOODS->value))
                    : Inertia::lazy(fn () => FulfilmentTransactionsResource::collection(IndexPhysicalGoodInPalletReturn::run($palletReturn, RetinaPalletReturnTabsEnum::PHYSICAL_GOODS->value))),

                RetinaPalletReturnTabsEnum::ATTACHMENTS->value => $this->tab == RetinaPalletReturnTabsEnum::ATTACHMENTS->value ?
                    fn () => AttachmentsResource::collection(IndexAttachments::run($palletReturn, RetinaPalletReturnTabsEnum::ATTACHMENTS->value))
                    : Inertia::lazy(fn () => AttachmentsResource::collection(IndexAttachments::run($palletReturn, RetinaPalletReturnTabsEnum::ATTACHMENTS->value))),
            ]
        )->table(
            IndexRetinaPalletsInReturnPalletWholePallets::make()->tableStructure(
                $palletReturn,
                request: $request,
                prefix: RetinaPalletReturnTabsEnum::GOODS->value
            )
        )->table(
            IndexServiceInPalletReturn::make()->tableStructure(
                $palletReturn,
                prefix: RetinaPalletReturnTabsEnum::SERVICES->value
            )
        )->table(
            IndexPhysicalGoodInPalletReturn::make()->tableStructure(
                $palletReturn,
                prefix: RetinaPalletReturnTabsEnum::PHYSICAL_GOODS->value
            )
        )->table(IndexAttachments::make()->tableStructure(RetinaPalletReturnTabsEnum::ATTACHMENTS->value));
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
                            'label' => __('goods returns')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $palletReturn->slug,
                        ],

                    ],
                    'suffix'         => $suffix
                ],
            ];
        };

        $palletReturn = PalletReturn::where('slug', $routeParameters['palletReturn'])->first();

        return match ($routeName) {
            'retina.fulfilment.storage.pallet_returns.show' => array_merge(
                ShowRetinaStorageDashboard::make()->getBreadcrumbs(),
                $headCrumb(
                    $palletReturn,
                    [
                        'index' => [
                            'name'       => 'retina.fulfilment.storage.pallet_returns.index',
                            'parameters' => []
                        ],
                        'model' => [
                            'name'       => 'retina.fulfilment.storage.pallet_returns.show',
                            'parameters' => $routeParameters
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
        $previous = PalletReturn::where('id', '<', $palletReturn->id)
            ->where('fulfilment_customer_id', $this->customer->fulfilmentCustomer->id)
            ->orderBy('id', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(PalletReturn $palletReturn, ActionRequest $request): ?array
    {
        $next = PalletReturn::where('id', '>', $palletReturn->id)
            ->where('fulfilment_customer_id', $this->customer->fulfilmentCustomer->id)
            ->orderBy('id')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?PalletReturn $palletReturn, string $routeName): ?array
    {
        if (!$palletReturn) {
            return null;
        }


        return match (class_basename($this->parent)) {
            'Warehouse' => [
                'label' => $palletReturn->slug,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $palletReturn->organisation->slug,
                        'warehouse'    => $palletReturn->warehouse->slug,
                        'palletReturn' => $palletReturn->slug
                    ]

                ]
            ],
            'FulfilmentCustomer' => [
                'label' => $palletReturn->slug,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation'       => $palletReturn->organisation->slug,
                        'fulfilment'         => $palletReturn->fulfilment->slug,
                        'fulfilmentCustomer' => $palletReturn->fulfilmentCustomer->slug,
                        'palletReturn'       => $palletReturn->slug
                    ]

                ]
            ],
            default => []
        };
    }
}
