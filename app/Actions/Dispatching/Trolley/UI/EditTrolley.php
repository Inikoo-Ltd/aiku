<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Feb 2026 14:32:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Trolley\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseEditAuthorisation;
use App\Models\Dispatching\Trolley;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditTrolley extends OrgAction
{
    use WithWarehouseEditAuthorisation;

    public function handle(Trolley $trolley): Trolley
    {
        return $trolley;
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, Trolley $trolley, ActionRequest $request): Trolley
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($trolley);
    }

    public function htmlResponse(Trolley $trolley, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'title'       => __('Edit trolley').' '.$trolley->name,
                'breadcrumbs' => ShowTrolley::make()->getBreadcrumbs(
                    routeName: $request->route()->getName(),
                    routeParameters: $request->route()->originalParameters(),
                    suffix: '('.__('Editing').')'
                ),
                'pageHead'    => [
                    'model'   => __('Edit Picking Trolley'),
                    'title'   => $trolley->name,
                    'icon'    => [
                        'title' => __('Picking trolley'),
                        'icon'  => 'fal fa-dolly-flatbed-alt'
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ]
                ],
                'formData'    => [
                    'blueprint' => [
                        [
                            'label'  => __('Properties'),
                            'fields' => [
                                'name' => [
                                    'type'  => 'input',
                                    'label' => __('Name/Id'),
                                    'value' => $trolley->name
                                ],
                            ]
                        ]
                    ],
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.trolleys.update',
                            'parameters' => [
                                $trolley->id
                            ]
                        ],
                    ]
                ]
            ]
        );
    }
}
