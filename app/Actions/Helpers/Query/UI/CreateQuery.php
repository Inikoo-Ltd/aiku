<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Sep 2023 18:42:14 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Query\UI;

use App\Actions\InertiaAction;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateQuery extends InertiaAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo('portfolio.edit');
    }


    public function asController(ActionRequest $request): ActionRequest
    {
        $this->initialisation($request);
        return $request;

    }


    public function htmlResponse(ActionRequest $request): Response
    {
        $request->route()->getName();

        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('New query'),
                'pageHead'    => [
                    'title'   => __('query'),
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('Cancel'),
                            'route' => [
                                'name'       => preg_replace('/create$/', 'index', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ],
                        ]
                    ]
                ],
                'formData' => [
                    'blueprint' => [
                        [
                            'title'  => __('Account'),
                            'fields' => [
                                'name' => [
                                    'type'     => 'input',
                                    'label'    => __('name'),
                                    'required' => true,
                                    'value'    => ''
                                ],
                                'base' => [
                                    'type'     => 'input',
                                    'label'    => __('base'),
                                    'required' => true,
                                    'value'    => ''
                                ],
                                'filters' => [
                                    'type'     => 'input',
                                    'label'    => __('filters'),
                                    'required' => true,
                                    'value'    => ''
                                ]
                            ]
                        ],
                    ],
                    'route' => [
                        'name' => 'org.models.query.store',
                    ],
                ],
            ]
        );
    }


    public function getBreadcrumbs(): array
    {
        return array_merge(
            IndexQuery::make()->getBreadcrumbs(
                'org.query.index',
                []
            ),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __("creating query"),
                    ]
                ]
            ]
        );
    }


}
