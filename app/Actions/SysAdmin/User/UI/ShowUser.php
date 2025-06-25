<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:24:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User\UI;

use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\HumanResources\Employee\UI\ShowEmployee;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\UI\ShowSysAdminDashboard;
use App\Actions\SysAdmin\User\WithUserSubNavigation;
use App\Enums\UI\SysAdmin\UserTabsEnum;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\SysAdmin\ApiTokensResource;
use App\Http\Resources\SysAdmin\UserResource;
use App\Http\Resources\SysAdmin\UserShowcaseResource;
use App\Models\HumanResources\Employee;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowUser extends OrgAction
{
    use WithUserSubNavigation;


    private mixed $authScope;

    public function asController(User $user, ActionRequest $request): User
    {
        $group           = app('group');
        $this->authScope = $group;
        $this->initialisationFromGroup($group, $request)->withTab(UserTabsEnum::values());

        return $user;
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inEmployee(Organisation $organisation, Employee $employee, User $user, ActionRequest $request): User
    {
        $this->authScope = $organisation;
        $this->initialisation($organisation, $request)->withTab(UserTabsEnum::values());

        return $user;
    }

    public function jsonResponse(User $user): UserResource
    {
        return new UserResource($user);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->authScope instanceof Group) {
            $this->canEdit = $request->user()->authTo('sysadmin.edit');

            return $request->user()->authTo("sysadmin.view");
        } else {
            $this->canEdit = $request->user()->authTo("human-resources.{$this->organisation->id}.view");

            return $request->user()->authTo("human-resources.{$this->organisation->id}.view");
        }
    }

    public function htmlResponse(User $user, ActionRequest $request): Response
    {
        return Inertia::render(
            'SysAdmin/User',
            [
                'title'                       => __('user'),
                'breadcrumbs'                 => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'                  => $this->authScope instanceof Group
                    ? [
                        'previous' => $this->getPrevious($user, $request),
                        'next'     => $this->getNext($user, $request),
                    ] : null,
                'pageHead'                    => [
                    'model'         => __('user'),
                    'icon'          =>
                        [
                            'icon'  => ['fal', 'fa-user-circle'],
                            'title' => __('user')
                        ],
                    'subNavigation' => $this->getUserNavigation($user, $request),
                    'title'         => $user->username,
                    'actions'       => [
                        $this->canEdit ? [
                            'type'  => 'button',
                            'style' => 'edit',
                            'route' => [
                                'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ] : false,
                    ]
                ],
                'tabs'                        => [
                    'current'    => $this->tab,
                    'navigation' => UserTabsEnum::navigation($user)
                ],
                'apiRoutes'                   => [
                    'createToken' => [
                        'name'       => 'grp.models.user.access-token.create',
                        'parameters' => ['user' => $user->id]
                    ],
                ],
                UserTabsEnum::SHOWCASE->value => $this->tab == UserTabsEnum::SHOWCASE->value ?
                    fn () => UserShowcaseResource::make($user)
                    : Inertia::lazy(fn () => UserShowcaseResource::make($user)),

                UserTabsEnum::API_TOKENS->value => $this->tab == UserTabsEnum::API_TOKENS->value ?
                    fn () => ApiTokensResource::collection(IndexApiTokens::run($user, prefix: UserTabsEnum::API_TOKENS->value))
                    : Inertia::lazy(fn () => ApiTokensResource::collection(IndexApiTokens::run($user, prefix: UserTabsEnum::API_TOKENS->value))),

                UserTabsEnum::HISTORY->value => $this->tab == UserTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($user))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($user)))

            ]
        )->table(IndexHistory::make()->tableStructure(prefix: UserTabsEnum::HISTORY->value))
            ->table(
                IndexApiTokens::make()->tableStructure(prefix: UserTabsEnum::API_TOKENS->value)
            );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, string $suffix = ''): array
    {
        $headCrumb = function (User $user, array $routeParameters, string $suffix) {
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
                            'label' => $user->username,
                        ],

                    ],
                    'suffix'         => $suffix

                ],
            ];
        };

        $employee = null;
        $user     = User::where('username', $routeParameters['user'])->first();
        if (Arr::has($routeParameters, 'employee')) {
            $employee = Employee::where('slug', $routeParameters['employee'])->first();
        }


        return match ($routeName) {
            'grp.sysadmin.users.show',
            'grp.sysadmin.users.edit' =>

            array_merge(
                ShowSysAdminDashboard::make()->getBreadcrumbs(),
                $headCrumb(
                    $user,
                    [
                        'index' => [
                            'name'       => 'grp.sysadmin.users.index',
                            'parameters' => []
                        ],
                        'model' => [
                            'name'       => 'grp.sysadmin.users.show',
                            'parameters' => $user->username
                        ]
                    ],
                    $suffix
                ),
            ),
            'grp.org.hr.employees.show.users.show' =>
            array_merge(
                ShowEmployee::make()->getBreadcrumbs($employee, $routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => $routeName,
                                'parameters' => $routeParameters
                            ],
                            'label' => $user->username,
                            'icon'  => 'fal fa-user-circle',
                        ]
                    ],

                ],
            ),

            default => []
        };
    }

    public function getPrevious(User $user, ActionRequest $request): ?array
    {
        $previous = User::where('username', '<', $user->username)->orderBy('username', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(User $user, ActionRequest $request): ?array
    {
        $next = User::where('username', '>', $user->username)->orderBy('username')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?User $user, string $routeName): ?array
    {
        if (!$user) {
            return null;
        }

        return match ($routeName) {
            'grp.sysadmin.users.show' => [
                'label' => $user->username,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'user' => $user->username
                    ]

                ]
            ]
        };
    }
}
