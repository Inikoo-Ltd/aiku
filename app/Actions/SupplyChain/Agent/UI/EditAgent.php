<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 04 Apr 2024 19:02:34 Central Indonesia Time, Bali Office, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Agent\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithSupplyChainEditAuthorisation;
use App\Models\SupplyChain\Agent;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditAgent extends OrgAction
{
    use WithSupplyChainEditAuthorisation;
    use WithAgentEditFields;

    public function handle(Agent $agent): Agent
    {
        return $agent;
    }

    public function asController(Agent $agent, ActionRequest $request): RedirectResponse|Agent
    {
        $this->initialisationFromGroup($agent->group, $request);

        return $this->handle($agent);
    }


    public function htmlResponse(Agent $agent, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'title'       => __('Edit agent'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $agent,
                    $request->route()->originalParameters()
                ),
                'navigation'                              => [
                    'previous' => $this->getPrevious($agent, $request),
                    'next'     => $this->getNext($agent, $request),
                ],
                'pageHead'    => [
                    'title'     => $agent->code,
                    'actions'   => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ]
                ],

                'formData' => [
                    'blueprint' => $this->agentEditSections($agent),

                    'args' => [
                        'updateRoute' => [
                            'name'       => 'grp.models.agent.update',
                            'parameters' => $agent->id

                        ],
                    ]
                ]
            ]
        );
    }


    public function getBreadcrumbs(Agent $agent, array $routeParameters): array
    {
        return ShowAgent::make()->getBreadcrumbs(
            $agent,
            routeName: 'grp.supply-chain.agents.edit',
            routeParameters: $routeParameters,
            suffix: '('.__('Editing').')'
        );
    }

    public function getPrevious(Agent $agent, ActionRequest $request): ?array
    {
        $previous = Agent::where('code', '<', $agent->code)->orderBy('code', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(Agent $agent, ActionRequest $request): ?array
    {
        $next = Agent::where('code', '>', $agent->code)->orderBy('code')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?Agent $agent, string $routeName): ?array
    {
        if (!$agent) {
            return null;
        }

        return match ($routeName) {
            'grp.supply-chain.agents.edit' => [
                'label' => $agent->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'agent' => $agent->slug
                    ]

                ]
            ]
        };
    }
}
