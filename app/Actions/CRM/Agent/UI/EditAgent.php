<?php

namespace App\Actions\CRM\Agent\UI;

use App\Actions\OrgAction;
use App\Models\CRM\Livechat\ChatAgent;
use App\Models\SysAdmin\Organisation;
use App\Models\HumanResources\Employee;
use App\Enums\CRM\Livechat\ChatAgentSpecializationEnum;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditAgent extends OrgAction
{
    /**
     * Load model
     */
    public function handle(ChatAgent $agent): ChatAgent
    {
        return $agent;
    }

    /**
     * Controller endpoint
     */
    public function asController(Organisation $organisation, $agentId, ActionRequest $request)
    {
        $agent = ChatAgent::findOrFail($agentId);

        $this->initialisation($organisation, $request);

        return $this->handle($agent);
    }

    /**
     * Inertia response
     */
    public function htmlResponse(ChatAgent $agent, ActionRequest $request): Response
    {

        $employees = Employee::whereNotNull('user_id')
            ->orderBy('contact_name')
            ->get()
            ->map(fn ($employee) => [
                'label' => $employee->contact_name ?? $employee->alias ?? 'Unnamed',
                'value' => $employee->user_id,
            ])
            ->values()
            ->toArray();


        return Inertia::render(
            'EditModel',
            [
                'title' => __('Edit CRM Agent'),

                'pageHead' => [
                    'title' => __('Edit '). $agent->user->contact_name ?? __('Agent'),
                    'icon'  => [
                        'title' => __('CRM Agent'),
                        'icon'  => 'fal fa-headset',
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters()),
                            ]
                        ]
                    ]
                ],

                'formData' => [
                    'blueprint' => [

                        [
                            'label'  => __('Agent'),
                            'title'  => __('Edit Agent'),
                            'fields' => [
                                'user_id' => [
                                    'type'        => 'input',
                                    'label'       => __('Agent'),
                                    'readonly'    => true,
                                    'value'       => $agent->user->contact_name,
                                ],
                            ],
                        ],

                        [
                            'label'  => __('Max Concurrent Chats'),
                            'title'  => __('Edit Max Concurrent Chats'),
                            'fields' => [
                                'max_concurrent_chats' => [
                                    'type'     => 'input_number',
                                    'label'    => __('Max Concurrent Chats'),
                                    'value'    => $agent->max_concurrent_chats,
                                    'required' => true,
                                ],
                            ],
                        ],

                        [
                            'label'  => __('Specialization'),
                            'title'  => __('Edit Specialization'),
                            'fields' => [
                                'specialization' => [
                                    'type'      => 'multiselect-tags',
                                    'label'     => __('Specialization'),
                                    'options'   => ChatAgentSpecializationEnum::options(),
                                    'labelProp' => 'label',
                                    'valueProp' => 'value',
                                    'value'     => $agent->specialization ?? [],
                                ],
                            ],
                        ],

                        [
                            'label'  => __('Auto Accept'),
                            'title'  => __('Edit Auto Accept'),
                            'fields' => [
                                'auto_accept' => [
                                    'type'  => 'toggle',
                                    'label' => __('Auto Accept'),
                                    'value' => $agent->auto_accept,
                                ],
                            ],
                        ],

                    ],

                    'args' => [
                        'updateRoute' => [
                            'name'       => 'grp.org.crm.agents.update',
                            'parameters' => [
                                $this->organisation->slug,
                                $agent->id,
                            ],
                        ],
                    ],
                ]

            ]
        );
    }
}
