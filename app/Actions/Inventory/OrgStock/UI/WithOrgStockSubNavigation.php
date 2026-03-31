<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Jan 2026 20:11:18 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\UI;

use App\Models\Inventory\OrgStock;
use Lorisleiva\Actions\ActionRequest;

trait WithOrgStockSubNavigation
{
    protected function getOrgStockSubNavigation(OrgStock $orgStock, ActionRequest $request): array
    {
        $routeName       = $request->route()->getName();
        $routeParameters = $request->route()->originalParameters();
        $routeName = preg_replace('/\.(org_stock_history|stock_history|procurement|products|delivery_notes)$/', '', $routeName);

        return [
            [
                'isAnchor' => true,
                'label'    => $orgStock->code,
                'route'    => [
                    'name'       => $routeName,
                    'parameters' => $routeParameters
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-box'],
                    'tooltip' => __('SKU')
                ]
            ],


            [
                'label' => __('Stock History'),

                'route'    => [
                    'name'       => $routeName.'.org_stock_history',
                    'parameters' => $routeParameters
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-history'],
                    'tooltip' => __('Stock History')
                ]
            ],
            [
                'label' => __('Movements'),

                'route'    => [
                    'name'       => $routeName.'.stock_history',
                    'parameters' => $routeParameters
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-scanner'],
                    'tooltip' => __('Movements')
                ]
            ],
            [
                'label' => __('Procurement'),

                'route'    => [
                    'name'       => $routeName.'.procurement',
                    'parameters' => $routeParameters
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-box-usd'],
                    'tooltip' => __('Suppliers').', '.__('Purchase orders')
                ]
            ],
            [
                'label' => __('Products/Sales'),

                'route'    => [
                    'name'       => $routeName.'.products',
                    'parameters' => $routeParameters
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-cube'],
                    'tooltip' => __('Products').', '.__('Sales')
                ]
            ],
            [
                'label' => __('Delivery Notes'),

                'route'    => [
                    'name'       => $routeName.'.delivery_notes',
                    'parameters' => $routeParameters
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-shopping-cart'],
                    'tooltip' => __('Delivery notes')
                ]
            ],

        ];
    }


}
