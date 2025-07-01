<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 18-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Dropshipping\Poll\UI;

use App\Actions\RetinaAction;
use App\Actions\Traits\WithCustomersSubNavigation;
use App\Enums\CRM\Poll\PollTypeEnum;
use App\Http\Resources\CRM\PollOptionsResource;
use App\Models\CRM\Poll;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelOptions\Options;
use Lorisleiva\Actions\ActionRequest;

class EditRetinaPoll extends RetinaAction
{
    use WithCustomersSubNavigation;

    public function handle(Poll $poll): Poll
    {
        return $poll;
    }

    public function asController(Poll $poll, ActionRequest $request): Poll
    {
        $this->initialisation($request);

        return $this->handle($poll);
    }

    /**
     * @throws \Exception
     */
    public function htmlResponse(Poll $poll, ActionRequest $request): Response
    {
        $optionsPool = [];
        $options     = $poll->pollOptions;
        if ($options->isNotEmpty()) {
            $optionsPool = PollOptionsResource::collection($poll->pollOptions)->toArray($request);
        }

        return Inertia::render(
            'EditModel',
            [
                'title'       => __('poll'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
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
                                'name'                     => [
                                    'type'  => 'input',
                                    'label' => __('name'),
                                    'value' => $poll->name
                                ],
                                'label'                    => [
                                    'type'  => 'input',
                                    'label' => __('label'),
                                    'value' => $poll->label
                                ],
                                'type'                     => [
                                    'type'         => 'poll_type_select',
                                    'type_options' => $optionsPool,
                                    'label'        => __('type'),
                                    'options'      => Options::forEnum(PollTypeEnum::class),
                                    'value'        => [
                                        'type'         => $poll->type->value,
                                        'poll_options' => $optionsPool
                                    ]
                                ],
                                'in_registration'          => [
                                    'type'  => 'toggle',
                                    'label' => __('in registration'),
                                    'value' => $poll->in_registration
                                ],
                                'in_registration_required' => [
                                    'type'  => 'toggle',
                                    'label' => __('registration required'),
                                    'value' => $poll->in_registration_required
                                ],
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

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return ShowRetinaPoll::make()->getBreadcrumbs(
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '('.__('Editing').')'
        );
    }
}
