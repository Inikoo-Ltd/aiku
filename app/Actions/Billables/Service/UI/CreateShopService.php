<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 20 Nov 2024 15:21:28 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Billables\Service\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateShopService extends OrgAction
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
                'title'       => __('New service'),
                'pageHead'    => [
                    'title'   => __('New service'),
                    'icon'    => [
                        'icon'  => ['fal', 'fa-concierge-bell'],
                        'title' => __('Service')
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
                                'title'  => __('Details'),
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
                                    'description' => [
                                        'type'     => 'textarea',
                                        'label'    => __('Description'),
                                        'required' => false,
                                    ],
                                    'price'       => [
                                        'type'     => 'input_number',
                                        'label'    => __('Price'),
                                        'required' => true,
                                    ],
                                    'unit'        => [
                                        'type'     => 'input',
                                        'label'    => __('Unit'),
                                        'required' => true,
                                    ],
                                ]
                            ]
                        ],
                    'route'     => [
                        'name'       => 'grp.models.billables.services.store',
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
            IndexShopServices::make()->getBreadcrumbs(
                routeName: preg_replace('/create$/', 'index', $routeName),
                routeParameters: $routeParameters,
            ),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating service'),
                    ]
                ]
            ]
        );
    }
}
