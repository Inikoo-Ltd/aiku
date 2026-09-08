<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 10 May 2024 20:55:47 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Production\UI;

use App\Actions\Dashboard\ShowOrganisationDashboard;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\OrgAction;
use App\Actions\Traits\Actions\WithActionButtons;
use App\Enums\Production\Artefact\ArtefactStateEnum;
use App\Enums\UI\Production\ProductionTabsEnum;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\Production\ProductionResource;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowCraftsDashboard extends OrgAction
{
    use WithActionButtons;

    public function handle(Production $production): Production
    {
        return $production;
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

    public function asController(Organisation $organisation, Production $production, ActionRequest $request): Production
    {
        $this->initialisationFromProduction($production, $request)->withTab(ProductionTabsEnum::values());

        return $this->handle($production);
    }


    public function htmlResponse(Production $production, ActionRequest $request): Response
    {

        return Inertia::render(
            'Org/Production/CraftsDashboard',
            [
                'title'                            => __('Crafts'),
                'breadcrumbs'                      => $this->getBreadcrumbs($request->route()->originalParameters()),
                'navigation'                       => [
                    'previous' => $this->getPrevious($production, $request),
                    'next'     => $this->getNext($production, $request),
                ],
                'pageHead'                         => [
                    'icon'    =>
                        [
                            'icon'  => ['fal', 'fa-flask-potion'],
                            'title' => __('Craft')
                        ],
                        'iconRight' => [
                            'icon'  => ['fal', 'fa-chart-network'],
                            'title' => __('Craft')
                        ],
                    'title'   => __('Crafts'),
                    'actions' => [


                    ],


                ],
                'stats' => $this->getStats($production, $request->route()->originalParameters()),
                'statsBoxNegativeTitle' => __('Artefact problems'),
                'statsBoxNegative' => [
                    [
                        'label' => __('Without recipe'),
                        'icon'  => 'fal fa-exclamation-triangle',
                        'value' => $production->artefacts()->whereDoesntHave('manufactureTasks')->count(),
                        'route' => [
                            'name'       => 'grp.org.productions.show.crafts.artefacts.index',
                            'parameters' => $request->route()->originalParameters()
                        ],
                    ],
                    [
                        'label' => __('Compliance problems'),
                        'icon'  => 'fal fa-clipboard-check',
                        'value' => $production->artefacts()->whereHas('complianceItems', function ($query) {
                            $query->where('is_required', true)
                                ->where(function ($query) {
                                    $query->whereNull('reference')
                                        ->orWhere('reference', '')
                                        ->orWhere('valid_until', '<', now());
                                });
                        })->count(),
                        'route' => [
                            'name'       => 'grp.org.productions.show.crafts.artefacts.index',
                            'parameters' => $request->route()->originalParameters()
                        ],
                    ],
                ],
                'tabs'                             => [

                    'current'    => $this->tab,
                    'navigation' => ProductionTabsEnum::navigation(),
                ],

                ProductionTabsEnum::SHOWCASE->value => $this->tab == ProductionTabsEnum::SHOWCASE->value ?
                    fn () => GetProductionShowcase::run($production)
                    : Inertia::optional(fn () => GetProductionShowcase::run($production)),





                ProductionTabsEnum::HISTORY->value => $this->tab == ProductionTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($production, ProductionTabsEnum::HISTORY->value))
                    : Inertia::optional(fn () => HistoryResource::collection(IndexHistory::run($production, ProductionTabsEnum::HISTORY->value)))

            ]
        )->table(IndexHistory::make()->tableStructure(prefix: ProductionTabsEnum::HISTORY->value));
    }


    private function getStats(Production $production, array $routeParameters): array
    {
        $artefactCounts = ArtefactStateEnum::count($production);

        return [
            [
                'label' => __('Departments'),
                'icon'  => 'fal fa-folder-tree',
                'color' => '#a3e635',
                'value' => $production->artefactDepartments()->count(),
                'route' => [
                    'name'       => 'grp.org.productions.show.crafts.artefact_departments.index',
                    'parameters' => $routeParameters
                ],
                'metas' => [
                    [
                        'tooltip' => __('Artefacts without a department'),
                        'icon'    => ['icon' => 'fal fa-unlink', 'class' => 'text-amber-500'],
                        'count'   => $production->artefacts()->whereNull('artefact_department_id')->count(),
                    ],
                ],
            ],
            [
                'label' => __('Families'),
                'icon'  => 'fal fa-folder',
                'color' => '#c084fc',
                'value' => $production->artefactFamilies()->count(),
                'route' => [
                    'name'       => 'grp.org.productions.show.crafts.artefact_families.index',
                    'parameters' => $routeParameters
                ],
                'metas' => [
                    [
                        'tooltip' => __('Artefacts without a family'),
                        'icon'    => ['icon' => 'fal fa-unlink', 'class' => 'text-amber-500'],
                        'count'   => $production->artefacts()->whereNull('artefact_family_id')->count(),
                    ],
                ],
            ],
            [
                'label' => __('Artefacts'),
                'icon'  => 'fal fa-hamsa',
                'color' => '#818cf8',
                'value' => $production->stats->number_artefacts,
                'route' => [
                    'name'       => 'grp.org.productions.show.crafts.artefacts.index',
                    'parameters' => $routeParameters
                ],
                'metas' => [
                    [
                        'tooltip' => __('Active artefacts'),
                        'icon'    => ['icon' => 'fas fa-check-circle', 'class' => 'text-green-500'],
                        'count'   => $artefactCounts[ArtefactStateEnum::ACTIVE->value],
                    ],
                    [
                        'tooltip' => __('In process'),
                        'icon'    => ['icon' => 'fal fa-seedling', 'class' => 'text-green-500 animate-pulse'],
                        'count'   => $artefactCounts[ArtefactStateEnum::IN_PROCESS->value],
                    ],
                    [
                        'tooltip' => __('Dormant'),
                        'icon'    => ['icon' => 'fas fa-times-circle', 'class' => 'text-amber-500'],
                        'count'   => $artefactCounts[ArtefactStateEnum::DORMANT->value],
                    ],
                    [
                        'tooltip' => __('Discontinued'),
                        'icon'    => ['icon' => 'fas fa-times-circle', 'class' => 'text-red-500'],
                        'count'   => $artefactCounts[ArtefactStateEnum::DISCONTINUED->value],
                    ],
                ],
            ],
            [
                'label' => __('Raw materials'),
                'icon'  => 'fal fa-network-wired',
                'color' => '#2dd4bf',
                'value' => $production->stats->number_raw_materials,
                'route' => [
                    'name'       => 'grp.org.productions.show.crafts.raw_materials.index',
                    'parameters' => $routeParameters
                ],
            ],
        ];
    }

    public function jsonResponse(Production $production): ProductionResource
    {
        return new ProductionResource($production);
    }

    public function getBreadcrumbs(array $routeParameters, $suffix = null): array
    {
        $production = Production::where('slug', $routeParameters['production'])->first();

        return array_merge(
            (new ShowOrganisationDashboard())->getBreadcrumbs(Arr::only($routeParameters, 'organisation')),
            [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => [
                                'name'       => 'grp.org.productions.index',
                                'parameters' => $routeParameters['organisation']
                            ],
                            'label' => __('Factories'),
                            'icon'  => 'fal fa-bars'
                        ],
                        'model' => [
                            'route' => [
                                'name'       => 'grp.org.productions.index',
                                'parameters' => $routeParameters
                            ],
                            'label' => $production?->code,
                            'icon'  => 'fal fa-bars'
                        ],
                    ],
                    'suffix'         => $suffix,

                ],
            ]
        );
    }

    public function getPrevious(Production $production, ActionRequest $request): ?array
    {
        $previous = Production::where('code', '<', $production->code)->where('organisation_id', $production->organisation_id)->orderBy('code', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(Production $production, ActionRequest $request): ?array
    {
        $next = Production::where('code', '>', $production->code)->where('organisation_id', $production->organisation_id)->orderBy('code')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?Production $production, string $routeName): ?array
    {
        if (!$production) {
            return null;
        }

        return match ($routeName) {
            'grp.org.productions.show.crafts.dashboard' => [
                'label' => $production->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation'  => $this->organisation->slug,
                        'production'    => $production->slug
                    ]

                ]
            ]
        };
    }
}
