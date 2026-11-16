<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026 17:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\PackingBench\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseEditAuthorisation;
use App\Models\Dispatching\PackingBench;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditPackingBench extends OrgAction
{
    use WithWarehouseEditAuthorisation;

    public function asController(Organisation $organisation, Warehouse $warehouse, PackingBench $packingBench, ActionRequest $request): Response
    {
        abort_unless($packingBench->warehouse_id === $warehouse->id, 404);
        $this->initialisationFromWarehouse($warehouse, $request);

        return Inertia::render(
            'EditModel',
            [
                'title'       => __('Packing bench'),
                'breadcrumbs' => IndexPackingBenches::make()->getBreadcrumbs(
                    routeParameters: $request->route()->originalParameters(),
                    suffix: '('.__('Editing').')'
                ),
                'pageHead'    => [
                    'model'   => __('Edit packing bench'),
                    'title'   => $packingBench->code,
                    'icon'    => [
                        'title' => __('Packing bench'),
                        'icon'  => 'fal fa-video'
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => 'grp.org.warehouses.show.dispatching.packing_benches.index',
                                'parameters' => [$organisation->slug, $warehouse->slug]
                            ]
                        ]
                    ]
                ],
                'formData'    => [
                    'blueprint' => [
                        [
                            'label'  => __('Properties'),
                            'fields' => [
                                'code'   => [
                                    'type'  => 'input',
                                    'label' => __('Code'),
                                    'value' => $packingBench->code
                                ],
                                'status' => [
                                    'type'  => 'toggle',
                                    'label' => __('Active'),
                                    'value' => $packingBench->status
                                ],
                            ]
                        ]
                    ],
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.warehouse.packing_benches.update',
                            'parameters' => [
                                'warehouse'    => $packingBench->warehouse_id,
                                'packingBench' => $packingBench->id
                            ]
                        ],
                    ]
                ]
            ]
        );
    }
}
