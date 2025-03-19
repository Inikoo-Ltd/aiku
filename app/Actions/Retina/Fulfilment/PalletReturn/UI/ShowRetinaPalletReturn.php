<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Mar 2023 17:41:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Fulfilment\PalletReturn\UI;

use App\Actions\Fulfilment\PalletReturn\IndexPalletsInReturnPalletWholePallets;
use App\Actions\Fulfilment\PalletReturn\UI\IndexPhysicalGoodInPalletReturn;
use App\Actions\Fulfilment\PalletReturn\UI\IndexServiceInPalletReturn;
use App\Actions\Helpers\Country\UI\GetAddressData;
use App\Actions\Helpers\Media\UI\IndexAttachments;
use App\Actions\Retina\Fulfilment\UI\ShowRetinaStorageDashboard;
use App\Actions\RetinaAction;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Enums\UI\Fulfilment\PalletReturnTabsEnum;
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
        $this->initialisation($request)->withTab(PalletReturnTabsEnum::values());

        return $this->handle($palletReturn);
    }

    public function htmlResponse(PalletReturn $palletReturn, ActionRequest $request): Response
    {
        $navigation = PalletReturnTabsEnum::navigation($palletReturn);

        if ($palletReturn->type == PalletReturnTypeEnum::PALLET) {
            unset($navigation[PalletReturnTabsEnum::STORED_ITEMS->value]);
        } else {
            unset($navigation[PalletReturnTabsEnum::PALLETS->value]);
        }

        // if ($palletReturn->type == PalletReturnTypeEnum::PALLET) {
        //     $this->tab = PalletReturnTabsEnum::PALLETS->value;
        // } else {
        //     $this->tab = PalletReturnTabsEnum::STORED_ITEMS->value;
        // }

        if ($palletReturn->type == PalletReturnTypeEnum::STORED_ITEM) {
            $afterTitle = [
                'label' => '('.__("Customer's sKUs").')'
            ];
        } else {
            $afterTitle = [
                'label' => '('.__('Whole pallets').')'
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

        return Inertia::render(
            'Storage/RetinaPalletReturn',
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
                    'title'      => $palletReturn->reference,
                    'icon'       => [
                        'icon'  => ['fal', 'fa-truck-couch'],
                        'title' => $palletReturn->reference
                    ],
                    'afterTitle' => $afterTitle,
                    'model'      => __('pallet return'),
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
                'upload_spreadsheet' => [
                    'event'           => 'action-progress',
                    'channel'         => 'retina.personal.'.$palletReturn->organisation_id,
                    'required_fields' => ['reference'],
                    'template'        => [
                        'label' => 'Download template (.xlsx)',
                    ],
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
                        'download' => [
                            'name'       => $downloadRoute,
                            'parameters' => [
                                'fulfilmentCustomer' => $palletReturn->fulfilmentCustomer->slug,
                                'type'               => 'xlsx'
                            ]
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
                'box_stats'  => [
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

                PalletReturnTabsEnum::PALLETS->value => $this->tab == PalletReturnTabsEnum::PALLETS->value ?
                    fn () => PalletReturnItemsUIResource::collection(IndexPalletsInReturnPalletWholePallets::run($palletReturn, PalletReturnTabsEnum::PALLETS->value))
                    : Inertia::lazy(fn () => PalletReturnItemsUIResource::collection(IndexPalletsInReturnPalletWholePallets::run($palletReturn, PalletReturnTabsEnum::PALLETS->value))),

                PalletReturnTabsEnum::SERVICES->value => $this->tab == PalletReturnTabsEnum::SERVICES->value ?
                    fn () => FulfilmentTransactionsResource::collection(IndexServiceInPalletReturn::run($palletReturn, PalletReturnTabsEnum::SERVICES->value))
                    : Inertia::lazy(fn () => FulfilmentTransactionsResource::collection(IndexServiceInPalletReturn::run($palletReturn, PalletReturnTabsEnum::SERVICES->value))),

                PalletReturnTabsEnum::PHYSICAL_GOODS->value => $this->tab == PalletReturnTabsEnum::PHYSICAL_GOODS->value ?
                    fn () => FulfilmentTransactionsResource::collection(IndexPhysicalGoodInPalletReturn::run($palletReturn, PalletReturnTabsEnum::PHYSICAL_GOODS->value))
                    : Inertia::lazy(fn () => FulfilmentTransactionsResource::collection(IndexPhysicalGoodInPalletReturn::run($palletReturn, PalletReturnTabsEnum::PHYSICAL_GOODS->value))),

                PalletReturnTabsEnum::ATTACHMENTS->value => $this->tab == PalletReturnTabsEnum::ATTACHMENTS->value ?
                    fn () => AttachmentsResource::collection(IndexAttachments::run($palletReturn, PalletReturnTabsEnum::ATTACHMENTS->value))
                    : Inertia::lazy(fn () => AttachmentsResource::collection(IndexAttachments::run($palletReturn, PalletReturnTabsEnum::ATTACHMENTS->value))),
            ]
        )->table(
            IndexPalletsInReturnPalletWholePallets::make()->tableStructure(
                $palletReturn,
                request: $request,
                prefix: PalletReturnTabsEnum::PALLETS->value
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
                            'label' => __('pallet returns')
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
