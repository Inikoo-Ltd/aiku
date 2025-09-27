<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 08 Apr 2024 09:52:43 Central Indonesia Time, Bali Office , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\UI;

use App\Actions\Fulfilment\UI\Catalogue\PhysicalGoods\IndexFulfilmentPhysicalGoods;
use App\Actions\OrgAction;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreatePhysicalGoods extends OrgAction
{
    public function handle(Fulfilment $fulfilment, ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'title'    => __('New goods'),
                'pageHead' => [
                    'title' => __('New goods')
                ],
                'formData' => [
                    'fullLayout' => true,
                    'blueprint'  =>
                        [
                            [
                                'title'  => __('Create Goods'),
                                'fields' => [
                                    'code' => [
                                        'type'       => 'input',
                                        'label'      => __('code'),
                                        'required'   => true
                                    ],
                                    'name' => [
                                        'type'       => 'input',
                                        'label'      => __('name'),
                                        'required'   => true
                                    ],
                                    'price' => [
                                        'type'       => 'input',
                                        'label'      => __('price'),
                                        'required'   => true
                                    ],
                                    'unit' => [
                                        'type'     => 'input',
                                        'label'    => __('unit'),
                                        'required' => true,
                                    ],
                                    'is_main' => [
                                        'type'     => 'toggle',
                                        'label'    => __('main'),
                                        'value'    => true,
                                        'required' => true,
                                    ],
                                    'org_stocks' => [
                                        'value' => []
                                    ]
                                ]
                            ]
                        ],
                    'route' => [
                        'name'       => 'grp.models.org.fulfilment.goods.store',
                        'parameters' => [
                            'organisation' => $fulfilment->organisation_id,
                            'fulfilment'   => $fulfilment->id,
                        ]
                    ]
                ],

            ]
        );
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.edit");
    }

    public function asController(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request): Response
    {
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($fulfilment, $request);
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            IndexFulfilmentPhysicalGoods::make()->getBreadcrumbs(
                routeParameters: $routeParameters,
            ),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating goods'),
                    ]
                ]
            ]
        );
    }

}
