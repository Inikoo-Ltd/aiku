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
            ]
        )) {
            $navigation['jobs'] = [
                'root'  => 'grp.org.productions.show.floor',
                'label' => __('Jobs'),
                'icon'  => ['fal', 'fa-tasks'],
                'route' => [
                    'name'       => 'grp.org.productions.show.floor',
                    'parameters' => [$production->organisation->slug, $production->slug]
                ],
            ];
        }

        if ($user->hasAnyPermission(
            [
                'org-supervisor.'.$production->organisation->id,
                'productions-view.'.$production->organisation->id,
                "productions_operations.$production->id.edit",
                "productions_operations.$production->id.orchestrate",
                "productions_operations.$production->id.prepare",
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
                            'label'   => __('Raw materials'),
                            'tooltip' => __('artefacts raw materials'),
                            'icon'    => ['fal', 'fa-drone'],
                            'root'    => 'grp.org.productions.show.crafts.raw_materials.',
                            'route'   => [
                                'name'       => 'grp.org.productions.show.crafts.raw_materials.index',
                                'parameters' => [$production->organisation->slug, $production->slug]
                            ],
                        ],

                        [
                            'label'   => __('Artefacts'),
                            'tooltip' => __('manufactured products'),
                            'icon'    => ['fal', 'fa-hamsa'],
                            'root'    => 'grp.org.productions.show.crafts.artefacts.',
                            'route'   => [
                                'name'       => 'grp.org.productions.show.crafts.artefacts.index',
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
                            'label'   => __('Job orders'),
                            'tooltip' => __('Job Orders'),
                            'icon'    => ['fal', 'fa-sort-shapes-down-alt'],
                            'root'    => 'grp.org.productions.show.operations.job-orders.',
                            'route'   => [
                                'name'       => 'grp.org.productions.show.operations.job-orders.index',
                                'parameters' => [$production->organisation->slug, $production->slug]
                            ],
                        ],
                        [
                            'label'   => __('Tasks'),
                            'tooltip' => __('manufacture tasks'),
                            'icon'    => ['fal', 'fa-code-merge'],
                            'root'    => 'grp.org.productions.show.operations.manufacture_tasks.',
                            'route'   => [
                                'name'       => 'grp.org.productions.show.operations.manufacture_tasks.index',
                                'parameters' => [$production->organisation->slug, $production->slug]
                            ],
                        ],
                    ]
                ]

            ];

            $navigation['partners'] = [
                'root'  => 'grp.org.productions.show.to_produce.',
                'label' => __('To produce'),
                'icon'  => ['fal', 'fa-truck-loading'],

                'route' => [
                    'name'       => 'grp.org.productions.show.to_produce.index',
                    'parameters' => [$production->organisation->slug, $production->slug]
                ],
            ];


            $navigation['pre_pick'] = [
                'root'  => 'grp.org.productions.show.pre_pick.',
                'label' => __('Pre-pick'),
                'icon'  => ['fal', 'fa-hand-holding-box'],

                'route' => [
                    'name'       => 'grp.org.productions.show.pre_pick.index',
                    'parameters' => [$production->organisation->slug, $production->slug]
                ],
            ];


            $navigation['artisans'] = [
                'root'  => 'grp.org.productions.show.artisans.',
                'label' => __('Artisans'),
                'icon'  => ['fal', 'fa-hat-chef'],

                'route' => [
                    'name'       => 'grp.org.productions.show.artisans.dashboard',
                    'parameters' => [$production->organisation->slug, $production->slug]
                ],

                'topMenu' => [
                    'subSections' => [
                        [
                            "tooltip" => __("Dashboard"),
                            "icon"    => ["fal", "fa-chart-network"],
                            "root"    => "grp.org.productions.show.artisans.dashboard",
                            "route"   => [
                                "name"       => "grp.org.productions.show.artisans.dashboard",
                                "parameters" => [$production->organisation->slug, $production->slug]
                            ],
                        ],
                        [
                            'label'   => __('Performance'),
                            'tooltip' => __('Sessions, output and earnings per artisan'),
                            'icon'    => ['fal', 'fa-user-hard-hat'],
                            'root'    => 'grp.org.productions.show.artisans.index',
                            'route'   => [
                                'name'       => 'grp.org.productions.show.artisans.index',
                                'parameters' => [$production->organisation->slug, $production->slug]
                            ],
                        ],
                        ...($user->hasAnyPermission(['org-supervisor.'.$production->organisation->id, "human-resources.{$production->organisation->id}.view"]) ? [[
                            'label'   => __('Payroll'),
                            'tooltip' => __('Piece-rate payroll export'),
                            'icon'    => ['fal', 'fa-money-check-alt'],
                            'root'    => 'grp.org.productions.show.artisans.payroll',
                            'route'   => [
                                'name'       => 'grp.org.productions.show.artisans.payroll',
                                'parameters' => [$production->organisation->slug, $production->slug]
                            ],
                        ]] : []),
                    ]
                ]
            ];
        }

        return $navigation;
    }
}
