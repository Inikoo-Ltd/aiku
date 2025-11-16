<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 16 Nov 2025 12:31:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\PalletReturn\UI\Traits;

use App\Actions\Fulfilment\Fulfilment\UI\ShowFulfilment;
use App\Actions\Fulfilment\FulfilmentCustomer\ShowFulfilmentCustomer;
use App\Actions\Inventory\Warehouse\UI\ShowWarehouse;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Enums\UI\Fulfilment\PalletReturnTabsEnum;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Inventory\Warehouse;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\PalletReturn;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

trait WithShowPalletReturnHelpers
{
    protected function buildSubNavigation(FulfilmentCustomer|Warehouse|Fulfilment $parent, ActionRequest $request): array
    {
        if ($parent instanceof FulfilmentCustomer && method_exists($this, 'getFulfilmentCustomerSubNavigation')) {
            return $this->getFulfilmentCustomerSubNavigation($parent, $request);
        }

        return [];
    }


    protected function buildTabsNavigation(PalletReturn $palletReturn, ActionRequest $request): array
    {
        $navigation = PalletReturnTabsEnum::navigation($palletReturn);

        if ($palletReturn->type == PalletReturnTypeEnum::PALLET) {
            unset($navigation[PalletReturnTabsEnum::STORED_ITEMS->value]);
        } else {
            unset($navigation[PalletReturnTabsEnum::PALLETS->value]);
            if (property_exists($this, 'tab')) {
                $this->tab = $request->get('tab', array_key_first($navigation));
            }
        }

        return $navigation;
    }


    protected function computeAfterTitle(PalletReturn $palletReturn): array
    {
        if ($palletReturn->type == PalletReturnTypeEnum::STORED_ITEM) {
            return ['label' => '('.__("Customer's SKUs").')'];
        }

        return ['label' => '('.__('Whole pallets').')'];
    }


    protected function buildEditLink(ActionRequest $request, bool $canEdit): array|false
    {
        if (!$canEdit) {
            return false;
        }

        return [
            'route' => [
                'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                'parameters' => array_values($request->route()->originalParameters())
            ]
        ];
    }

    /**
     * Build the common upload_spreadsheet configuration used by both ShowPalletReturn* actions.
     */
    protected function buildUploadSpreadsheetConfig(PalletReturn $palletReturn, string $downloadRoute): array
    {
        return [
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
        ];
    }

    /**
     * Common breadcrumb builder used by both ShowPalletReturn* actions.
     */
    protected function buildPalletReturnBreadcrumbs(string $routeName, array $routeParameters, $suffix = ''): array
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
}
