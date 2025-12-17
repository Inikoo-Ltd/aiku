<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Tue, 14 Mar 2023 09:31:03 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Production\Artefact\UI;

use App\Actions\OrgAction;
use App\Enums\Production\Artefact\ArtefactStateEnum;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateArtefact extends OrgAction
{
    protected Production|Organisation $parent;

    public function handle(ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'title'       => __('New artefact'),
                'pageHead'    => [
                    'title'        => __('New artefact'),
                    'icon'         => [
                        'title' => __('Create artefact'),
                        'icon'  => 'fal fa-industry'
                    ],
                    'actions'      => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('Cancel'),
                            'route' => [
                                'name'       => 'grp.org.productions.show.crafts.artefacts.index',
                                'parameters' => $request->route()->originalParameters()
                            ],
                        ]
                    ]
                ],
                'formData' => [
                    'blueprint' => [
                        [
                            'title'  => __('Create artefact'),
                            'fields' => [
                                'code' => [
                                    'type'     => 'input',
                                    'label'    => __('Code'),
                                    'value'    => '',
                                    'required' => true
                                ],
                                'name' => [
                                    'type'     => 'input',
                                    'label'    => __('Name'),
                                    'value'    => '',
                                    'required' => true
                                ],
                                'state' => [
                                    'type'     => 'select',
                                    'options'  => ArtefactStateEnum::values(),
                                    'label'    => __('state'),
                                    'value'    => '',
                                    'required' => false
                                ],
                            ]
                        ]
                    ],
                    'route'      => [
                        'name'       => 'grp.models.production.artefacts.store',
                        'parameters' => [$this->parent->id]
                    ]
                ],

            ]
        );
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->parent instanceof Organisation) {
            $this->canEdit = $request->user()->authTo('org-supervisor.'.$this->organisation->id);

            return $request->user()->authTo(
                [
                    'productions-view.'.$this->organisation->id,
                    'org-supervisor.'.$this->organisation->id
                ]
            );
        }

        $this->canEdit = $request->user()->authTo("productions_rd.{$this->production->id}.edit");

        return $request->user()->authTo("productions_rd.{$this->production->id}.view");
    }


    public function inOrganisation(Organisation $organisation, ActionRequest $request): Response
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request);

        return $this->handle($request);
    }

    public function asController(Organisation $organisation, Production $production, ActionRequest $request): Response
    {
        $this->parent = $production;
        $this->initialisationFromProduction($production, $request);

        return $this->handle($request);
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            IndexArtefacts::make()->getBreadcrumbs(request()->route()->getName(), $routeParameters),
            [
                [
                    'type'         => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating Artefact'),
                    ]
                ]
            ]
        );
    }
}
