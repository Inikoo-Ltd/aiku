<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 02 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ArtefactDepartment\UI;

use App\Actions\OrgAction;
use App\Actions\Production\Artefact\UI\IndexArtefacts;
use App\Actions\Production\Production\UI\ShowCraftsDashboard;
use App\Http\Resources\Production\ArtefactDepartmentsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Production\ArtefactDepartment;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexArtefactDepartments extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->authTo("productions_rd.{$this->production->id}.edit");

        return $request->user()->authTo("productions_rd.{$this->production->id}.view");
    }

    public function asController(Organisation $organisation, Production $production, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($production);
    }

    public function inJson(Production $production, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($production);
    }

    public function handle(Production $production, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('artefact_departments.code', $value)
                    ->orWhereWith('artefact_departments.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(ArtefactDepartment::class)
            ->where('artefact_departments.production_id', $production->id)
            ->defaultSort('artefact_departments.code')
            ->select(['artefact_departments.id', 'artefact_departments.slug', 'artefact_departments.code', 'artefact_departments.name', 'artefact_departments.number_artefacts'])
            ->allowedSorts(['code', 'name', 'number_artefacts'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Production $production, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($production, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }
            $table
                ->withGlobalSearch()
                ->withEmptyState([
                    'title'       => __('No artefact departments yet'),
                    'description' => $this->canEdit ? __('Group artefacts by the kind of work they need, e.g. Soap or Bath Bombs.') : null,
                    'count'       => $production->artefactDepartments()->count(),
                    'action'      => $this->canEdit ? [
                        'type'    => 'button',
                        'style'   => 'create',
                        'tooltip' => __('New family'),
                        'label'   => __('family'),
                        'route'   => [
                            'name'       => 'grp.org.productions.show.crafts.artefact_departments.create',
                            'parameters' => [$production->organisation->slug, $production->slug]
                        ]
                    ] : null
                ])
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_artefacts', label: __('Artefacts'), canBeHidden: false, sortable: true, align: 'right')
                ->defaultSort('code');
        };
    }

    public function jsonResponse(LengthAwarePaginator $artefactDepartments): AnonymousResourceCollection
    {
        return ArtefactDepartmentsResource::collection($artefactDepartments);
    }

    public function htmlResponse(LengthAwarePaginator $artefactDepartments, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Production/ArtefactDepartments',
            [
                'breadcrumbs'   => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'         => __('Artefact departments'),
                'pageHead'      => [
                    'title'         => __('Artefact departments'),
                    'icon'          => ['icon' => ['fal', 'fa-folder-tree'], 'title' => __('Artefact departments')],
                    'subNavigation' => IndexArtefacts::make()->getArtefactsSubNavigation($this->production),
                    'actions'       => [
                        $this->canEdit ? [
                            'type'  => 'button',
                            'style' => 'create',
                            'label' => __('family'),
                            'route' => [
                                'name'       => 'grp.org.productions.show.crafts.artefact_departments.create',
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ] : null,
                    ]
                ],
                'data'          => ArtefactDepartmentsResource::collection($artefactDepartments),
            ]
        )->table($this->tableStructure($this->production));
    }

    public function getBreadcrumbs(array $routeParameters, $suffix = null): array
    {
        return array_merge(
            ShowCraftsDashboard::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.productions.show.crafts.artefact_departments.index',
                            'parameters' => $routeParameters
                        ],
                        'label' => __('Artefact departments'),
                        'icon'  => 'fal fa-bars',
                    ],
                    'suffix' => $suffix
                ]
            ]
        );
    }
}
