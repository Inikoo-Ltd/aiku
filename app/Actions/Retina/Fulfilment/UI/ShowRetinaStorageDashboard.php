<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 19 Jan 2025 01:19:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Fulfilment\UI;

use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\PalletDelivery\PalletDeliveryStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Http\Resources\Catalogue\RetinaRentalAgreementResource;
use App\Http\Resources\Helpers\CurrencyResource;
use App\Models\Fulfilment\FulfilmentCustomer;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowRetinaStorageDashboard extends RetinaAction
{
    public function asController(ActionRequest $request): FulfilmentCustomer
    {
        $this->initialisation($request);
        return $this->customer->fulfilmentCustomer;
    }

    public function htmlResponse(FulfilmentCustomer $fulfilmentCustomer): Response
    {



        $clauses = null;
        foreach ($fulfilmentCustomer->rentalAgreementClauses as $clause) {
            $price                                  = $clause->asset->price;
            $percentageOff                          = $clause->percentage_off;
            $discount                               = $percentageOff / 100;
            $clauses[] = [
                'name'           => $clause->asset->name,
                'asset_id'       => $clause->asset_id,
                'type'           => $clause->asset->type->value,
                'agreed_price'   => $price - $price * $discount,
                'price'          => $price,
                'percentage_off' => $percentageOff
            ];
        }
        $routeActions = [];

        if ($fulfilmentCustomer->pallets_storage) {
            $routeActions[] = [
                'type'  => 'button',
                'style' => 'create',
                'tooltip' => __('Book goods into stock, rent storage space, buy packaging material etc'),
                'label' => __('New Storage or Service'),
                'fullLoading'   => true,
                'route' => [
                    'method'     => 'post',
                    'name'       => 'retina.models.pallet-delivery.store',
                    'parameters' => []
                ]
            ];
        }
        $routeActions[] = [
            'type'    => 'button',
            'style'   => $fulfilmentCustomer->number_pallets_status_storing ? 'create' : 'gray',
            'disabled' => $fulfilmentCustomer->number_pallets_status_storing ? false : true,
            'tooltip' => $fulfilmentCustomer->number_pallets_status_storing ? __('Make a new dispatch from your stock') : __('This service is available if you have stock to dispatch'),
            'label'   => __('New Dispatch'),
            'route'   => [
                'method'     => 'post',
                'name'       => 'retina.models.pallet-return.store',
                'parameters' => []
            ]
        ];
        $routeActions[] = [
            'type'    => 'button',
            'style'   => $fulfilmentCustomer->number_pallets_with_stored_items_state_storing ? 'create' : 'gray',
            'disabled' => $fulfilmentCustomer->number_pallets_with_stored_items_state_storing ? false : true,
            'tooltip' => $fulfilmentCustomer->number_pallets_with_stored_items_state_storing ? __('Make a new dispatch from your SKUs') : __('This service is available if you have SKUs to dispatch'),
            'label'   => __('New Dropship Dispatch'),
            'route'   => [
                'method'     => 'post',
                'name'       => 'retina.models.pallet-return-stored-items.store',
                'parameters' => []
            ]
        ];
        // $routeActions = [
        //     $fulfilmentCustomer->pallets_storage ? [
        //         'type'  => 'button',
        //         'style' => 'create',
        //         'label' => __('New Goods Delivery'),
        //         'fullLoading'   => true,
        //         'route' => [
        //             'method'     => 'post',
        //             'name'       => 'retina.models.pallet-delivery.store',
        //             'parameters' => []
        //         ]
        //     ] : false,
        // ];

            //    if (!app()->environment('production') && $fulfilmentCustomer->pallets_storage) {
            //        $routeActions = array_merge($routeActions, [
            //            $fulfilmentCustomer->number_pallets_status_storing ? [
            //                'type'    => 'button',
            //                'style'   => 'create',
            //                'tooltip' => $fulfilmentCustomer->number_pallets_with_stored_items_state_storing ? __('Create new return (whole pallet)') : __('Create new return'),
            //                'label'   => $fulfilmentCustomer->number_pallets_with_stored_items_state_storing ? __('New Return (whole pallet)') : __('Return'),
            //                'route'   => [
            //                    'method'     => 'post',
            //                    'name'       => 'retina.models.pallet-return.store',
            //                    'parameters' => []
            //                ]
            //            ] : false,
            //            $this->customer->fulfilmentCustomer->number_pallets_with_stored_items_state_storing ? [
            //                'type'    => 'button',
            //                'style'   => 'create',
            //                'tooltip' => __('Create new return (Selected SKUs)'),
            //                'label'   => __('Return (Selected SKUs)'),
            //                'route'   => [
            //                    'method'     => 'post',
            //                    'name'       => 'retina.models.pallet-return-stored-items.store',
            //                    'parameters' => []
            //                ]
            //            ] : false,
            //        ]);
            //    }
        $routeActions = array_filter($routeActions);

        return Inertia::render('Storage/RetinaStorageDashboard', [
            'title'        => __('Storage Dashboard'),
            'breadcrumbs'    => $this->getBreadcrumbs(),
            'pageHead'    => [

                'title'         => __('Storage Dashboard'),
                'icon'          => [
                    'icon'  => ['fal', 'fa-tachometer-alt'],
                    'title' => __('Storage Dashboard')
                ],

            ],

            'route_action' => $routeActions,

            'currency'     => CurrencyResource::make($fulfilmentCustomer->fulfilment->shop->currency),
            'storageData'  => $this->getDashboardData($fulfilmentCustomer),
            'rental_agreement' => RetinaRentalAgreementResource::make($fulfilmentCustomer->rentalAgreement),
            'discounts'    => $clauses
        ]);
    }

    public function getDashboardData(FulfilmentCustomer $fulfilmentCustomer): array
    {
        $stats = [];

        $stats['pallets'] = [
            'label'         => __('Goods'),
            'count'         => $fulfilmentCustomer->number_pallets_status_storing,
            'description'   => __('in warehouse'),
            'route'         => [
                'name' => 'retina.fulfilment.storage.pallets.index'
            ]
        ];

        foreach (PalletStateEnum::cases() as $case) {
            $stats['pallets']['state'][$case->value] = [
                'value' => $case->value,
                'icon'  => PalletStateEnum::stateIcon()[$case->value],
                'count' => PalletStateEnum::count($fulfilmentCustomer)[$case->value] ?? 0,
                'label' => PalletStateEnum::labels()[$case->value]
            ];
        }

        $stats['pallet_deliveries'] = [
            'label' => __('Goods In'),
            'count' => $fulfilmentCustomer->number_pallet_deliveries,
            'route' => [
                'name' => 'retina.fulfilment.storage.pallet_deliveries.index'
            ]
        ];
        foreach (PalletDeliveryStateEnum::cases() as $case) {
            $stats['pallet_deliveries']['cases'][$case->value] = [
                'value' => $case->value,
                'icon'  => PalletDeliveryStateEnum::stateIcon()[$case->value],
                'count' => PalletDeliveryStateEnum::count($fulfilmentCustomer)[$case->value],
                'label' => PalletDeliveryStateEnum::labels()[$case->value]
            ];
        }

        $stats['pallet_returns'] = [
            'label' => __('Goods Out'),
            'count' => $fulfilmentCustomer->number_pallet_returns,
            'route' => [
                'name' => 'retina.fulfilment.storage.pallet_returns.index'
            ]
        ];
        foreach (PalletReturnStateEnum::cases() as $case) {
            $stats['pallet_returns']['cases'][$case->value] = [
                'value' => $case->value,
                'icon'  => PalletReturnStateEnum::stateIcon()[$case->value],
                'count' => PalletReturnStateEnum::count($fulfilmentCustomer)[$case->value],
                'label' => PalletReturnStateEnum::labels()[$case->value]
            ];
        }

        return $stats;
    }

    public function getBreadcrumbs(): array
    {

        return
            array_merge(
                ShowRetinaDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'retina.fulfilment.storage.dashboard'
                            ],
                            'label' => __('Storage'),
                        ]
                    ]
                ]
            );

    }
}
