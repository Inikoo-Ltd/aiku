<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Wed, 15 Mar 2023 08:39:50 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Production\Artefact\UI;

use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\OrgAction;
use App\Actions\Production\Production\UI\ShowCraftsDashboard;
use App\Actions\Traits\Actions\WithActionButtons;
use App\Enums\UI\Production\ArtefactTabsEnum;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\Production\ArtefactResource;
use App\Http\Resources\Production\ManufactureTasksResource;
use App\Models\Production\Artefact;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowArtefact extends OrgAction
{
    use WithActionButtons;

    public function handle(Artefact $artefact): Artefact
    {
        return $artefact;
    }

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->authTo('org-supervisor.'.$this->organisation->id);
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

    public function asController(Organisation $organisation, Production $production, Artefact $artefact, ActionRequest $request): Artefact
    {
        $this->initialisationFromProduction($production, $request)->withTab(ArtefactTabsEnum::values());

        return $this->handle($artefact);
    }

    public function htmlResponse(Artefact $artefact, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Production/Artefact',
            [
                'title' => __('warehouse area'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),
                'navigation' => [
                    'previous' => $this->getPrevious($artefact, $request),
                    'next' => $this->getNext($artefact, $request),
                ],
                'pageHead' => [
                    'icon' => [
                        'icon' => ['fal', 'fa-map-signs'],
                        'title' => __('Warehouse area'),
                    ],
                    'model' => __('artefact'),
                    'title' => $artefact->name,
                    'actions' => [
                        // $this->canEdit ?
                        //     [
                        //         'type'    => 'button',
                        //         'style'   => 'create',
                        //         'tooltip' => __('New location'),
                        //         'label'   => __('New location'),
                        //         'route'   => [
                        //             'name'       => $request->route()->getName().'.locations.create',
                        //             'parameters' => $request->route()->originalParameters()
                        //         ]
                        //     ]
                        //     : null,
                        // $this->canDelete ? $this->getDeleteActionIcon($request) : null,
                        [
                            'type' => 'button',
                            'tooltip' => __('Edit'),
                            'icon' => 'fal fa-pencil',
                            'style' => 'secondary',
                            'route' => [
                                'name' => preg_replace('/(show|dashboard)$/', 'edit', $request->route()->getName()),
                                'parameters' => $request->route()->originalParameters(),

                            ],
                        ],
                    ],
                    // 'meta'    => [
                    //     [
                    //         'name'     => trans_choice('location|locations', $artefact->stats->number_locations),
                    //         'number'   => $artefact->stats->number_locations,
                    //         'route'     => [
                    //             'name'       => 'grp.org.warehouses.show.infrastructure.warehouse_areas.show.locations.index',
                    //             'parameters' => $request->route()->originalParameters()
                    //         ],
                    //         'leftIcon' => [
                    //             'icon'    => 'fal fa-inventory',
                    //             'tooltip' => __('locations')
                    //         ]
                    //     ]
                    // ]
                ],
                'tabs' => [
                    'current' => $this->tab,
                    'navigation' => ArtefactTabsEnum::navigation(),
                ],
                ArtefactTabsEnum::SHOWCASE->value => $this->tab == ArtefactTabsEnum::SHOWCASE->value ?
                    fn () => GetArtefactShowcase::run($artefact)
                    : Inertia::lazy(fn () => GetArtefactShowcase::run($artefact)),

                ArtefactTabsEnum::MANUFACTURE_TASKS->value => $this->tab == ArtefactTabsEnum::MANUFACTURE_TASKS->value
                    ? fn () => ManufactureTasksResource::collection(GetArtefactManufactureTasks::run($artefact, $request))
                    : Inertia::lazy(fn () => ManufactureTasksResource::collection(GetArtefactManufactureTasks::run($artefact, $request))),

                // ArtefactTabsEnum::LOCATIONS->value => $this->tab == ArtefactTabsEnum::LOCATIONS->value
                //     ?
                //     fn () => LocationResource::collection(
                //         IndexLocations::run(
                //             parent: $artefact,
                //             prefix: ArtefactTabsEnum::LOCATIONS->value
                //         )
                //     )
                //     : Inertia::lazy(fn () => LocationResource::collection(
                //         IndexLocations::run(
                //             parent: $artefact,
                //             prefix: ArtefactTabsEnum::LOCATIONS->value
                //         )
                //     )),

                ArtefactTabsEnum::HISTORY->value => $this->tab == ArtefactTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($artefact))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($artefact))),

            ]
            // )->table(
            //     IndexLocations::make()->tableStructure(
            //         parent: $artefact,
            //         prefix: ArtefactTabsEnum::LOCATIONS->value
            //     )
        )->table(IndexHistory::make()->tableStructure(prefix: ArtefactTabsEnum::HISTORY->value));
    }

    public function jsonResponse(Artefact $artefact): ArtefactResource
    {
        return new ArtefactResource($artefact);
    }

    public function getBreadcrumbs(array $routeParameters, $suffix = null): array
    {
        $artefact = Artefact::where('slug', $routeParameters['artefact'])->first();

        return array_merge(
            ShowCraftsDashboard::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type' => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => [
                                'name' => 'grp.org.productions.show.crafts.artefacts.index',
                                'parameters' => $routeParameters,
                            ],
                            'label' => __('Artefacts'),
                            'icon' => 'fal fa-bars',
                        ],
                        'model' => [
                            'route' => [
                                'name' => 'grp.org.productions.show.crafts.artefacts.show',
                                'parameters' => $routeParameters,
                            ],
                            'label' => $artefact?->code,
                            'icon' => 'fal fa-bars',
                        ],
                    ],
                    'suffix' => $suffix,

                ],
            ]
        );
    }

    public function getPrevious(Artefact $artefact, ActionRequest $request): ?array
    {
        $previous = Artefact::where('code', '<', $artefact->code)->where('organisation_id', $artefact->organisation_id)->orderBy('code', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(Artefact $artefact, ActionRequest $request): ?array
    {
        $next = Artefact::where('code', '>', $artefact->code)->where('organisation_id', $artefact->organisation_id)->orderBy('code')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?Artefact $artefact, string $routeName): ?array
    {
        if (! $artefact) {
            return null;
        }

        return match ($routeName) {
            'grp.org.productions.show.infrastructure.dashboard' => [
                'label' => $artefact->code,
                'route' => [
                    'name' => $routeName,
                    'parameters' => [
                        'organisation' => $this->organisation->slug,
                        'production' => $artefact->production->slug,
                    ],
                ],
            ],
            default => null,
        };
    }
}
