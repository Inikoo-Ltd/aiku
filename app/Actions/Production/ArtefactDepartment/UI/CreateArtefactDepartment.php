<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 02 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ArtefactDepartment\UI;

use App\Actions\OrgAction;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateArtefactDepartment extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("productions_rd.{$this->production->id}.edit");
    }

    public function asController(Organisation $organisation, Production $production, ActionRequest $request): Response
    {
        $this->initialisationFromProduction($production, $request);

        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('New artefact department'),
                'pageHead'    => [
                    'model'   => __('Create'),
                    'title'   => __('New artefact department'),
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'route' => [
                                'name'       => 'grp.org.productions.show.crafts.artefact_departments.index',
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
                                'code'        => ['type' => 'input', 'label' => __('Code'), 'required' => true],
                                'name'        => ['type' => 'input', 'label' => __('Name'), 'required' => true],
                                'description' => ['type' => 'textarea', 'label' => __('Description'), 'required' => false],
                            ]
                        ]
                    ],
                    'route'     => [
                        'name'       => 'grp.models.production.artefact_departments.store',
                        'parameters' => [$production->id]
                    ]
                ],
            ]
        );
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            IndexArtefactDepartments::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => ['label' => __('Creating artefact department')]
                ]
            ]
        );
    }
}
