<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Wed, 15 Mar 2023 08:39:50 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\HumanResources\ClockingMachine\UI;

use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\HumanResources\Clocking\UI\IndexClockings;
use App\Actions\HumanResources\Workplace\UI\ShowWorkplace;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Actions\UI\HumanResources\ShowHumanResourcesDashboard;
use App\Enums\UI\HumanResources\ClockingMachineTabsEnum;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\HumanResources\ClockingMachineResource;
use App\Http\Resources\HumanResources\ClockingsResource;
use App\Models\HumanResources\ClockingMachine;
use App\Models\HumanResources\Workplace;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\Enums\HumanResources\ClockingMachine\ClockingMachineTypeEnum;

class ShowClockingMachine extends OrgAction
{
    use WithHumanResourcesAuthorisation;

    private function getAvailableTabs(ClockingMachine $clockingMachine): array
    {
        $tabs = [
            ClockingMachineTabsEnum::SHOWCASE->value,
            ClockingMachineTabsEnum::CLOCKINGS->value,
            ClockingMachineTabsEnum::HISTORY->value,
            ClockingMachineTabsEnum::DATA->value,
        ];


        if ($clockingMachine->type === ClockingMachineTypeEnum::QR_CODE->value) {
            array_splice($tabs, 1, 0, ClockingMachineTabsEnum::SCAN_QR_CODE->value);
        }

        return $tabs;
    }

    public function inOrganisation(Organisation $organisation, ClockingMachine $clockingMachine, ActionRequest $request): ClockingMachine
    {
        $this->initialisation($organisation, $request)->withTab($this->getAvailableTabs($clockingMachine));

        return $clockingMachine;
    }


    /** @noinspection PhpUnusedParameterInspection */
    public function inWorkplace(Organisation $organisation, Workplace $workplace, ClockingMachine $clockingMachine, ActionRequest $request): ClockingMachine
    {
        $this->initialisation($organisation, $request)->withTab($this->getAvailableTabs($clockingMachine));

        return $clockingMachine;
    }

