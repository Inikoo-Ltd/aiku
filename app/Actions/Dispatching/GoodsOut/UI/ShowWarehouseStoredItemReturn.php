<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 28 Feb 2025 00:00:26 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\GoodsOut\UI;

use App\Actions\Fulfilment\PalletReturn\UI\GetPalletReturnAddressManagement;
use App\Actions\Fulfilment\PalletReturn\UI\GetPalletReturnBoxStats;
use App\Actions\Fulfilment\PalletReturn\UI\IndexPhysicalGoodInPalletReturn;
use App\Actions\Fulfilment\PalletReturn\UI\IndexServiceInPalletReturn;
use App\Actions\Fulfilment\StoredItem\UI\IndexStoredItemsInReturn;
use App\Actions\Helpers\Media\UI\IndexAttachments;
use App\Actions\Inventory\Warehouse\UI\ShowWarehouse;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithFulfilmentWarehouseAuthorisation;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Enums\UI\Fulfilment\PalletReturnTabsEnum;
use App\Http\Resources\Fulfilment\FulfilmentTransactionsResource;
use App\Http\Resources\Fulfilment\PalletReturnItemsWithStoredItemsResource;
use App\Http\Resources\Fulfilment\PalletReturnResource;
use App\Http\Resources\Fulfilment\PalletReturnsResource;
use App\Http\Resources\Helpers\Attachment\AttachmentsResource;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowWarehouseStoredItemReturn extends OrgAction
{
    use WithFulfilmentWarehouseAuthorisation;

    public function handle(PalletReturn $palletReturn): PalletReturn
    {
        return $palletReturn;
    }


    public function asController(Organisation $organisation, Warehouse $warehouse, PalletReturn $palletReturn, ActionRequest $request): PalletReturn
    {
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(PalletReturnTabsEnum::values());

        return $this->handle($palletReturn);
    }

    public function getActions(PalletReturn $palletReturn): array
    {
        $actions   = [];
        $actions[] = [
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
        if ($this->canEdit) {
            if ($palletReturn->state == PalletReturnStateEnum::CONFIRMED) {
                $actions[] = [
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
                ];
            }

            if ($palletReturn->state == PalletReturnStateEnum::PICKED) {
                $actions[] = [
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
                ];
            }
        }

        return $actions;
    }

    public function htmlResponse(PalletReturn $palletReturn, ActionRequest $request): Response
    {
        $subNavigation = [];


        $navigation = PalletReturnTabsEnum::navigation($palletReturn);
        unset($navigation[PalletReturnTabsEnum::PALLETS->value]);
        $this->tab = $request->get('tab', array_key_first($navigation));


        $afterTitle = [
            'label' => '('.__("Customer's SKUs").')'
        ];


        $actions = $this->getActions($palletReturn);

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

                    'actions' => $actions
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


                'routeStorePallet' => [
                    'name'       => 'grp.models.pallet-return.pallet.store',
                    'parameters' => [
                        'palletReturn' => $palletReturn->id
                    ]
                ],


                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => $navigation
                ],
                'data' => PalletReturnResource::make($palletReturn),
                 'address_management' => GetPalletReturnAddressManagement::run(palletReturn: $palletReturn),
                 'box_stats'          => GetPalletReturnBoxStats::run(palletReturn: $palletReturn, parent: $palletReturn->fulfilmentCustomer),

                // 'service_list_route'       => [
                //     'name'       => 'grp.json.fulfilment.return.services.index',
                //     'parameters' => [
                //         'fulfilment' => $palletReturn->fulfilment->slug,
                //         'scope'      => $palletReturn->slug
                //     ]
                // ],
                // 'physical_good_list_route' => [
                //     'name'       => 'grp.json.fulfilment.return.physical-goods.index',
                //     'parameters' => [
                //         'fulfilment' => $palletReturn->fulfilment->slug,
                //         'scope'      => $palletReturn->slug
                //     ]
                // ],

                // 'route_check_stored_items' => [
                //     'method'     => 'post',
                //     'name'       => 'grp.models.pallet-return.stored_item.store',
                //     'parameters' => [
                //         $palletReturn->id
                //     ]
                // ],

                'can_edit_transactions' => false,
                'option_attach_file'    => [
                    [
                        'name' => __('Other'),
                        'code' => 'Other'
                    ]
                ],
                'stored_items_count'    => $palletReturn->storedItems()->count(),

                PalletReturnTabsEnum::STORED_ITEMS->value => $this->tab == PalletReturnTabsEnum::STORED_ITEMS->value ?
                    fn () => PalletReturnItemsWithStoredItemsResource::collection(IndexStoredItemsInReturn::run($palletReturn, PalletReturnTabsEnum::STORED_ITEMS->value))
                    : Inertia::lazy(fn () => PalletReturnItemsWithStoredItemsResource::collection(IndexStoredItemsInReturn::run($palletReturn, PalletReturnTabsEnum::STORED_ITEMS->value))),

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
            'grp.org.warehouses.show.dispatching.pallet-return-with-stored-items.show' => array_merge(
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
                            'name'       => 'grp.org.warehouses.show.dispatching.pallet-return-with-stored-items.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'warehouse', 'palletReturn'])
                        ]
                    ],
                    $suffix
                ),
            ),

            default => []
        };
    }

    public function getPrevious(PalletReturn $palletReturn, ActionRequest $request): ?array
    {
        $previous = PalletReturn::where('id', '<', $palletReturn->id)->where('type', PalletReturnTypeEnum::STORED_ITEM)->orderBy('id', 'desc')->first();


        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(PalletReturn $palletReturn, ActionRequest $request): ?array
    {
        $next = PalletReturn::where('id', '>', $palletReturn->id)->where('type', PalletReturnTypeEnum::PALLET)->orderBy('id')->first();


        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?PalletReturn $palletReturn, string $routeName): ?array
    {
        if (!$palletReturn) {
            return null;
        }


        return [
            'label' => $palletReturn->reference,
            'route' => [
                'name'       => $routeName,
                'parameters' => [
                    'organisation' => $palletReturn->organisation->slug,
                    'warehouse'    => $palletReturn->warehouse->slug,
                    'palletReturn' => $palletReturn->reference
                ]

            ]
        ];
    }
}
