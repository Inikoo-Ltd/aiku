<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Tue, 14 Mar 2023 09:31:03 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Production\Production\UI;

use App\Actions\OrgAction;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateProduction extends OrgAction
{
    public function handle(ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'title'       => __('New production'),
                'pageHead'    => [
                    'title'        => __('New production'),
                    'icon'         => [
                        'title' => __('Create productions'),
                        'icon'  => 'fal fa-industry'
                    ],
                    'actions'      => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('Cancel'),
                            'route' => [
                                'name'       => 'grp.org.productions.index',
                                'parameters' => $request->route()->originalParameters()
                            ],
                        ]
                    ]
                ],
                'formData' => [
                    'blueprint' => [
                        [
                            'title'  => __('Create production'),
                            'fields' => [

                                'code' => [
                                    'type'     => 'input',
                                    'label'    => __('code'),
                                    'value'    => '',
                                    'required' => true
                                ],
                                'name' => [
                                    'type'     => 'input',
                                    'label'    => __('name'),
                                    'value'    => '',
                                    'required' => true
                                ],

                            ]
                        ]
                    ],
                    'route'      => [
                        'name'       => 'grp.models.production.store',
                    ]
                ],

            ]
        );
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("productions.{$this->organisation->id}.edit");
    }


    public function asController(Organisation $organisation, ActionRequest $request): Response
    {
        $this->initialisation($organisation, $request);

        return $this->handle($request);
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            IndexProductions::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'         => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating production'),
                    ]
                ]
            ]
        );
    }
}
