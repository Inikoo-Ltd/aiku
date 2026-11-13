<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026 17:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\PackingBench\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseEditAuthorisation;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreatePackingBench extends OrgAction
{
    use WithWarehouseEditAuthorisation;

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): Response
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => array_merge(
                    IndexPackingBenches::make()->getBreadcrumbs($request->route()->originalParameters()),
                    [
                        [
                            'type'          => 'creatingModel',
                            'creatingModel' => [
                                'label' => __('Creating packing bench'),
                            ]
                        ]
                    ]
                ),
                'title'       => __('Create packing bench'),
                'pageHead'    => [
                    'model'   => __('Packing bench'),
                    'title'   => __('Create'),
                    'icon'    => 'fal fa-video',
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('Cancel'),
                            'route' => [
                                'name'       => 'grp.org.warehouses.show.dispatching.packing_benches.index',
                                'parameters' => array_values($request->route()->originalParameters())
                            ],
                        ]
                    ]
                ],
                'formData'    => [
                    'blueprint' => [
                        [
                            'title'  => __('Packing bench'),
                            'fields' => [
                                'code' => [
                                    'type'        => 'input',
                                    'label'       => __('Code'),
                                    'placeholder' => __('The number written on the table, e.g. 3'),
                                    'value'       => '',
                                    'required'    => true,
                                ],
                            ]
                        ],
                    ],
                    'route'     => [
                        'name'       => 'grp.models.warehouse.packing_benches.store',
                        'parameters' => $warehouse->id
                    ]
                ],
            ]
        );
    }
}
