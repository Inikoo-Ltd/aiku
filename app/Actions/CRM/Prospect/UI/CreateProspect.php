<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 04 Oct 2023 18:36:33 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Prospect\UI;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateProspect extends OrgAction
{
    public function handle(Shop $parent, ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('new prospect'),
                'pageHead'    => [
                    'title'   => __('new prospect'),
                    'icon'    => [
                        'icon'  => ['fal', 'fa-transporter'],
                        'title' => __('prospect')
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
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
                                    'company_name'    => [
                                        'type'  => 'input',
                                        'label' => __('company')
                                    ],
                                    'first_name'    => [
                                        'type'  => 'input',
                                        'label' => __('first name'),
                                        'required' => true
                                    ],
                                    'last_name'    => [
                                        'type'  => 'input',
                                        'label' => __('last name'),
                                        'required' => false
                                    ],
                                    'email'           => [
                                        'type'  => 'input',
                                        'label' => __('email')
                                    ],
                                    'phone'           => [
                                        'type'  => 'phone',
                                        'label' => __('phone')
                                    ],
                                    'contact_website' => [
                                        'type'      => 'inputWithAddOn',
                                        'label'     => __('website'),
                                        'leftAddOn' => [
                                            'label' => 'https://'
                                        ],
                                    ],

                                ]
                            ],
                        ],
                    'route'     =>
                        match (class_basename($parent)) {
                            'Shop' => [
                                'name'       => 'org.models.shop.prospect.store',
                                'parameters' => [$parent->id]
                            ],
                            default => [
                                [
                                    'name' => 'org.models.prospect.store',
                                ]
                            ]
                        }
                ]

            ]
        );
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo('crm.edit');
    }


    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $request);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request): Response
    {
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($fulfilment->shop, $request);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            IndexProspects::make()->getBreadcrumbs(
                routeName: preg_replace('/create$/', 'index', $routeName),
                routeParameters: $routeParameters,
            ),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating prospect'),
                    ]
                ]
            ]
        );
    }
}
