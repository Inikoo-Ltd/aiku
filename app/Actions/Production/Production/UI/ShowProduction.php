<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Tue, 14 Mar 2023 15:31:00 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Production\Production\UI;

use App\Actions\Dashboard\ShowOrganisationDashboard;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\OrgAction;
use App\Actions\Traits\Actions\WithActionButtons;
use App\Enums\UI\Production\ProductionTabsEnum;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\Production\ProductionResource;
use App\Http\Resources\Tag\TagResource;
use App\Models\Helpers\Tag;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowProduction extends OrgAction
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
            'Org/Production/Production',
            [
                'title'                            => __('Factory'),
                'breadcrumbs'                      => $this->getBreadcrumbs($request->route()->originalParameters()),
                'navigation'                       => [
                    'previous' => $this->getPrevious($production, $request),
                    'next'     => $this->getNext($production, $request),
                ],
                'pageHead'                         => [
                    'icon'    =>
                        [
                            'icon'  => ['fal', 'industry'],
                            'title' => __('Factory')
                        ],
                    'title'   => $production->name,
                    'actions' => [
                        $this->canEdit ?
                            [
                                'type'    => 'button',
                                'style'   => 'create',
                                'tooltip' => __('new job order'),
                                'label'   => __('job order'),
                                'route'   => [
                                    'name'       => 'grp.org.productions.show.job-orders.create',
                                    'parameters' => $request->route()->originalParameters()
                                ]
                            ]
                            : null,
                        $this->canEdit ? $this->getEditActionIcon($request) : null,

                    ],


                ],
                'tabs'                             => [

                    'current'    => $this->tab,
                    'navigation' => ProductionTabsEnum::navigation(),
                ],
                'tagsList'      => TagResource::collection(Tag::all()),

                ProductionTabsEnum::SHOWCASE->value => $this->tab == ProductionTabsEnum::SHOWCASE->value ?
                    fn () => GetProductionShowcase::run($production)
                    : Inertia::lazy(fn () => GetProductionShowcase::run($production)),





                ProductionTabsEnum::HISTORY->value => $this->tab == ProductionTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($production))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($production)))

            ]
        )->table(IndexHistory::make()->tableStructure(prefix: ProductionTabsEnum::HISTORY->value));
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
            'grp.org.productions.show.infrastructure.dashboard' => [
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
