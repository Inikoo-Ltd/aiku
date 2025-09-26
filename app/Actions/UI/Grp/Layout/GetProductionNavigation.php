<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 May 2024 18:59:34 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Grp\Layout;

use App\Models\Production\Production;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\Concerns\AsAction;

class GetProductionNavigation
{
    use AsAction;

    public function handle(Production $production, User $user): array
    {
        $navigation = [];

        if ($user->hasAnyPermission(
            [
                'org-supervisor.'.$production->organisation->id,
                'productions-view.'.$production->organisation->id,
                "productions_operations.$production->id.view",
                "productions_operations.$production->id.orchestrate",
                "productions_rd.$production->id.view",
                "productions_procurement.$production->id.view",
            ]
        )) {
            $navigation["crafts"] = [
                "root"  => "grp.org.productions.show.crafts.",
                "label" => __("Crafts"),
                "icon"  => ['fal', 'fa-flask-potion'],
                "route" => [
                    "name"       => "grp.org.productions.show.crafts.dashboard",
                    "parameters" => [$production->organisation->slug, $production->slug],
                ],

                'topMenu' => [
                    'subSections' => [
                        [
                            "tooltip" => __("Dashboard"),
                            "icon"    => ["fal", "fa-chart-network"],
                            "root"    => "grp.org.productions.show.crafts.dashboard",
                            "route"   => [
                                "name"       => "grp.org.productions.show.crafts.dashboard",
                                "parameters" => [$production->organisation->slug, $production->slug]
                            ],
                        ],

                        [
                            'label'   => __('raw materials'),
                            'tooltip' => __('artefacts raw materials'),
                            'icon'    => ['fal', 'fa-drone'],
                            'root'    => 'grp.org.productions.show.crafts.raw_materials.',
                            'route'   => [
                                'name'       => 'grp.org.productions.show.crafts.raw_materials.index',
                                'parameters' => [$production->organisation->slug, $production->slug]
                            ],
                        ],

                        [
                            'label'   => __('artefacts'),
                            'tooltip' => __('manufactured products'),
                            'icon'    => ['fal', 'fa-hamsa'],
                            'root'    => 'grp.org.productions.show.crafts.artefacts.',
                            'route'   => [
                                'name'       => 'grp.org.productions.show.crafts.artefacts.index',
                                'parameters' => [$production->organisation->slug, $production->slug]
                            ],
                        ],

                        [
                            'label'   => __('tasks'),
                            'tooltip' => __('manufacture tasks'),
                            'icon'    => ['fal', 'fa-code-merge'],
                            'root'    => 'grp.org.productions.show.crafts.manufacture_tasks.',
                            'route'   => [
                                'name'       => 'grp.org.productions.show.crafts.manufacture_tasks.index',
                                'parameters' => [$production->organisation->slug, $production->slug]
                            ],
                        ],


                    ]
                ]

            ];


            $navigation['operations'] = [
                'root'  => 'grp.org.productions.show.operations.',
                'label' => __('Operations'),
                'icon'  => ['fal', 'fa-fill-drip'],

                'route' => [
                    'name'       => 'grp.org.productions.show.operations.dashboard',
                    'parameters' => [$production->organisation->slug, $production->slug]
                ],

                'topMenu' => [
                    'subSections' => [
                        [
                            "tooltip" => __("Dashboard"),
                            "icon"    => ["fal", "fa-chart-network"],
                            "root"    => "grp.org.productions.show.operations.dashboard",
                            "route"   => [
                                "name"       => "grp.org.productions.show.operations.dashboard",
                                "parameters" => [$production->organisation->slug, $production->slug]
                            ],
                        ],

                        [
                            'label'   => __('job orders'),
                            'tooltip' => __('Job Orders'),
                            'icon'    => ['fal', 'fa-sort-shapes-down-alt'],
                            'root'    => 'grp.org.productions.show.operations.job-orders.',
                            'route'   => [
                                'name'       => 'grp.org.productions.show.operations.job-orders.index',
                                'parameters' => [$production->organisation->slug, $production->slug]
                            ],
                        ],

                        [
                            'label'   => __('artisans'),
                            'tooltip' => __('Production workers'),
                            'icon'    => ['fal', 'fa-hat-chef'],
                            'root'    => 'grp.org.productions.show.operations.artisans.',
                            'route'   => [
                                'name'       => 'grp.org.productions.show.operations.artisans.index',
                                'parameters' => [$production->organisation->slug, $production->slug]
                            ],
                        ],
                    ]
                ]

            ];
        }

        return $navigation;
    }
}
