<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jun 2026 21:09:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Agent\UI;

use App\Actions\Helpers\Language\UI\GetLanguagesOptions;
use App\Actions\Helpers\Organisation\UI\GetOrganisationOptions;
use App\Actions\Helpers\Shop\UI\GetShopOptions;
use App\Actions\OrgAction;
use App\Enums\CRM\Livechat\ChatAgentSpecializationEnum;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

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
            'name'       => 'grp.org.chat.agents.store',
            'parameters' => [
                'organisation' => $organisation->slug,
            ]
        ];

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
                                    'type'        => 'select_infinite',
                                    'information' => __('Only active employees are listed'),
                                    'label'       => __('Agent'),
                                    'placeholder' => __('Select user'),
                                    'required'    => true,
                                    'mode'        => 'single',
                                    'searchable'  => true,
                                    'labelProp'   => 'contact_and_org_code',
                                    'valueProp'   => 'id',
                                    'fetchRoute'  => [
                                        'name'       => 'grp.search.get_users',
                                    ],
                                ],

                                'organisation_id' => [
                                    'type'        => 'select',
                                    'label'       => __('Organisation'),
                                    'placeholder' => __('Select organisation'),
                                    'options'     => GetOrganisationOptions::make()->filter($organisation->slug),
                                    'required'    => true,
                                    'mode'        => 'single',
                                    'searchable'  => true,
                                    'key'         => 'organisation_select'
                                ],

                                'shop_id' => [
                                    'type'     => 'multiselect-tags',
                                    'placeholder' => __('Select shops'),
                                    'label'       => __('Shop'),
                                    'options'     => GetShopOptions::run($organisation->slug),
                                    'required'    => false,
                                    'labelProp' => 'label',
                                    'valueProp' => 'value',
                                ],

                                'language_id' => [
                                    'type'        => 'select',
                                    'label'       => __('Language'),
                                    'placeholder' => __('Select language'),
                                    'required'    => true,
                                    'options'     => GetLanguagesOptions::make()->translated(),
                                    'labelProp' => 'label',
                                    'valueProp' => 'value',
                                ],

                                'max_concurrent_chats' => [
                                    'type'        => 'input_number',
                                    'label'       => __('Max Concurrent Chats'),
                                    'information' => __('The maximum number of chats this agent can handle at the same time.'),
                                    'bind'        => [
                                        'min' => 0,
                                    ],
                                    'required'    => true,
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
                                    'value'  => true,
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
