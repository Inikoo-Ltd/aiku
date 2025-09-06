<?php

/*
 * author Arya Permana - Kirin
 * created on 09-01-2025-09h-25m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\UI\SysAdmin;

use App\Actions\RetinaAction;
use App\Http\Resources\CRM\WebUserResource;
use App\Models\CRM\WebUser;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowRetinaWebUser extends RetinaAction
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
        $iconRight = [];

        return Inertia::render(
            'SysAdmin/RetinaWebUser',
            [
                'title'       => __('User').': '.$webUser->username,
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'           => [
                    'previous' => $this->getPrevious($webUser, $request),
                    'next'     => $this->getNext($webUser, $request),
                ],
                'pageHead'    => [
                    //'model'         => $model,
                    'title'     => $webUser->username,
                    'icon'      => 'fal fa-user-circle',
                    'noCapitalise'  => true,
                    'iconRight' => $iconRight,
                    'actions'   => [
                        [
                            'type'  => 'button',
                            'style' => 'edit',
                            'route' => [
                                'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ],

                    ],

                ],
                'data'        => new WebUserResource($webUser)
            ]
        );
    }


    public function jsonResponse(WebUser $webUser): WebUserResource
    {
        return new WebUserResource($webUser);
    }


    public function getBreadcrumbs(string $routeName, array $routeParameters, string $suffix = ''): array
    {
        $headCrumb = function (WebUser $webUser, array $routeParameters, string $suffix) {
            return [
                [

                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Users')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $webUser->username,
                        ],

                    ],
                    'suffix'         => $suffix

                ],
            ];
        };

        $webUser = WebUser::where('slug', $routeParameters['webUser'])->first();

        return match ($routeName) {
            'retina.sysadmin.web-users.show' =>
            array_merge(
                ShowRetinaFulfilmentSysAdminDashboard::make()->getBreadcrumbs(),
                $headCrumb(
                    $webUser,
                    [
                        'index' => [
                            'name'       => 'retina.sysadmin.web-users.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'retina.sysadmin.web-users.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                ),
            ),
            default => []
        };
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
            'retina.sysadmin.web-users.show' => [
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
