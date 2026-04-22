<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:24:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User\UI;

use App\Actions\OrgAction;
use App\Actions\SysAdmin\User\UI\Traits\HasPermissionsForm;
use App\Models\HumanResources\Employee;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditUser extends OrgAction
{
    use HasPermissionsForm;
    public function handle(User $user): User
    {
        return $user;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("sysadmin.view");
    }

    public function asController(User $user, ActionRequest $request): User
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($user);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inEmployee(Organisation $organisation, Employee $employee, User $user, ActionRequest $request): User
    {
        $this->initialisation($organisation, $request);

        return $this->handle($user);
    }

    public function htmlResponse(User $user, ActionRequest $request): Response
    {
        $permissionsData = $this->getPermissionsFormData($user);

        /** @var Employee $employee */
        $employee = $user->employees()->first();

        return Inertia::render("EditModel", [
            "title"       => __("Editing user").' '.$user->username,
            "breadcrumbs" => $this->getBreadcrumbs(
                $request->route()->getName(),
                $request->route()->originalParameters()
            ),
            "pageHead"    => [
                "title"   => $user->username,
                "icon"    => 'fal fa-user-circle',
                "model"   => __('User'),
                "actions" => [
                    [
                        "type"  => "button",
                        "style" => "exitEdit",
                        "route" => [
                            "name"       => preg_replace('/edit$/', "show", $request->route()->getName()),
                            "parameters" => array_values($request->route()->originalParameters()),
                        ],
                    ],
                ],
            ],


            "formData" => [
                "blueprint" => [
                    [
                        "label"   => __("Access"),
                        "title"   => __("access"),
                        "icon"    => "fal fa-door-closed",
                        "current" => true,
                        "fields"  => [
                            "status" => [
                                "type"        => "toggle",
                                "label"       => __("can login"),
                                "value"       => $user->status,
                            ],
                        ],
                    ],
                    [
                        "label"   => __("Credentials"),
                        "title"   => __("id"),
                        "icon"    => "fal fa-key",
                        "current" => false,
                        "fields"  => [
                            "username" => [
                                "type"        => "input",
                                "label"       => __("username"),
                                "placeholder" => "username",
                                "value"       => $user->username ?? '',
                            ],
                            "email"    => [
                                "type"        => "input",
                                "label"       => __("email"),
                                "placeholder" => __("example@mail.com"),
                                "value"       => $user->email ?? '',
                            ],
                            "password" => [
                                "type"        => "password",
                                "label"       => __("password"),
                                "placeholder" => "********",
                                "value"       => '',
                            ],
                        ],
                    ],
                    "permissions" => [
                        "label"   => __("Permissions"),
                        "title"   => __("Permissions"),
                        "icon"    => "fa-light fa-user-lock",
                        "current" => false,
                        "fields"  => [
                            "permissions" => $this->getPermissionsFieldDefinition($user, $permissionsData, $employee),
                        ],
                    ],


                ],
                "args"      => [
                    "updateRoute" => [
                        "name"       => "grp.models.user.update",
                        "parameters" => [$user->id],
                    ],
                ],
            ],
        ]);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return ShowUser::make()->getBreadcrumbs(
            routeName: preg_replace('/edit$/', "show", $routeName),
            routeParameters: $routeParameters,
            suffix: "(".__("editing").")"
        );
    }
}
