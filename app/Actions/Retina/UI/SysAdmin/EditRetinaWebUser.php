<?php

/*
 * author Arya Permana - Kirin
 * created on 09-01-2025-10h-19m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\UI\SysAdmin;

use App\Actions\RetinaAction;
use App\Models\CRM\WebUser;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditRetinaWebUser extends RetinaAction
{
    public function handle(WebUser $webUser): WebUser
    {
        return $webUser;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->is_root;
    }

    public function asController(WebUser $webUser, ActionRequest $request): WebUser
    {
        $this->initialisation($request);

        return $this->handle($webUser);
    }

    public function htmlResponse(WebUser $webUser, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'title'       => __('Web user'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'           => [
                    'previous' => $this->getPrevious($webUser, $request),
                    'next'     => $this->getNext($webUser, $request),
                ],
                'pageHead'    => [
                    'title'     => __('Edit web user'),
                    'meta'      => [
                        [
                            'name' => $webUser->username
                        ]
                    ],
                    'actions'   => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ],
                ],

                'formData' => [
                    'blueprint' => [
                        [
                            'title'   => __('properties'),
                            'label'   => __('properties'),
                            'icon'    => 'fa-light fa-key',
                            'current' => true,
                            'fields'  => array_merge([
                                'contact_name' => [
                                    'type'  => 'input',
                                    'label' => __('contact name'),
                                    'value' => $webUser->contact_name
                                ],
                                'email' => [
                                    'type'  => 'input',
                                    'label' => __('email'),
                                    'value' => $webUser->email
                                ],
                                'username' => [
                                    'type'  => 'input',
                                    'label' => __('username'),
                                    'value' => $webUser->username
                                ],
                                'password' => [
                                    'type'  => 'password',
                                    'label' => __('password'),
                                    'value' => ''
                                ],
                            ], $webUser->is_root ? [] : [
                                'status' => [
                                    'type'  => 'toggle',
                                    'label' => __('Status'),
                                    'value' => $webUser->status
                                ]
                            ])
                        ],
                        [
                            'label'   => __('Delete'),
                            'icon'    => 'fa-light fa-trash',
                            'fields'  => [
                                'delete' => [
                                    'type'  => 'action',
                                    'action' => [
                                        'label' => 'delete',
                                        'type' => 'delete',
                                        'route' => [
                                            'name' => 'retina.models.web-users.delete',
                                            'parameters' => [
                                                'webUser' => $webUser->id
                                            ],
                                            'method'      => 'delete'
                                        ]
                                    ],
                                ],
                            ]
                        ]
                    ],
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'retina.models.web-users.update',
                            'parameters' => [$webUser->id]

                        ],
                    ]
                ]
            ]
        );
    }


    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return ShowRetinaWebUser::make()->getBreadcrumbs(
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '('.__('Editing').')'
        );
    }

    public function getPrevious(WebUser $webUser, ActionRequest $request): ?array
    {
        $previous = WebUser::where('username', '<', $webUser->username)
            ->where('web_users.customer_id', $this->customer->id)
            ->orderBy('username', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(WebUser $webUser, ActionRequest $request): ?array
    {
        $next = WebUser::where('username', '>', $webUser->username)
            ->where('web_users.customer_id', $this->customer->id)
            ->orderBy('username')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?WebUser $webUser, string $routeName): ?array
    {
        if (!$webUser) {
            return null;
        }

        return match ($routeName) {
            'retina.sysadmin.web-users.edit' => [
                'label' => $webUser->username,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'webUser' => $webUser->slug,
                    ]

                ]
            ],
        };
    }
}
