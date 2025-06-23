<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 29 Jul 2024 19:36:30 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group\UI;

use App\Actions\GrpAction;
use App\Actions\SysAdmin\UI\ShowSysAdminDashboard;
use App\Actions\SysAdmin\WithSysAdminAuthorization;
use App\Models\SysAdmin\Group;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditGroupSettings extends GrpAction
{
    use WithSysAdminAuthorization;

    public function handle(Group $group): Group
    {

        return $group;
    }


    public function asController(ActionRequest $request): Group
    {
        $this->initialisation(group(), $request);
        return $this->handle($this->group);
    }


    public function htmlResponse(Group $group, ActionRequest $request): Response
    {

        return Inertia::render("EditModel", [
            "title"       => __("group"),
            "breadcrumbs" => $this->getBreadcrumbs(
                $request->route()->originalParameters()
            ),
            "pageHead" => [
                "title"   => $group->name,
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
                        "label"   => __("Group Information"),
                        "title"   => __("id"),
                        "icon"    => "fa-light fa-user",
                        "current" => true,
                        "fields"  => [
                            "name" => [
                                "type"        => "input",
                                "label"       => __("name"),
                                "value"       => $group->name ?? '',
                            ],
                            "logo" => [
                                "type"  => "avatar",
                                "label" => __("logo"),
                                "value" => $group->imageSources(320, 320)
                            ],
                        ],
                    ],
                    [
                        'label'  => __('Email Builder'),
                        'icon'   => 'fa-light fa-satellite-dish',
                        'fields' => [
                            "client_id" => [
                                "type"        => "input",
                                "label"       => __("Beefree Client ID"),
                                "value"       => $group->settings['beefree']['client_id'] ?? '',
                            ],
                            "client_secret" => [
                                "type"        => "input",
                                "label"       => __("Beefree Client Secret"),
                                "value"       => $group->settings['beefree']['client_secret'] ?? '',
                            ],
                            // "grant_type" => [
                            //     "type"        => "input",
                            //     "label"       => __("Grant Type"),
                            //     "value"       => $group->settings['beefree']['grant_type'] ?? '',
                            // ],
                        ],

                    ],
                    [
                        'label'  => __('Email Provider'),
                        'icon'   => 'fa-light fa-satellite-dish',
                        'fields' => [
                            "access_id" => [
                                "type"        => "input",
                                "label"       => __("Access ID"),
                                "value"       => $group->settings['email']['provider']['access_id'] ?? '',
                            ],
                            "access_key" => [
                                "type"        => "input",
                                "label"       => __("Access Key"),
                                "value"       => $group->settings['email']['provider']['access_key'] ?? '',
                            ],
                            "region" => [
                                "type"        => "input",
                                "label"       => __("Region"),
                                "value"       => $group->settings['email']['provider']['region'] ?? '',
                            ]
                        ]
                ],
                    [
                            'label'  => __('Account Treblle'),
                            'icon'   => 'fal fa-code',
                            'fields' => [
                                'treblle_api_key' => [
                                    'type'  => 'purePassword',
                                    'label' => __('API Key'),
                                    'value' => Arr::get($group->settings, 'treblle.api_key', ''),
                                ],
                                'treblle_project_id' => [
                                    'type'  => 'input',
                                    'label' => __('Project ID'),
                                    'value' => Arr::get($group->settings, 'treblle.project_id', ''),
                                ],
                            ],
                        ],
                ],
                "args" => [
                    "updateRoute" => [
                        "name"       => "grp.models.group-settings.update",
                    ],
                ],
            ],
        ]);
    }

    public function getBreadcrumbs($suffix = null): array
    {
        return array_merge(
            ShowSysAdminDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name' => 'grp.sysadmin.settings.edit',
                        ],
                        'label' => __('settings'),
                        'icon'  => 'fal fa-slide-h',
                    ],
                    'suffix' => $suffix

                ]
            ]
        );
    }
}
