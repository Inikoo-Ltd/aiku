<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 02 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ArtefactDepartment\UI;

use App\Actions\OrgAction;
use App\Models\Production\ArtefactDepartment;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditArtefactDepartment extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo(["org-supervisor.{$this->organisation->id}", "productions_rd.{$this->production->id}.edit"]);
    }

    public function asController(Organisation $organisation, Production $production, ArtefactDepartment $artefactDepartment, ActionRequest $request): Response
    {
        $this->initialisationFromProduction($production, $request);

        return Inertia::render(
            'EditModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Edit artefact department'),
                'pageHead'    => [
                    'title'   => __('Edit artefact department'),
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => 'grp.org.productions.show.crafts.artefact_departments.show',
                                'parameters' => $request->route()->originalParameters()
                            ],
                        ]
                    ]
                ],
                'formData'    => [
                    'blueprint' => [
                        [
                            'title'  => __('Artefact department'),
                            'fields' => [
                                'code'        => ['type' => 'input', 'label' => __('Code'), 'value' => $artefactDepartment->code, 'required' => true],
                                'name'        => ['type' => 'input', 'label' => __('Name'), 'value' => $artefactDepartment->name, 'required' => true],
                                'description' => ['type' => 'textarea', 'label' => __('Description'), 'value' => $artefactDepartment->description, 'required' => false],
                            ]
                        ]
                    ],
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.artefact_department.update',
                            'parameters' => [$artefactDepartment->id]
                        ],
                    ]
                ],
            ]
        );
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return ShowArtefactDepartment::make()->getBreadcrumbs($routeParameters, suffix: '('.__('Editing').')');
    }
}
