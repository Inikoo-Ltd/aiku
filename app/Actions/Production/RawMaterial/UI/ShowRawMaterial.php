<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Tue, 14 Mar 2023 15:31:00 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Production\RawMaterial\UI;

use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\Production\Production\UI\ShowCraftsDashboard;
use App\Actions\OrgAction;
use App\Actions\Traits\Actions\WithActionButtons;
use App\Enums\UI\Production\ProductionTabsEnum;
use App\Enums\UI\Production\RawMaterialTabsEnum;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\Production\RawMaterialsResource;
use App\Models\Production\Production;
use App\Models\Production\RawMaterial;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowRawMaterial extends OrgAction
{
    use WithActionButtons;

    public function handle(RawMaterial $rawMaterial): RawMaterial
    {
        return $rawMaterial;
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

    public function asController(Organisation $organisation, Production $production, RawMaterial $rawMaterial, ActionRequest $request): RawMaterial
    {
        $this->initialisationFromProduction($production, $request)->withTab(RawMaterialTabsEnum::values());

        return $this->handle($rawMaterial);
    }


    public function htmlResponse(RawMaterial $rawMaterial, ActionRequest $request): Response
    {
        $title = __('Raw Material');

        return Inertia::render(
            'Org/Production/RawMaterial',
            [
                'title'                            => $title,
                'breadcrumbs'                      => $this->getBreadcrumbs($request->route()->originalParameters()),
                'navigation'                       => [
                    'previous' => $this->getPrevious($rawMaterial, $request),
                    'next'     => $this->getNext($rawMaterial, $request),
                ],
                'pageHead'                         => [
                    'icon'    =>
                        [
                            'icon'  => ['fal', 'industry'],
                            'title' => $title
                        ],
                    'title'   => $rawMaterial->code,
                    'model'   => $title,
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
                    'navigation' => RawMaterialTabsEnum::navigation(),
                ],

                RawMaterialTabsEnum::SHOWCASE->value => $this->tab == RawMaterialTabsEnum::SHOWCASE->value ?
                    fn () => GetRawMaterialShowcase::run($rawMaterial)
                    : Inertia::lazy(fn () => GetRawMaterialShowcase::run($rawMaterial)),





               RawMaterialTabsEnum::HISTORY->value => $this->tab == RawMaterialTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($rawMaterial))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($rawMaterial)))

            ]
        )->table(IndexHistory::make()->tableStructure(prefix: ProductionTabsEnum::HISTORY->value));
    }


    public function jsonResponse(RawMaterial $rawMaterial): RawMaterialsResource
    {
        return new RawMaterialsResource($rawMaterial);
    }

    public function getBreadcrumbs(array $routeParameters, $suffix = null): array
    {
        $rawMaterial = RawMaterial::where('slug', $routeParameters['rawMaterial'])->first();

        return array_merge(
            ShowCraftsDashboard::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => [
                                'name'       => 'grp.org.productions.show.crafts.raw_materials.index',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('raw materials'),
                            'icon'  => 'fal fa-bars',
                        ],
                        'model' => [
                            'route' => [
                                'name'       => 'grp.org.productions.show.crafts.raw_materials.show',
                                'parameters' => $routeParameters
                            ],
                            'label' => $rawMaterial?->code,
                            'icon'  => 'fal fa-bars'
                        ],
                    ],
                    'suffix'         => $suffix,

                ],
            ]
        );
    }

    public function getPrevious(RawMaterial $rawMaterial, ActionRequest $request): ?array
    {
        $previous = RawMaterial::where('code', '<', $rawMaterial->code)->where('organisation_id', $rawMaterial->organisation_id)->orderBy('code', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(RawMaterial $rawMaterial, ActionRequest $request): ?array
    {
        $next = RawMaterial::where('code', '>', $rawMaterial->code)->where('organisation_id', $rawMaterial->organisation_id)->orderBy('code')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?RawMaterial $rawMaterial, string $routeName): ?array
    {
        if (!$rawMaterial) {
            return null;
        }

        return match ($routeName) {
            'grp.org.productions.show.infrastructure.dashboard' => [
                'label' => $rawMaterial->code,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation'  => $this->organisation->slug,
                        'production'    => $rawMaterial->production->slug
                    ]
                ]
            ],
            default => null,
        };
    }
}
