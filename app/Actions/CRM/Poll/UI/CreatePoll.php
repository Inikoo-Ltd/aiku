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
use App\Enums\CRM\Poll\PollTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Spatie\LaravelOptions\Options;
use Lorisleiva\Actions\ActionRequest;

class CreatePoll extends OrgAction
{
    use WithCustomersSubNavigation;
    use WithCRMAuthorisation;

    /**
     * @throws \Exception
     */
    public function handle(ActionRequest $request): \Inertia\Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'title'       => __('poll'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    'title'   => __('new poll'),
                    'icon'    => [
                        'title' => __('polls'),
                        'icon'  => 'fal fa-cube'
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('cancel'),
                            'route' => [
                                'name'       => preg_replace('/create$/', 'index', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ],
                        ]
                    ]
                ],

                'formData' => [
                    'blueprint' => [
                        [
                            'title'  => __('new poll'),
                            'fields' => [
                                'name'                     => [
                                    'type'     => 'input',
                                    'label'    => __('name'),
                                    'required' => true
                                ],
                                'type'                     => [
                                    'type'     => 'poll_type_select',
                                    'label'    => __('type'),
                                    'required' => true,
                                    'options'  => Options::forEnum(PollTypeEnum::class),
                                    'value'    => [
                                        'type'         => PollTypeEnum::OPTION->value,
                                        'poll_options' => []
                                    ]
                                ],
                                'label'                    => [
                                    'type'     => 'input',
                                    'label'    => __('label'),
                                    'required' => true
                                ],
                                'in_registration'          => [
                                    'type'  => 'toggle',
                                    'label' => __('in registration'),
                                    'value' => false
                                ],
                                'in_registration_required' => [
                                    'type'  => 'toggle',
                                    'label' => __('registration required'),
                                    'value' => false
                                ],
                            ],
                        ]
                    ],

                    'route' => [
                        'name'       => 'grp.models.poll.store',
                        'parameters' => [
                            'shop' => $this->shop->slug,
                        ]
                    ]
                ]
            ]
        );
    }

    /**
     * @throws \Exception
     */
    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): \Inertia\Response
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($request);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            IndexPolls::make()->getBreadcrumbs(
                routeName: preg_replace('/create$/', 'index', $routeName),
                routeParameters: $routeParameters,
            ),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating poll'),
                    ]
                ]
            ]
        );
    }
}
