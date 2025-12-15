<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 May 2024 20:18:42 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Production\UI;

use App\Actions\OrgAction;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditProduction extends OrgAction
{
    public function handle(Production $production): Production
    {
        return $production;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("supervisor.productions.{$this->production->id}");
    }

    public function asController(Organisation $organisation, Production $production, ActionRequest $request): Production
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($production);
    }

    public function htmlResponse(Production $production, ActionRequest $request): Response
    {
        $sections               = [];
        $sections['properties'] = [
            'label'  => __('Properties'),
            'icon'   => 'fal fa-sliders-h',
            'fields' => [
                'code' => [
                    'type'  => 'input',
                    'label' => __('Code'),
                    'value' => $production->code
                ],
                'name' => [
                    'type'  => 'input',
                    'label' => __('Name'),
                    'value' => $production->name
                ]
            ]
        ];

        $currentSection = 'properties';
        if ($request->has('section') and Arr::has($sections, $request->get('section'))) {
            $currentSection = $request->get('section');
        }

        return Inertia::render(
            'EditModel',
            [
                'title'                            => __('Edit production'),
                'breadcrumbs'                      => $this->getBreadcrumbs($request->route()->originalParameters()),
                'navigation'                       => [
                    'previous' => $this->getPrevious($production, $request),
                    'next'     => $this->getNext($production, $request),
                ],
                'pageHead'    => [
                    'title'     => $production->code,
                    'actions'   => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ]
                ],

                'formData' => [
                    'current'   => $currentSection,
                    'blueprint' => $sections,
                    'args'      => [
                        'updateRoute' => [
                            'name'      => 'grp.models.production.update',
                            'parameters' => $production->id
                        ],
                    ]
                ]
            ]
        );
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return ShowProduction::make()->getBreadcrumbs(routeParameters:$routeParameters, suffix: '('.__('Editing').')');
    }

    public function getPrevious(Production $production, ActionRequest $request): ?array
    {
        $previous = Production::where('code', '<', $production->code)->orderBy('code', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(Production $production, ActionRequest $request): ?array
    {
        $next = Production::where('code', '>', $production->code)->orderBy('code')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?Production $production, string $routeName): ?array
    {
        if (!$production) {
            return null;
        }

        return match ($routeName) {
            'grp.org.productions.show.infrastructure.edit' => [
                'label' => $production->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation'  => $production->organisation->slug,
                        'production'    => $production->slug
                    ]
                ]
            ]
        };
    }
}
