<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Mar 2023 17:41:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\GoodsOut\UI;

use App\Actions\Fulfilment\PalletReturn\IndexPalletsInReturnPalletWholePallets;
use App\Actions\Fulfilment\PalletReturn\UI\IndexPhysicalGoodInPalletReturn;
use App\Actions\Fulfilment\PalletReturn\UI\IndexServiceInPalletReturn;
use App\Actions\Fulfilment\StoredItem\UI\IndexStoredItemsInReturn;
use App\Actions\Helpers\Media\UI\IndexAttachments;
use App\Actions\Inventory\Warehouse\UI\ShowWarehouse;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithFulfilmentWarehouseAuthorisation;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Enums\UI\Fulfilment\PalletReturnTabsEnum;
use App\Http\Resources\Fulfilment\FulfilmentTransactionsResource;
use App\Http\Resources\Fulfilment\PalletReturnItemsUIResource;
use App\Http\Resources\Fulfilment\PalletReturnResource;
use App\Http\Resources\Helpers\Attachment\AttachmentsResource;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowWarehousePalletReturn extends OrgAction
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



    public function htmlResponse(PalletReturn $palletReturn, ActionRequest $request): Response
    {

        $subNavigation = [];


        $actions = [];


        $navigation = PalletReturnTabsEnum::navigation($palletReturn);


        if ($palletReturn->type == PalletReturnTypeEnum::STORED_ITEM) {
            $afterTitle = [
                'label' => '('.__("Customer's SKUs").')'
                ];
        } else {
            $afterTitle = [
                'label' => '('.__('Whole pallets').')'
            ];
        }


        return Inertia::render(
            'Org/Fulfilment/PalletReturn',
            [
                'title'       => __('pallet return'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation' => [
                    'previous' => $this->getPrevious($palletReturn, $request),
                    'next'     => $this->getNext($palletReturn, $request),
                ],
                'pageHead' => [
                    // 'container' => $container,
                    'subNavigation' => $subNavigation,
                    'title'     => $palletReturn->reference,
                    'model'     => __('return'),
                    'afterTitle' => $afterTitle,
                    'icon'      => [
                        'icon'  => ['fal', 'fa-truck-couch'],
                        'title' => $palletReturn->reference
                    ],
                    'actions' => $actions
                ],

                'interest'  => [
                    'pallets_storage' => $palletReturn->fulfilmentCustomer->pallets_storage,
                    'items_storage'   => $palletReturn->fulfilmentCustomer->items_storage,
                    'dropshipping'    => $palletReturn->fulfilmentCustomer->dropshipping,
                ],

                'updateRoute' => [
                    'name'       => 'grp.models.pallet-return.update',
                    'parameters' => [
                        'palletReturn'       => $palletReturn->id
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
                        'palletReturn'       => $palletReturn->id
                    ]
                ],





                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => $navigation
                ],
                'data'             => PalletReturnResource::make($palletReturn),



                'pallets_route' => [
                    'method'     => 'get',
                    'name'       => 'grp.json.pallet-return.pallets.index',
                    'parameters' => [
                        $palletReturn->slug
                    ]
                ],

                'route_check_stored_items'   => [
                    'method'     => 'post',
                    'name'       => 'grp.models.pallet-return.stored_item.store',
                    'parameters' => [
                        $palletReturn->id
                    ]
                ],

                'can_edit_transactions' => true,



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
                    'suffix' => $suffix
                ],
            ];
        };

        $palletReturn = PalletReturn::where('slug', $routeParameters['palletReturn'])->first();

        return match ($routeName) {


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

    public function getPrevious(PalletReturn $palletReturn, ActionRequest $request): ?array
    {

        $previous = PalletReturn::where('id', '<', $palletReturn->id)->where('type', PalletReturnTypeEnum::PALLET)->orderBy('id', 'desc')->first();


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
                    'organisation'   => $palletReturn->organisation->slug,
                    'warehouse'      => $palletReturn->warehouse->slug,
                    'palletReturn'   => $palletReturn->reference
                ]

            ]
        ];
    }
}
