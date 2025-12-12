<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Nov 2023 00:45:40 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Prospect\Queries\UI;

use App\Actions\InertiaAction;
use App\Actions\CRM\Prospect\UI\IndexProspects;
use App\Models\Catalogue\Shop;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateProspectQuery extends InertiaAction
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
                'title'       => __('New prospect list'),
                'pageHead'    => [
                    'title'   => __('New prospect list'),
                    'icon'    => [
                        'icon'  => ['fal', 'fa-code-branch'],
                        'title' => __('Prospect list')
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('Cancel'),
                            'route' => [
                                'name'       => preg_replace('/create$/', 'index', $request->route()->getName()),
                                'parameters' => array_merge(
                                    $request->route()->originalParameters(),
                                    [
                                        '_query' => [
                                            'tab' => 'lists'
                                        ]
                                    ]
                                )
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
                                    'name'          => [
                                        'type'  => 'input',
                                        'label' => __('Name')
                                    ],
                                    'query_builder' => [
                                        'type'      => 'prospectQueryBuilder',
                                        'label'     => __('query by'),
                                        'full'      => true,
                                    ],


                                ]
                            ],
                        ],
                    'route'     =>
                        [
                            'name'       => 'org.models.shop.prospect-query.store',
                            'parameters' => [$parent->id]
                        ]
                ]
            ]
        );
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo('crm.edit');
    }


    public function inShop(Shop $shop, ActionRequest $request): Response
    {
        $this->initialisation($request);

        return $this->handle($shop, $request);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            IndexProspects::make()->getBreadcrumbs(
                routeName: 'grp.org.shops.show.crm.prospects.index',
                routeParameters: $routeParameters,
            ),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating prospect list'),
                    ]
                ]
            ]
        );
    }
}
