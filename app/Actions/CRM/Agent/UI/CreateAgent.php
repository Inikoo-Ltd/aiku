<?php

namespace App\Actions\CRM\Agent\UI;

use Inertia\Inertia;
use Inertia\Response;
use App\Actions\OrgAction;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use App\Models\HumanResources\Employee;
use App\Enums\CRM\Livechat\ChatAgentSpecializationEnum;

class CreateAgent extends OrgAction
{
    public function asController(Organisation $organisation, ActionRequest $request): Response
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, $request);
    }

    public function handle(Organisation $organisation, ActionRequest $request): Response
    {
        $route = [
            'name'       => 'grp.org.crm.agents.store',
            'parameters' => [
                'organisation' => $organisation->slug,
            ]
        ];

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
            'CreateModel',
            [
                'title' => __('Create CRM Agent'),

                'icon'  => [
                    'icon'  => ['fal', 'fa-headset'],
                    'title' => __('Agent'),
                ],

                'pageHead' => [
                    'title'   => __('Create CRM Agent'),
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('Cancel'),
                            'route' => [
                                'name'       => str_replace('create', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters()),
                            ],
                        ],
                    ],
                ],

                'formData' => [
                    'blueprint' => [
                        [
                            'title'  => __('CRM Agent Information'),

                            'fields' => [
                                'user_id' => [
                                    'type'        => 'select',
                                    'label'       => __('Agent'),
                                    'placeholder' => __('Select user'),
                                    'required'    => true,
                                    'options'     => $employees,
                                ],

                                'max_concurrent_chats' => [
                                    'type'     => 'input_number',
                                    'label'    => __('Max Concurrent Chats'),
                                    'required' => true,
                                ],

                                'specialization' => [
                                    'type'     => 'multiselect-tags',
                                    'label'    => __('Specialization'),
                                    'required' => false,
                                    'options'  => ChatAgentSpecializationEnum::options(),
                                    'labelProp' => 'label',
                                    'valueProp' => 'value',
                                ],

                                'auto_accept' => [
                                    'type'  => 'toggle',
                                    'label' => __('Auto Accept'),
                                ],
                            ],
                        ],
                    ],

                    'route' => $route,
                ],
            ]
        );
    }
}
