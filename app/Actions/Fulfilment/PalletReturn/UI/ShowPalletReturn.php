<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Mar 2023 17:41:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletReturn\UI;

use App\Actions\Fulfilment\Fulfilment\UI\ShowFulfilment;
use App\Actions\Fulfilment\FulfilmentCustomer\ShowFulfilmentCustomer;
use App\Actions\Fulfilment\GetNotesData;
use App\Actions\Fulfilment\PalletReturn\IndexPalletsInReturnPalletWholePallets;
use App\Actions\Fulfilment\StoredItem\UI\IndexStoredItemsInReturn;
use App\Actions\Fulfilment\WithFulfilmentCustomerSubNavigation;
use App\Actions\Helpers\Media\UI\IndexAttachments;
use App\Actions\Inventory\Warehouse\UI\ShowWarehouse;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithFulfilmentShopAuthorisation;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Enums\UI\Fulfilment\PalletReturnTabsEnum;
use App\Http\Resources\Fulfilment\FulfilmentTransactionsResource;
use App\Http\Resources\Fulfilment\PalletReturnItemsUIResource;
use App\Http\Resources\Fulfilment\PalletReturnResource;
use App\Http\Resources\Helpers\Attachment\AttachmentsResource;
use App\Models\CRM\CustomerHasPlatform;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowPalletReturn extends OrgAction
{
    use WithFulfilmentShopAuthorisation;
    use WithFulfilmentCustomerSubNavigation;

    private Warehouse|FulfilmentCustomer|Fulfilment $parent;

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
    public function inWarehouse(Organisation $organisation, Warehouse $warehouse, PalletReturn $palletReturn, ActionRequest $request): PalletReturn
    {
        $this->parent = $warehouse;
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(PalletReturnTabsEnum::values());

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


        $navigation = PalletReturnTabsEnum::navigation($palletReturn);


        if ($palletReturn->type == PalletReturnTypeEnum::PALLET) {
            unset($navigation[PalletReturnTabsEnum::STORED_ITEMS->value]);
        } else {
            unset($navigation[PalletReturnTabsEnum::PALLETS->value]);
            $this->tab = $request->get('tab', array_key_first($navigation));
        }

        $actions = GetPalletReturnActions::run($palletReturn, $this->canEdit, $this->isSupervisor);


        if ($palletReturn->type == PalletReturnTypeEnum::STORED_ITEM) {
            $afterTitle = [
                'label' => '('.__("Customer's SKUs").')'
            ];
        } else {
            $afterTitle = [
                'label' => '('.__('Whole pallets').')'
            ];
        }

        if ($palletReturn->type == PalletReturnTypeEnum::PALLET) {
            $downloadRoute = 'grp.org.fulfilments.show.crm.customers.show.pallet_returns.pallets.export';
        } else {
            $downloadRoute = 'grp.org.fulfilments.show.crm.customers.show.pallet_returns.pallets.stored-items.export';
        }


        return Inertia::render(
            'Org/Fulfilment/PalletReturn',
            [
                'title'       => __('pallet return'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($this->parent, $palletReturn, $request),
                    'next'     => $this->getNext($this->parent, $palletReturn, $request),
                ],
                'pageHead'    => [
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

                'shipment_route' => [
                    'name'       => 'grp.models.pallet-return.shipment_from_fulfilment.store',
                    'parameters' => [
                        'palletReturn' => $palletReturn->id
                    ]
                ],

                'interest' => [
                    'pallets_storage' => $palletReturn->fulfilmentCustomer->pallets_storage,
                    'items_storage'   => $palletReturn->fulfilmentCustomer->items_storage,
                    'dropshipping'    => $palletReturn->fulfilmentCustomer->dropshipping,
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

                'upload_spreadsheet' => [
                    'event'           => 'action-progress',
                    'channel'         => 'grp.personal.'.$this->organisation->id,
                    'required_fields' => ['reference'],
                    'template'        => [
                        'label' => 'Download template (.xlsx)',
                    ],
                    'route'           => [
                        'upload'   => [
                            'name'       => 'grp.models.pallet-return.pallet-return-item.upload.upload',
                            'parameters' => [
                                'palletReturn' => $palletReturn->id
                            ]
                        ],
                        'history'  => [
                            'name'       => 'grp.org.fulfilments.show.crm.customers.show.pallet_returns.pallets.uploads.history',
                            'parameters' => [
                                'organisation'       => $palletReturn->organisation->slug,
                                'fulfilment'         => $palletReturn->fulfilment->slug,
                                'fulfilmentCustomer' => $palletReturn->fulfilmentCustomer->slug,
                                'palletReturn'       => $palletReturn->slug
                            ]
                        ],
                        'download' => [
                            'name'       => $downloadRoute,
                            'parameters' => [
                                'organisation'       => $palletReturn->organisation->slug,
                                'fulfilment'         => $palletReturn->fulfilment->slug,
                                'fulfilmentCustomer' => $palletReturn->fulfilmentCustomer->slug,
                                'type'               => 'xlsx'
                            ]
                        ],
                    ],
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


                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => $navigation
                ],
                'data' => PalletReturnResource::make($palletReturn),

                'address_management' => GetPalletReturnAddressManagement::run(palletReturn: $palletReturn),


                'box_stats' => GetPalletReturnBoxStats::run(palletReturn: $palletReturn, parent: $this->parent),

                'notes_data' => GetNotesData::run(model: $palletReturn),

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

                'pallets_route' => [
                    'method'     => 'get',
                    'name'       => 'grp.json.pallet-return.pallets.index',
                    'parameters' => [
                        $palletReturn->slug
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

                'option_attach_file' => [
                    [
                        'name' => __('Other'),
                        'code' => 'Other'
                    ]
                ],

                PalletReturnTabsEnum::PALLETS->value => $this->tab == PalletReturnTabsEnum::PALLETS->value ?
                    fn () => PalletReturnItemsUIResource::collection(IndexPalletsInReturnPalletWholePallets::run($palletReturn, PalletReturnTabsEnum::PALLETS->value))
                    : Inertia::lazy(fn () => PalletReturnItemsUIResource::collection(IndexPalletsInReturnPalletWholePallets::run($palletReturn, PalletReturnTabsEnum::PALLETS->value))),


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
            IndexPalletsInReturnPalletWholePallets::make()->tableStructure(
                $palletReturn,
                request: $request,
                prefix: PalletReturnTabsEnum::PALLETS->value
            )
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


    public function jsonResponse(PalletReturn $palletReturn): PalletReturnResource
    {
        return new PalletReturnResource($palletReturn);
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
            'grp.org.fulfilments.show.crm.customers.show.pallet_returns.show' => array_merge(
                ShowFulfilmentCustomer::make()->getBreadcrumbs(Arr::only($routeParameters, ['organisation', 'fulfilment', 'fulfilmentCustomer'])),
                $headCrumb(
                    $palletReturn,
                    [
                        'index' => [
                            'name'       => 'grp.org.fulfilments.show.crm.customers.show.pallet_returns.index',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment', 'fulfilmentCustomer'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.fulfilments.show.crm.customers.show.pallet_returns.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment', 'fulfilmentCustomer', 'palletReturn'])
                        ]
                    ],
                    $suffix
                )
            ),
            'grp.org.fulfilments.show.operations.pallet-returns.show' => array_merge(
                ShowFulfilment::make()->getBreadcrumbs(Arr::only($routeParameters, ['organisation', 'fulfilment'])),
                $headCrumb(
                    $palletReturn,
                    [
                        'index' => [
                            'name'       => 'grp.org.fulfilments.show.operations.pallet-returns.index',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment', 'palletReturn'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.fulfilments.show.operations.pallet-returns.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'fulfilment', 'palletReturn'])
                        ]
                    ],
                    $suffix
                )
            ),
            'grp.org.warehouses.show.dispatching.pallet-returns.show' => array_merge(
                ShowWarehouse::make()->getBreadcrumbs(
                    Arr::only($routeParameters, ['organisation', 'warehouse'])
                ),
                $headCrumb(
                    $palletReturn,
                    [
                        'index' => [
                            'name'       => 'grp.org.warehouses.show.dispatching.pallet-returns.index',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'warehouse'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.warehouses.show.dispatching.pallet-returns.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'warehouse', 'palletReturn'])
                        ]
                    ],
                    $suffix
                ),
            ),

            default => []
        };
    }

    public function getPrevious(Warehouse|FulfilmentCustomer|Fulfilment|CustomerHasPlatform $parent, PalletReturn $palletReturn, ActionRequest $request): ?array
    {
        if ($parent instanceof FulfilmentCustomer) {
            $previous = PalletReturn::where('fulfilment_customer_id', $parent->id)->where('id', '<', $palletReturn->id)->where('type', PalletReturnTypeEnum::PALLET)->orderBy('id', 'desc')->first();
        } elseif ($parent instanceof Fulfilment) {
            $previous = PalletReturn::where('fulfilment_id', $parent->id)->where('id', '<', $palletReturn->id)->where('type', PalletReturnTypeEnum::PALLET)->orderBy('id', 'desc')->first();
        } elseif ($parent instanceof CustomerHasPlatform) {
            $previous = PalletReturn::where('fulfilment_customer_id', $palletReturn->fulfilment_customer_id)->where('platform_id', $parent->platform_id)->where('id', '<', $palletReturn->id)->orderBy('id', 'desc')->first();
        } else {
            $previous = PalletReturn::where('id', '<', $palletReturn->id)->where('type', PalletReturnTypeEnum::PALLET)->orderBy('id', 'desc')->first();
        }

        return $this->getNavigation($parent, $previous, $request->route()->getName());
    }

    public function getNext(Warehouse|FulfilmentCustomer|Fulfilment|CustomerHasPlatform $parent, PalletReturn $palletReturn, ActionRequest $request): ?array
    {
        if ($parent instanceof FulfilmentCustomer) {
            $next = PalletReturn::where('fulfilment_customer_id', $parent->id)->where('id', '>', $palletReturn->id)->where('type', PalletReturnTypeEnum::PALLET)->orderBy('id')->first();
        } elseif ($parent instanceof Fulfilment) {
            $next = PalletReturn::where('fulfilment_id', $parent->id)->where('id', '>', $palletReturn->id)->where('type', PalletReturnTypeEnum::PALLET)->orderBy('id')->first();
        } elseif ($parent instanceof CustomerHasPlatform) {
            $next = PalletReturn::where('fulfilment_customer_id', $palletReturn->fulfilment_customer_id)->where('platform_id', $parent->platform_id)->where('id', '>', $palletReturn->id)->orderBy('id')->first();
        } else {
            $next = PalletReturn::where('id', '>', $palletReturn->id)->where('type', PalletReturnTypeEnum::PALLET)->orderBy('id')->first();
        }

        return $this->getNavigation($parent, $next, $request->route()->getName());
    }

    private function getNavigation(Warehouse|FulfilmentCustomer|Fulfilment|CustomerHasPlatform $parent, ?PalletReturn $palletReturn, string $routeName): ?array
    {
        if (!$palletReturn) {
            return null;
        }


        return match (class_basename($parent)) {
            'Warehouse' => [
                'label' => $palletReturn->reference,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $palletReturn->organisation->slug,
                        'warehouse'    => $palletReturn->warehouse->slug,
                        'palletReturn' => $palletReturn->reference
                    ]

                ]
            ],
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
            'CustomerHasPlatform' => [
                'label' => $palletReturn->reference,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation'       => $palletReturn->organisation->slug,
                        'fulfilment'         => $palletReturn->fulfilment->slug,
                        'fulfilmentCustomer' => $palletReturn->fulfilmentCustomer->slug,
                        'customerHasPlatform' => $parent,
                        'palletReturn'       => $palletReturn->reference
                    ]

                ]
            ],
            default => []
        };
    }
}
