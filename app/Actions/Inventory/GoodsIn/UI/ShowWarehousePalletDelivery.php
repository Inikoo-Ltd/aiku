<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Mar 2023 17:41:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\GoodsIn\UI;

use App\Actions\Fulfilment\Pallet\UI\IndexPalletsInDelivery;
use App\Actions\Fulfilment\PalletDelivery\UI\IndexPhysicalGoodInPalletDelivery;
use App\Actions\Fulfilment\PalletDelivery\UI\IndexServiceInPalletDelivery;
use App\Actions\Helpers\Media\UI\IndexAttachments;
use App\Actions\Inventory\Warehouse\UI\ShowWarehouse;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithFulfilmentWarehouseAuthorisation;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\PalletDelivery\PalletDeliveryStateEnum;
use App\Enums\UI\Fulfilment\PalletDeliveryTabsEnum;
use App\Http\Resources\Fulfilment\FulfilmentCustomerResource;
use App\Http\Resources\Fulfilment\FulfilmentTransactionsResource;
use App\Http\Resources\Fulfilment\PalletDeliveryResource;
use App\Http\Resources\Fulfilment\PalletsResource;
use App\Http\Resources\Helpers\Attachment\AttachmentsResource;
use App\Http\Resources\Helpers\CurrencyResource;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowWarehousePalletDelivery extends OrgAction
{
    use WithFulfilmentWarehouseAuthorisation;


    public function handle(PalletDelivery $palletDelivery): PalletDelivery
    {
        return $palletDelivery;
    }


    public function asController(Organisation $organisation, Warehouse $warehouse, PalletDelivery $palletDelivery, ActionRequest $request): PalletDelivery
    {
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(PalletDeliveryTabsEnum::values());

        return $this->handle($palletDelivery);
    }


    public function htmlResponse(PalletDelivery $palletDelivery, ActionRequest $request): Response
    {
        $subNavigation = [];
        $showGrossAndDiscount = $palletDelivery->gross_amount !== $palletDelivery->net_amount;

        $recurringBillData = null;
        $invoiceData = null;
        if ($palletDelivery->recurringBill) {
            $recurringBill      = $palletDelivery->recurringBill;
            $recurringBillRoute = null;
            $invoiceRoute      = null;
            $recurringBillRoute = [
                'name'       => 'grp.org.fulfilments.show.operations.recurring_bills.current.show',
                'parameters' => [
                    'organisation'  => $recurringBill->organisation->slug,
                    'fulfilment'    => $recurringBill->fulfilment->slug,
                    'recurringBill' => $recurringBill->slug
                ]
            ];
            if ($recurringBill->invoices) {
                $invoice = $recurringBill->invoices;
                $invoiceRoute = [
                    'name'       => 'grp.org.fulfilments.show.operations.invoices.show',
                    'parameters' => [
                        'organisation'  => $recurringBill->organisation->slug,
                        'fulfilment'    => $recurringBill->fulfilment->slug,
                        'invoice' => $recurringBill->invoices->slug
                    ]
                ];
            }
            if ($recurringBillRoute) {
                $recurringBillData = [
                    'reference'    => $recurringBill->reference,
                    'status'       => $recurringBill->status,
                    'total_amount' => $recurringBill->total_amount,
                    'route'        => $recurringBillRoute
                ];
            }
            if ($invoiceRoute) {
                $invoiceData = [
                    'reference'    => $invoice->reference,
                    'status'       => $invoice->pay_status,
                    'total_amount' => $invoice->total_amount,
                    'route'        => $invoiceRoute
                ];
            }
        }

        $actions = $this->getActions($palletDelivery);

        return Inertia::render(
            'Org/Fulfilment/PalletDelivery',
            [
                'title'       => __('pallet delivery'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($palletDelivery, $request),
                    'next'     => $this->getNext($palletDelivery, $request),
                ],
                'pageHead'    => [
                    'title'         => $palletDelivery->reference,
                    'icon'          => [
                        'icon'  => ['fal', 'fa-truck-couch'],
                        'title' => $palletDelivery->reference
                    ],
                    'subNavigation' => $subNavigation,
                    'model'         => __('pallet delivery'),
                    'iconRight'     => $palletDelivery->state->stateIcon()[$palletDelivery->state->value],
                    'actions'       => $actions,
                ],

                'can_edit_transactions' => true,

                'interest' => [
                    'pallets_storage' => $palletDelivery->fulfilmentCustomer->pallets_storage,
                    'items_storage'   => $palletDelivery->fulfilmentCustomer->items_storage,
                    'dropshipping'    => $palletDelivery->fulfilmentCustomer->dropshipping,
                ],

                'updateRoute' => [
                    'name'       => 'grp.models.pallet-delivery.update',
                    'parameters' => [
                        'palletDelivery' => $palletDelivery->id
                    ]
                ],


                'locationRoute' => [
                    'name'       => 'grp.org.warehouses.show.infrastructure.locations.index',
                    'parameters' => [
                        'organisation' => $palletDelivery->organisation->slug,
                        'warehouse'    => $palletDelivery->warehouse->slug
                    ]
                ],

                'rentalRoute' => [
                    'name'       => 'grp.org.fulfilments.show.catalogue.rentals.index',
                    'parameters' => [
                        'organisation' => $palletDelivery->organisation->slug,
                        'fulfilment'   => $palletDelivery->fulfilment->slug
                    ]
                ],

                'storedItemsRoute' => [
                    'index'  => [
                        'name'       => 'grp.org.fulfilments.show.crm.customers.show.stored-items.index',
                        'parameters' => [
                            'organisation'       => $palletDelivery->organisation->slug,
                            'fulfilment'         => $palletDelivery->fulfilment->slug,
                            'fulfilmentCustomer' => $palletDelivery->fulfilmentCustomer->slug,
                            'palletDelivery'     => $palletDelivery->slug
                        ]
                    ],
                    'store'  => [
                        'name'       => 'grp.models.fulfilment-customer.stored-items.store',
                        'parameters' => [
                            'fulfilmentCustomer' => $palletDelivery->fulfilmentCustomer->id
                        ]
                    ],
                    'delete' => [
                        'name' => 'grp.models.stored-items.delete'
                    ]
                ],


                'service_list_route'       => [
                    'name'       => 'grp.json.fulfilment.delivery.services.index',
                    'parameters' => [
                        'fulfilment' => $palletDelivery->fulfilment->slug,
                        'scope'      => $palletDelivery->slug
                    ]
                ],
                'physical_good_list_route' => [
                    'name'       => 'grp.json.fulfilment.delivery.physical-goods.index',
                    'parameters' => [
                        'fulfilment' => $palletDelivery->fulfilment->slug,
                        'scope'      => $palletDelivery->slug
                    ]
                ],

                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => PalletDeliveryTabsEnum::navigation($palletDelivery)
                ],


                'data' => PalletDeliveryResource::make($palletDelivery),
                'box_stats'  => [
                    'fulfilment_customer' => FulfilmentCustomerResource::make($palletDelivery->fulfilmentCustomer)->getArray(),
                    'delivery_state'      => PalletDeliveryStateEnum::stateIcon()[$palletDelivery->state->value],
                    'recurring_bill'      => $recurringBillData,
                    'invoice' => $invoiceData,
                    'order_summary'       => [
                        [
                            [
                                'label'       => __('Services'),
                                'quantity'    => $palletDelivery->stats->number_services ?? 0,
                                'price_base'  => __('Multiple'),
                                'price_total' => $palletDelivery->services_amount
                            ],
                            [
                                'label'       => __('Physical Goods'),
                                'quantity'    => $palletDelivery->stats->number_physical_goods ?? 0,
                                'price_base'  => __('Multiple'),
                                'price_total' => $palletDelivery->goods_amount
                            ],
                        ],

                        $showGrossAndDiscount ? [
                            [
                                'label'       => __('Gross'),
                                'information' => '',
                                'price_total' => $palletDelivery->gross_amount
                            ],
                            [
                                'label'       => __('Discounts'),
                                'information' => '',
                                'price_total' => $palletDelivery->discount_amount
                            ],
                        ] : [],
                        [
                            [
                                'label'       => __('Net'),
                                'information' => '',
                                'price_total' => $palletDelivery->net_amount
                            ],
                            [
                                'label'       => __('Tax').' '.$palletDelivery->taxCategory->rate * 100 .'%',
                                'information' => '',
                                'price_total' => $palletDelivery->tax_amount
                            ],
                        ],
                        [
                            [
                                'label'       => __('Total'),
                                'price_total' => $palletDelivery->total_amount
                            ],
                        ],

                        'currency' => CurrencyResource::make($palletDelivery->currency),
                    ]
                ],


                PalletDeliveryTabsEnum::PALLETS->value => $this->tab == PalletDeliveryTabsEnum::PALLETS->value ?
                    fn () => PalletsResource::collection(IndexPalletsInDelivery::run($palletDelivery, PalletDeliveryTabsEnum::PALLETS->value))
                    : Inertia::lazy(fn () => PalletsResource::collection(IndexPalletsInDelivery::run($palletDelivery, PalletDeliveryTabsEnum::PALLETS->value))),

                PalletDeliveryTabsEnum::SERVICES->value => $this->tab == PalletDeliveryTabsEnum::SERVICES->value ?
                    fn () => FulfilmentTransactionsResource::collection(IndexServiceInPalletDelivery::run($palletDelivery, PalletDeliveryTabsEnum::SERVICES->value))
                    : Inertia::lazy(fn () => FulfilmentTransactionsResource::collection(IndexServiceInPalletDelivery::run($palletDelivery, PalletDeliveryTabsEnum::SERVICES->value))),

                PalletDeliveryTabsEnum::PHYSICAL_GOODS->value => $this->tab == PalletDeliveryTabsEnum::PHYSICAL_GOODS->value ?
                    fn () => FulfilmentTransactionsResource::collection(IndexPhysicalGoodInPalletDelivery::run($palletDelivery, PalletDeliveryTabsEnum::PHYSICAL_GOODS->value))
                    : Inertia::lazy(fn () => FulfilmentTransactionsResource::collection(IndexPhysicalGoodInPalletDelivery::run($palletDelivery, PalletDeliveryTabsEnum::PHYSICAL_GOODS->value))),

                PalletDeliveryTabsEnum::ATTACHMENTS->value => $this->tab == PalletDeliveryTabsEnum::ATTACHMENTS->value ?
                    fn () => AttachmentsResource::collection(IndexAttachments::run($palletDelivery, PalletDeliveryTabsEnum::ATTACHMENTS->value))
                    : Inertia::lazy(fn () => AttachmentsResource::collection(IndexAttachments::run($palletDelivery, PalletDeliveryTabsEnum::ATTACHMENTS->value))),

            ]
        )->table(
            IndexPalletsInDelivery::make()->tableStructure(
                $palletDelivery,
                prefix: PalletDeliveryTabsEnum::PALLETS->value
            )
        )->table(
            IndexServiceInPalletDelivery::make()->tableStructure(
                $palletDelivery,
                prefix: PalletDeliveryTabsEnum::SERVICES->value
            )
        )->table(
            IndexPhysicalGoodInPalletDelivery::make()->tableStructure(
                $palletDelivery,
                prefix: PalletDeliveryTabsEnum::PHYSICAL_GOODS->value
            )
        )->table(IndexAttachments::make()->tableStructure(PalletDeliveryTabsEnum::ATTACHMENTS->value));
    }

    public function getActions(PalletDelivery $palletDelivery): array
    {
        $numberPalletsStateBookingIn = $palletDelivery->pallets()->where('state', PalletStateEnum::BOOKING_IN)->count();
        $numberPalletsRentalNotSet   = $palletDelivery->pallets()->whereNull('rental_id')->count();

        $pdfButton = [
            'type'   => 'button',
            'style'  => 'tertiary',
            'label'  => 'PDF',
            'target' => '_blank',
            'icon'   => 'fal fa-file-pdf',
            'key'    => 'action',
            'route'  => [
                'name'       => 'grp.models.pallet-delivery.pdf',
                'parameters' => [
                    'palletDelivery' => $palletDelivery->id
                ]
            ]
        ];


        $actions = match ($palletDelivery->state) {
            PalletDeliveryStateEnum::BOOKING_IN => [
                [
                    'type'   => 'buttonGroup',
                    'key'    => 'upload-add',
                    'button' => []
                ],
                ($numberPalletsStateBookingIn == 0 && $numberPalletsRentalNotSet == 0) ? [
                    'type'    => 'button',
                    'style'   => 'primary',
                    'icon'    => 'fal fa-check',
                    'tooltip' => __('Confirm booking'),
                    'label'   => __('Finish booking'),
                    'key'     => 'action',
                    'route'   => [
                        'method'     => 'post',
                        'name'       => 'grp.models.pallet-delivery.booked-in',
                        'parameters' => [
                            'palletDelivery' => $palletDelivery->id
                        ]
                    ]
                ] : null,
            ],
            default => []
        };

        return array_merge($actions, [$pdfButton]);
    }


    public function jsonResponse(PalletDelivery $palletDelivery): PalletDeliveryResource
    {
        return new PalletDeliveryResource($palletDelivery);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, $suffix = ''): array
    {
        $headCrumb = function (PalletDelivery $palletDelivery, array $routeParameters, string $suffix) {
            return [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Pallet deliveries')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $palletDelivery->slug,
                        ],

                    ],
                    'suffix'         => $suffix
                ],
            ];
        };

        $palletDelivery = PalletDelivery::where('slug', $routeParameters['palletDelivery'])->first();


        return match ($routeName) {
            'grp.org.warehouses.show.incoming.pallet_deliveries.show' =>
            array_merge(
                ShowWarehouse::make()->getBreadcrumbs(
                    Arr::only($routeParameters, ['organisation', 'warehouse'])
                ),
                $headCrumb(
                    $palletDelivery,
                    [
                        'index' => [
                            'name'       => 'grp.org.warehouses.show.incoming.pallet_deliveries.index',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'warehouse'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.warehouses.show.incoming.pallet_deliveries.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'warehouse', 'palletDelivery'])
                        ]
                    ],
                    $suffix
                ),
            ),

            default => []
        };
    }

    public function getPrevious(PalletDelivery $palletDelivery, ActionRequest $request): ?array
    {
        $previous = PalletDelivery::where('id', '<', $palletDelivery->id)->orderBy('id', 'desc')->first();


        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(PalletDelivery $palletDelivery, ActionRequest $request): ?array
    {
        $next = PalletDelivery::where('id', '>', $palletDelivery->id)->orderBy('id')->first();


        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?PalletDelivery $palletDelivery, string $routeName): ?array
    {
        if (!$palletDelivery) {
            return null;
        }

        return [
            'label' => $palletDelivery->reference,
            'route' => [
                'name'       => $routeName,
                'parameters' => [
                    'organisation'   => $palletDelivery->organisation->slug,
                    'warehouse'      => $palletDelivery->warehouse->slug,
                    'palletDelivery' => $palletDelivery->slug
                ]

            ]
        ];
    }
}
