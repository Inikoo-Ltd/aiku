<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Dec 2024 09:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgAgent\UI;

use App\Actions\SupplyChain\Agent\UI\WithAgentEditFields;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Actions\OrgAction;
use App\Models\Procurement\OrgAgent;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditOrgAgent extends OrgAction
{
    use WithProcurementAuthorisation;
    use WithAgentEditFields;

    public function handle(OrgAgent $orgAgent): OrgAgent
    {
        return $orgAgent;
    }

    public function asController(Organisation $organisation, OrgAgent $orgAgent, ActionRequest $request): OrgAgent
    {
        $this->initialisation($organisation, $request);

        return $this->handle($orgAgent);
    }

    protected function ownsAgent(OrgAgent $orgAgent): bool
    {
        return $orgAgent->agent->organisation_id === $this->organisation->id;
    }

    public function htmlResponse(OrgAgent $orgAgent, ActionRequest $request): Response
    {
        $agent = $orgAgent->agent;

        $blueprint = $this->ownsAgent($orgAgent)
            ? $this->agentEditSections($agent)
            : [
                [
                    'title'  => __('Agent Details'),
                    'icon'   => 'fal fa-address-book',
                    'fields' => [
                        'code' => [
                            'type'     => 'input',
                            'label'    => __('Code'),
                            'value'    => $agent->code,
                            'required' => true,
                            'readonly' => true,
                        ],
                        'name' => [
                            'type'     => 'input',
                            'label'    => __('Name'),
                            'value'    => $agent->organisation->name,
                            'readonly' => true,
                        ],
                    ],
                ],
            ];

        $updateRoute = $this->ownsAgent($orgAgent)
            ? [
                'name'       => 'grp.models.agent.update',
                'parameters' => $agent->id,
            ]
            : [
                'name'       => 'grp.models.org_agent.update',
                'parameters' => $orgAgent->id,
            ];

        return Inertia::render(
            'EditModel',
            [
                'title'       => __('Edit Agent'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($orgAgent, $request),
                    'next'     => $this->getNext($orgAgent, $request),
                ],
                'pageHead'    => [
                    'title'   => $orgAgent->agent->organisation->name,
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => preg_replace('/\.edit$/', '', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters()),
                            ],
                        ],
                    ],
                ],

                'formData' => [
                    'blueprint' => $blueprint,
                    'args' => [
                        'updateRoute' => $updateRoute,
                    ],
                ],
            ]
        );
    }

    public function getBreadcrumbs(array $routeParameters, $suffix = null): array
    {
        return ShowOrgAgent::make()->getBreadcrumbs(
            routeName: 'grp.org.procurement.org_agents.show.edit',
            routeParameters: $routeParameters,
            suffix: '(' . __('Editing') . ')'
        );
    }

    public function getPrevious(OrgAgent $orgAgent, ActionRequest $request): ?array
    {
        $previous = OrgAgent::where('slug', '<', $orgAgent->slug)->orderBy('slug', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(OrgAgent $orgAgent, ActionRequest $request): ?array
    {
        $next = OrgAgent::where('slug', '>', $orgAgent->slug)->orderBy('slug')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?OrgAgent $orgAgent, string $routeName): ?array
    {
        if (!$orgAgent) {
            return null;
        }

        return match ($routeName) {
            'grp.org.procurement.org_agents.show.edit' => [
                'label' => $orgAgent->agent->organisation->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $orgAgent->organisation->slug,
                        'orgAgent'     => $orgAgent->slug
                    ]
                ]
            ],
            default => null
        };
    }
}
