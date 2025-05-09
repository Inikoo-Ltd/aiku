<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Mar 2023 17:41:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Fulfilment\PalletReturn\UI;

use App\Actions\Fulfilment\PalletReturn\UI\GetPalletReturnAddressManagement;
use App\Actions\Fulfilment\PalletReturn\UI\GetPalletReturnBoxStats;
use App\Actions\Fulfilment\PalletReturn\UI\GetRetinaPalletReturnActions;
use App\Actions\Fulfilment\PalletReturn\UI\IndexPhysicalGoodInPalletReturn;
use App\Actions\Fulfilment\PalletReturn\UI\IndexServiceInPalletReturn;
use App\Actions\Helpers\Media\UI\IndexAttachments;
use App\Actions\Retina\Fulfilment\UI\ShowRetinaStorageDashboard;
use App\Actions\RetinaAction;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Enums\UI\Fulfilment\RetinaPalletReturnTabsEnum;
use App\Http\Resources\Fulfilment\FulfilmentTransactionsResource;
use App\Http\Resources\Fulfilment\PalletReturnItemsUIResource;
use App\Http\Resources\Fulfilment\PalletReturnResource;
use App\Http\Resources\Fulfilment\PalletReturnsResource;
use App\Http\Resources\Helpers\Attachment\AttachmentsResource;
use App\Models\Fulfilment\PalletReturn;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowRetinaPalletReturn extends RetinaAction
{
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

        $actions = GetRetinaPalletReturnActions::run($palletReturn);


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
                    'model'      => __('goods out'),
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

                'interest' => [
                    'pallets_storage' => $palletReturn->fulfilmentCustomer->pallets_storage,
                    'items_storage'   => $palletReturn->fulfilmentCustomer->items_storage,
                    'dropshipping'    => $palletReturn->fulfilmentCustomer->dropshipping,
                ],


                'upload_pallet' => [
                    'title'               => [
                        'label'       => __('Upload storing pallet'),
                        'information' => __('The list of column file: reference')
                    ],
                    'progressDescription' => __('Adding pallets to the Return'),
                    'preview_template'    => [
                        'unique_column' => [
                            'reference' => [
                                'label' => __('The pallets should already stored in warehouse. Both system reference and your reference is valid.'),
                            ]
                        ],
                        'header'        => ['reference'],
                        'rows'          => [
                            [
                                'reference' => 'PALLET1',
                            ],
                        ]
                    ],
                    'upload_spreadsheet'  => [
                        'event'           => 'action-progress',
                        'channel'         => 'retina.personal.'.$palletReturn->organisation_id,
                        'required_fields' => ['reference'],
                        // 'template'        => [
                        //     'label' => 'Download template (.xlsx)',
                        // ],
                        'route'           => [
                            'upload'  => [
                                'name'       => 'retina.models.pallet-return.pallet-return-item.upload',
                                'parameters' => [
                                    'palletReturn' => $palletReturn->id
                                ]
                            ],
                            'history' => [
                                'name'       => 'retina.fulfilment.storage.pallet_returns.uploads.history',
                                'parameters' => [
                                    'palletReturn' => $palletReturn->slug
                                ]
                            ],
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

                'tabs'               => [
                    'current'    => $this->tab,
                    'navigation' => $navigation
                ],
                'address_management' => GetPalletReturnAddressManagement::run(palletReturn: $palletReturn, forRetina: true),
                'box_stats'          => GetPalletReturnBoxStats::run(palletReturn: $palletReturn, parent: $palletReturn->fulfilmentCustomer, fromRetina: true),
                'notes_data'         => [
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
                'pallets_route'            => [
                    'method'     => 'get',
                    'name'       => 'retina.json.pallet-return.pallets.index',
                    'parameters' => [
                        'palletReturn' => $palletReturn->slug
                    ]
                ],
                'option_attach_file'       => [
                    [
                        'name' => __('Other'),
                        'code' => 'Other'
                    ]
                ],
                'data'                     => PalletReturnResource::make($palletReturn),

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
                            'label' => __('goods out')
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

    public function getPrevious(PalletReturn $palletReturn, ActionRequest $request, bool $storedItems = false): ?array
    {
        $query = PalletReturn::where('id', '<', $palletReturn->id)
        ->where('fulfilment_customer_id', $palletReturn->fulfilmentCustomer->id);

        if ($storedItems) {
            $query->where('type', PalletReturnTypeEnum::STORED_ITEM);
        } else {
            $query->where('type', PalletReturnTypeEnum::PALLET);
        }

        $previous = $query->orderBy('id', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(PalletReturn $palletReturn, ActionRequest $request, bool $storedItems = false): ?array
    {
        $query = PalletReturn::where('id', '>', $palletReturn->id)
        ->where('fulfilment_customer_id', $palletReturn->fulfilmentCustomer->id);

        if ($storedItems) {
            $query->where('type', PalletReturnTypeEnum::STORED_ITEM);
        } else {
            $query->where('type', PalletReturnTypeEnum::PALLET);
        }

        $next = $query->orderBy('id')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?PalletReturn $palletReturn, string $routeName): ?array
    {
        if (!$palletReturn) {
            return null;
        }


        return [
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
            ];
    }
}
