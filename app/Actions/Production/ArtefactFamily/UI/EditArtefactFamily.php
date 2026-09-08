<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 08 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ArtefactFamily\UI;

use App\Actions\OrgAction;
use App\Models\Production\ArtefactFamily;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditArtefactFamily extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("productions_rd.{$this->production->id}.edit");
    }

    public function asController(Organisation $organisation, Production $production, ArtefactFamily $artefactFamily, ActionRequest $request): Response
    {
        $this->initialisationFromProduction($production, $request);

        return Inertia::render(
            'EditModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Edit artefact family'),
                'pageHead'    => [
                    'title'   => __('Edit artefact family'),
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => 'grp.org.productions.show.crafts.artefact_families.show',
                                'parameters' => $request->route()->originalParameters()
                            ],
                        ]
                    ]
                ],
                'formData'    => [
                    'blueprint' => [
                        [
                            'title'  => __('Artefact family'),
                            'fields' => [
                                'artefact_department_id' => [
                                    'type'     => 'select',
                                    'label'    => __('Department'),
                                    'required' => true,
                                    'value'    => $artefactFamily->artefact_department_id,
                                    'options'  => CreateArtefactFamily::make()->getDepartmentOptions($production),
                                ],
                                'code'                   => ['type' => 'input', 'label' => __('Code'), 'value' => $artefactFamily->code, 'required' => true],
                                'name'                   => ['type' => 'input', 'label' => __('Name'), 'value' => $artefactFamily->name, 'required' => true],
                                'description'            => ['type' => 'textarea', 'label' => __('Description'), 'value' => $artefactFamily->description, 'required' => false],
                            ]
                        ]
                    ],
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.artefact_family.update',
                            'parameters' => [$artefactFamily->id]
                        ],
                    ]
                ],
            ]
        );
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return ShowArtefactFamily::make()->getBreadcrumbs($routeParameters, suffix: '('.__('Editing').')');
    }
}
