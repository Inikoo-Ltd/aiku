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
use App\Actions\Procurement\WithProcurementSerialReferences;
use App\Enums\Procurement\OrgAgent\OrgAgentOrderContainerEnum;
use App\Models\Procurement\OrgAgent;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditOrgAgent extends OrgAction
{
    use WithProcurementAuthorisation;
    use WithProcurementSerialReferences;
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

        $blueprint[] = [
            'label'  => __('Ordering'),
            'title'  => __('Ordering'),
            'icon'   => 'fal fa-shopping-basket',
            'fields' => [
                'order_container' => [
                    'type'        => 'select',
                    'label'       => __('Container'),
                    'information' => __('The container an order to this agent is measured against, to say how full it is by volume.'),
                    'required'    => true,
                    'options'     => OrgAgentOrderContainerEnum::selectOptions(),
                    'value'       => $orgAgent->order_container->value,
                    'updateRoute' => [
                        'name'       => 'grp.models.org_agent.update',
                        'parameters' => $orgAgent->id,
                    ],
                ],
            ],
        ];

        if (!$this->ownsAgent($orgAgent)) {
            $blueprint[] = $this->moneyRequestSection($orgAgent);
        }

        $blueprint = array_merge($blueprint, $this->procurementSerialReferenceSections($orgAgent, [
            'name'       => 'grp.models.org_agent.update',
            'parameters' => $orgAgent->id,
        ]));

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

    /**
     * @return array<string, mixed>
     */
    private function moneyRequestSection(OrgAgent $orgAgent): array
    {
        $users = User::where('group_id', $orgAgent->group_id)
            ->where('status', true)
            ->orderBy('contact_name')
            ->get(['id', 'contact_name', 'username'])
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->chatName()])
            ->values()
            ->all();

        $updateRoute = [
            'name'       => 'grp.models.org_agent.update',
            'parameters' => $orgAgent->id,
        ];

        return [
            'label'  => __('Money requests'),
            'title'  => __('Money requests'),
            'icon'   => 'fal fa-money-check-alt',
            'fields' => [
                'money_request_payer_user_ids' => [
                    'type'        => 'multiselect-tags',
                    'label'       => __('Emailed to approve and pay'),
                    'information' => __('Emailed when this agent asks for money, and can approve, reject and pay it even without accounting or procurement permission.'),
                    'options'     => $users,
                    'labelProp'   => 'name',
                    'valueProp'   => 'id',
                    'value'       => $orgAgent->moneyRequestPayerIds(),
                    'updateRoute' => $updateRoute,
                ],
                'money_request_cc_user_ids' => [
                    'type'        => 'multiselect-tags',
                    'label'       => __('Emails CC\'d to'),
                    'information' => __('Get a copy of the emails when this agent asks for money, and when it is paid or rejected. Being here does not let them approve or pay.'),
                    'options'     => $users,
                    'labelProp'   => 'name',
                    'valueProp'   => 'id',
                    'value'       => $orgAgent->moneyRequestCcIds(),
                    'updateRoute' => $updateRoute,
                ],
            ],
        ];
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
