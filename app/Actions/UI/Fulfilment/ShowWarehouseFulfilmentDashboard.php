<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Mar 2023 18:44:51 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Fulfilment;

use App\Actions\Inventory\Warehouse\UI\ShowWarehouse;
use App\Actions\OrgAction;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowWarehouseFulfilmentDashboard extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo(
            [
                "fulfilment.{$this->warehouse->id}.view"
            ]
        );
    }


    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): ActionRequest
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $request;
    }


    public function htmlResponse(ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Warehouse/Fulfilment/FulfilmentDashboard',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('fulfilment'),
                'pageHead'    => [
                    'icon'      => [
                        'icon'  => ['fal', 'fa-hand-holding-box'],
                        'title' => __('Fulfilment')
                    ],
                    'iconRight' => [
                        'icon'  => ['fal', 'fa-chart-network'],
                        'title' => __('Fulfilment')
                    ],
                    'title'     => __('fulfilment central command'),
                ],


            ]
        );
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return
            array_merge(
                ShowWarehouse::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.warehouses.show.fulfilment.dashboard',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Fulfilment'),
                            'icon'  => 'fal fa-chart-network'
                        ]
                    ]
                ]
            );
    }


}
