<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Tue, 14 Mar 2023 15:31:00 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Production\ManufactureTask\UI;

use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\Production\Production\UI\ShowCraftsDashboard;
use App\Actions\OrgAction;
use App\Actions\Traits\Actions\WithActionButtons;
use App\Enums\UI\Production\ManufactureTaskTabsEnum;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\Production\ArtefactsResource;
use App\Http\Resources\Production\ManufactureTasksResource;
use App\Models\Production\ManufactureTask;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowManufactureTask extends OrgAction
{
    use WithActionButtons;

    public function handle(ManufactureTask $manufactureTask): ManufactureTask
    {
        return $manufactureTask;
    }

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit   = $request->user()->authTo('org-supervisor.'.$this->organisation->id);
        $this->canDelete = $request->user()->authTo('org-supervisor.'.$this->organisation->id);


        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            'productions-view.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.view",
            "productions_operations.{$this->production->id}.orchestrate",
            "productions_rd.{$this->production->id}.view",
            "productions_procurement.{$this->production->id}.view",

        ]);
    }

    public function asController(Organisation $organisation, Production $production, ManufactureTask $manufactureTask, ActionRequest $request): ManufactureTask
    {
        $this->initialisationFromProduction($production, $request)->withTab(ManufactureTaskTabsEnum::values());

        return $this->handle($manufactureTask);
    }


    public function htmlResponse(ManufactureTask $manufactureTask, ActionRequest $request): Response
    {

        return Inertia::render(
            'Org/Production/ManufactureTask',
            [
                'title'                            => __('manufacture task'),
                'breadcrumbs'                      => $this->getBreadcrumbs($request->route()->originalParameters()),
                'navigation'                       => [
                    'previous' => $this->getPrevious($manufactureTask, $request),
                    'next'     => $this->getNext($manufactureTask, $request),
                ],
                'pageHead'                         => [
                    'icon'    =>
                        [
                            'icon'  => ['fal', 'industry'],
                            'title' => __('manufacture task')
                        ],
                    'title'   => $manufactureTask->name,
                    'actions' => [
                        [
                            'type'    => 'button',
                        'tooltip'     => __('Edit'),
                        'icon'        => 'fal fa-pencil',
                        'style'       => 'secondary',
                        'route'       => [
                            'name'       => preg_replace('/(show|dashboard)$/', 'edit', $request->route()->getName()),
                            'parameters' => $request->route()->originalParameters()

                            ]
                        ]

                    ],


                ],
                'tabs'                             => [

                    'current'    => $this->tab,
                    'navigation' => ManufactureTaskTabsEnum::navigation(),
                ],

                ManufactureTaskTabsEnum::SHOWCASE->value => $this->tab == ManufactureTaskTabsEnum::SHOWCASE->value ?
                    fn () => GetManufactureTaskShowcase::run($manufactureTask)
                    : Inertia::lazy(fn () => GetManufactureTaskShowcase::run($manufactureTask)),

                ManufactureTaskTabsEnum::ARTEFACT->value => $this->tab == ManufactureTaskTabsEnum::ARTEFACT->value
                    ? fn () => ArtefactsResource::collection(GetManufactureTaskArtefacts::run($manufactureTask, $request))
                    : Inertia::lazy(fn () => ArtefactsResource::collection(GetManufactureTaskArtefacts::run($manufactureTask, $request))),

                ManufactureTaskTabsEnum::HISTORY->value => $this->tab == ManufactureTaskTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($manufactureTask))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($manufactureTask)))

            ]
        )->table(IndexHistory::make()->tableStructure(prefix: ManufactureTaskTabsEnum::HISTORY->value));
    }


    public function jsonResponse(ManufactureTask $manufactureTask): ManufactureTasksResource
    {
        return new ManufactureTasksResource($manufactureTask);
    }

    public function getBreadcrumbs(array $routeParameters, $suffix = null): array
    {
        $manufactureTask = ManufactureTask::where('slug', $routeParameters['manufactureTask'])->first();

        return array_merge(
            ShowCraftsDashboard::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => [
                                'name'       => 'grp.org.productions.show.crafts.manufacture_tasks.index',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('manufacture tasks'),
                            'icon'  => 'fal fa-bars',
                        ],
                        'model' => [
                            'route' => [
                                'name'       => 'grp.org.productions.show.crafts.manufacture_tasks.show',
                                'parameters' => $routeParameters
                            ],
                            'label' => $manufactureTask?->code,
                            'icon'  => 'fal fa-bars'
                        ],
                    ],
                    'suffix'         => $suffix,

                ],
            ]
        );
    }

    public function getPrevious(ManufactureTask $manufactureTask, ActionRequest $request): ?array
    {
        $previous = ManufactureTask::where('code', '<', $manufactureTask->code)->where('organisation_id', $manufactureTask->organisation_id)->orderBy('code', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(ManufactureTask $manufactureTask, ActionRequest $request): ?array
    {
        $next = ManufactureTask::where('code', '>', $manufactureTask->code)->where('organisation_id', $manufactureTask->organisation_id)->orderBy('code')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?ManufactureTask $manufactureTask, string $routeName): ?array
    {
        if (!$manufactureTask) {
            return null;
        }

        return match ($routeName) {
            'grp.org.productions.show.infrastructure.dashboard' => [
                'label' => $manufactureTask->code,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation'  => $this->organisation->slug,
                        'production'    => $manufactureTask->production->slug
                    ]
                ]
            ],
            default => null,
        };
    }
}
