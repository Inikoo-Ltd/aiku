<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 02 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ArtefactFamily\UI;

use App\Actions\OrgAction;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateArtefactFamily extends OrgAction
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
                'title'       => __('New artefact family'),
                'pageHead'    => [
                    'title'   => __('New artefact family'),
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'route' => [
                                'name'       => 'grp.org.productions.show.crafts.artefact_families.index',
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
                                'code'        => ['type' => 'input', 'label' => __('Code'), 'required' => true],
                                'name'        => ['type' => 'input', 'label' => __('Name'), 'required' => true],
                                'description' => ['type' => 'textarea', 'label' => __('Description'), 'required' => false],
                            ]
                        ]
                    ],
                    'route'     => [
                        'name'       => 'grp.models.production.artefact_families.store',
                        'parameters' => [$production->id]
                    ]
                ],
            ]
        );
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            IndexArtefactFamilies::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => ['label' => __('Creating artefact family')]
                ]
            ]
        );
    }
}
