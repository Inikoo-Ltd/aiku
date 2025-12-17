<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 20 Nov 2024 15:21:28 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Billables\Charge\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\Catalogue\Charge\ChargeTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\LaravelOptions\Options;

class CreateCharge extends OrgAction
{
    use WithCatalogueAuthorisation;

    /**
     * @throws \Exception
     */
    public function handle(Shop $shop, ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('New charge'),
                'pageHead'    => [
                    'title'   => __('New charge'),
                    'icon'    => [
                        'icon'  => ['fal', 'fa-charging-station'],
                        'title' => __('Charge')
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'label' => __('Cancel'),
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
                                    'code'        => [
                                        'type'     => 'input',
                                        'label'    => __('Code'),
                                        'required' => true,
                                    ],
                                    'name'        => [
                                        'type'     => 'input',
                                        'label'    => __('Name'),
                                        'required' => true,
                                    ],
                                    'label' => [
                                        'type'  => 'input',
                                        'information'   => __("This will show in customer's order"),
                                        'label' => __('Label'),
                                        'required' => true,
                                    ],
                                    'description' => [
                                        'type'     => 'textarea',
                                        'information'   => __("This will show in customer's order as a tooltip"),
                                        'label'    => __('Description'),
                                        'required' => true,
                                    ],

                                    'type'        => [
                                        'type'     => 'select',
                                        'label'    => __('Type'),
                                        'required' => true,
                                        'options'  => Options::forEnum(ChargeTypeEnum::class),
                                    ],

                                ]
                            ]
                        ],
                    'route'     => [
                        'name'       => 'grp.models.billables.charges.store',
                        'parameters' => [
                            'shop' => $shop->id
                        ]
                    ]
                ]

            ]
        );
    }

    /**
     * @throws \Exception
     */
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
                        'label' => __('Creating charge'),
                    ]
                ]
            ]
        );
    }
}
