<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:32:25 Malaysia Time, Pantai Lembeng, Bali, Id
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Charge\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\HasCatalogueAuthorisation;
use App\Enums\Catalogue\Charge\ChargeStateEnum;
use App\Enums\Catalogue\Charge\ChargeTriggerEnum;
use App\Enums\Catalogue\Charge\ChargeTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\LaravelOptions\Options;

class CreateCharge extends OrgAction
{
    use HasCatalogueAuthorisation;

    public function handle(Shop $shop, ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('new charge'),
                'pageHead'    => [
                    'title'        => __('new charge'),
                    'icon'         => [
                        'icon'  => ['fal', 'fa-charging-station'],
                        'title' => __('Charge')
                    ],
                    'actions'      => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'label' => __('cancel'),
                            'route' => [
                                'name'       => preg_replace('/create$/', 'index', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ],
                        ]
                    ]
                ],
                'formData'    => [
                    'blueprint' =>
                        [
                            [
                                'title'  => __('contact'),
                                'fields' => [
                                    'code' => [
                                        'type'  => 'input',
                                        'label' => __('code'),
                                        'required' => true,
                                    ],
                                    'name' => [
                                        'type'  => 'input',
                                        'label' => __('name'),
                                        'required' => true,
                                    ],
                                    'description' => [
                                        'type'        => 'textarea',
                                        'label'       => __('description'),
                                        'required' => true,
                                    ],
                                    'state' => [
                                        'type'     => 'select',
                                        'label'    => __('state'),
                                        'required' => true,
                                        'options'  => Options::forEnum(ChargeStateEnum::class),
                                    ],
                                    'trigger' => [
                                        'type'     => 'select',
                                        'label'    => __('trigger'),
                                        'required' => true,
                                        'options'  => Options::forEnum(ChargeTriggerEnum::class),
                                    ],
                                    'type' => [
                                        'type'     => 'select',
                                        'label'    => __('type'),
                                        'required' => true,
                                        'options'  => Options::forEnum(ChargeTypeEnum::class),
                                    ],

                                ]
                            ]
                        ],
                    'route'     => [
                        'name'      => 'grp.models.billables.charges.store',
                        'parameters'=> [
                            'shop'         => $shop->id
                            ]
                    ]
                ]

            ]
        );
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);
        return $this->handle($shop, $request);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            IndexCharges::make()->getBreadcrumbs(
                routeName: preg_replace('/create$/', 'index', $routeName),
                routeParameters: $routeParameters,
            ),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating schema'),
                    ]
                ]
            ]
        );
    }
}