    public function htmlResponse(ClockingMachine $clockingMachine, ActionRequest $request): Response
    {
        $availableTabs = $this->getAvailableTabs($clockingMachine);
        $navigationData = [];
        foreach ($availableTabs as $tabValue) {
            $enumCase = ClockingMachineTabsEnum::tryFrom($tabValue);
            if ($enumCase) {
                $blueprint = $enumCase->blueprint();
                $navigationData[$tabValue] = $blueprint;
            }
        }

        return Inertia::render(
            'Org/HumanResources/ClockingMachine',
            [
                'title'                                  => __('clocking machine'),
                'breadcrumbs'                            => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'                             => [
                    'previous' => $this->getPrevious($clockingMachine, $request),
                    'next'     => $this->getNext($clockingMachine, $request),
                ],
                'pageHead'                               => [
                    'icon'    =>
                        [
                            'icon'  => ['fal', 'fa-chess-clock'],
                            'title' => __('Clocking machines')
                        ],
                    'title'   => $clockingMachine->name,
                    'model'   => __('clocking machine'),
                    'actions' => [
                        $this->canEdit ? [
                            'type'  => 'button',
                            'style' => 'edit',
                            'route' => [
                                'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ] : false,
                        $this->canDelete ? [
                            'type'  => 'button',
                            'style' => 'delete',
                            'route' => [
                                'name'       => 'grp.org.hr.workplaces.show.clocking_machines.remove',
                                'parameters' => $request->route()->originalParameters()
                            ]

                        ] : false
                    ],
                    'meta'    => [
                        [
                            'name'     => trans_choice('clocking|clockings', 0/*$clockingMachine->stats->number_clockings*/),
                            'number'   => 0/*$clockingMachine->stats->number_clockings*/,
                            'route'    =>
                                match ($request->route()->getName()) {
                                    'grp.org.hr.workplaces.show.clocking_machines.show' => [
                                        'grp.org.hr.workplaces.show.clocking_machines.show.clockings.index',
                                        [$this->organisation->slug, $clockingMachine->workplace->slug, $clockingMachine->slug]
                                    ],
                                    default => [
                                        'grp.org.hr.clocking_machines.show.clockings.index',
                                        [
                                            $this->organisation->slug,
                                            $clockingMachine->slug,
                                        ]
                                    ]
                                }


                            ,
                            'leftIcon' => [
                                'icon'    => 'fal fa-clock',
                                'tooltip' => __('clockings')
                            ]
                        ]
                    ]

                ],
                'tabs'                                   => [
                    'current'    => $this->tab,
                    'navigation' => $navigationData
                ],

                ClockingMachineTabsEnum::SHOWCASE->value => $this->tab == ClockingMachineTabsEnum::SHOWCASE->value ?
                    fn () => GetClockingMachineShowcase::run($clockingMachine)
                    : Inertia::lazy(fn () => GetClockingMachineShowcase::run($clockingMachine)),

                ClockingMachineTabsEnum::SCAN_QR_CODE->value => $this->tab == ClockingMachineTabsEnum::SCAN_QR_CODE->value ?
                    fn () => [
                        'qr_code_url'  => $clockingMachine->qr_code_url,
                        'machine_name' => $clockingMachine->name
                    ]
                    : Inertia::lazy(fn () => ['status' => 'loaded_lazy']),

                ClockingMachineTabsEnum::CLOCKINGS->value => $this->tab == ClockingMachineTabsEnum::CLOCKINGS->value ?
                    fn () => ClockingsResource::collection(IndexClockings::run($clockingMachine, ClockingMachineTabsEnum::CLOCKINGS->value))
                    : Inertia::lazy(fn () => ClockingsResource::collection(IndexClockings::run($clockingMachine, ClockingMachineTabsEnum::CLOCKINGS->value))),

                ClockingMachineTabsEnum::HISTORY->value => $this->tab == ClockingMachineTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($clockingMachine))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($clockingMachine)))

            ]
        )->table(IndexClockings::make()->tableStructure($clockingMachine, prefix: ClockingMachineTabsEnum::CLOCKINGS->value))
            ->table(IndexHistory::make()->tableStructure('hst'));
    }


    public function jsonResponse(ClockingMachine $clockingMachine): ClockingMachineResource
    {
        return new ClockingMachineResource($clockingMachine);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, $suffix = null): array
    {
        $clockingMachine = ClockingMachine::where('slug', $routeParameters['clockingMachine'])->first();


        $headCrumb = function (ClockingMachine $clockingMachine, array $routeParameters, $suffix) {
            return [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Clocking machines')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $clockingMachine->slug,
                        ],
                    ],
                    'suffix'         => $suffix,

                ],
            ];
        };

        return match ($routeName) {
            'grp.org.hr.clocking_machines.show' =>
            array_merge(
                (new ShowHumanResourcesDashboard())->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $clockingMachine,
                    [
                        'index' => [
                            'name'       => 'grp.org.hr.clocking_machines.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.org.hr.clocking_machines.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                )
            ),
            'grp.org.hr.workplaces.show.clocking_machines.show' =>
            array_merge(
                (new ShowWorkplace())->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $clockingMachine,
                    [
                        'index' => [
                            'name'       => 'grp.org.hr.workplaces.show.clocking_machines.index',
                            'parameters' => $routeParameters
                        ],
                        'model' => [
                            'name'       => 'grp.org.hr.workplaces.show.clocking_machines.show',
                            'parameters' => $routeParameters


                        ]
                    ],
                    $suffix
                )
            ),
            default => []
        };
    }

    public function getPrevious(ClockingMachine $clockingMachine, ActionRequest $request): ?array
    {
        $previous = ClockingMachine::where('slug', '<', $clockingMachine->slug)->orderBy('slug', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(ClockingMachine $clockingMachine, ActionRequest $request): ?array
    {
        $next = ClockingMachine::where('slug', '>', $clockingMachine->slug)->orderBy('slug')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?ClockingMachine $clockingMachine, string $routeName): ?array
    {
        if (!$clockingMachine) {
            return null;
        }

        return match ($routeName) {
            'grp.org.hr.clocking_machines.show' => [
                'label' => $clockingMachine->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation'    => $this->organisation->slug,
                        'clockingMachine' => $clockingMachine->slug
                    ]
                ]
            ],
            'grp.org.hr.workplaces.show.clocking_machines.show' => [
                'label' => $clockingMachine->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation'    => $this->organisation->slug,
                        'workplace'       => $clockingMachine->workplace->slug,
                        'clockingMachine' => $clockingMachine->slug
                    ]
                ]
            ]
        };
    }
}
