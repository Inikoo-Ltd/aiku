<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 18-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\CRM\Poll\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Actions\Traits\WithCustomersSubNavigation;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Poll;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditPoll extends OrgAction
{
    use WithCustomersSubNavigation;
    use WithCRMAuthorisation;
    public function handle(Poll $poll): Poll
    {
        return $poll;
    }

    public function asController(Organisation $organisation, Shop $shop, Poll $poll, ActionRequest $request): Poll
    {
        $this->initialisationFromShop($poll->shop, $request);

        return $this->handle($poll);
    }

    public function htmlResponse(Poll $poll, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'title'       => __('poll'),
                'pageHead'    => [
                    'title'   => $poll->name,
                    'icon'    => [
                        'title' => __('polls'),
                        'icon'  => 'fal fa-cube'
                    ],
                    'actions' => [
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
                    'blueprint' => [
                        [
                            'title'  => __('edit poll'),
                            'fields' => [
                                'name' => [
                                    'type'  => 'input',
                                    'label' => __('name'),
                                    'value' => $poll->name
                                ],
                                'label' => [
                                    'type'  => 'input',
                                    'label' => __('label'),
                                    'value' => $poll->label
                                ],
                                'in_registration' => [
                                    'type'  => 'toggle',
                                    'label' => __('in registration'),
                                    'value' => $poll->in_registration
                                ],
                                'in_registration_required' => [
                                    'type'  => 'toggle',
                                    'label' => __('registration required'),
                                    'value' => $poll->in_registration_required
                                ],
                                // 'in_iris' => [
                                //     'type'  => 'toggle',
                                //     'label' => __('in iris'),
                                //     'value' => $poll->in_iris
                                // ],
                                // 'in_iris_required' => [
                                //     'type'  => 'toggle',
                                //     'label' => __('iris required'),
                                //     'value' => $poll->in_iris_required
                                // ],
                            ],
                        ]
                    ],

                    'args' => [
                        'updateRoute' => [
                            'name'       => 'grp.models.poll.update',
                            'parameters' => $poll->id
                        ],
                    ]
                ]
            ]
        );
    }
}
