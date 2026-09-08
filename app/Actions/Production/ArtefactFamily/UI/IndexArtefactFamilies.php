<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 08 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ArtefactFamily\UI;

use App\Actions\OrgAction;
use App\Actions\Production\Artefact\UI\IndexArtefacts;
use App\Actions\Production\Production\UI\ShowCraftsDashboard;
use App\Http\Resources\Production\ArtefactFamiliesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Production\ArtefactDepartment;
use App\Models\Production\ArtefactFamily;
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

class IndexArtefactFamilies extends OrgAction
{
    protected Production|ArtefactDepartment $parent;

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->authTo("productions_rd.{$this->production->id}.edit");

        return $request->user()->authTo("productions_rd.{$this->production->id}.view");
    }

    public function asController(Organisation $organisation, Production $production, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $production;
        $this->initialisationFromProduction($production, $request);

        return $this->handle($production);
    }

    public function inJson(Production $production, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $production;
        $this->initialisationFromProduction($production, $request);

        return $this->handle($production);
    }

    public function inArtefactDepartment(Production $production, ArtefactDepartment $artefactDepartment, $prefix = null): LengthAwarePaginator
    {
        $this->parent = $artefactDepartment;

        return $this->handle($artefactDepartment, $prefix);
    }

    public function handle(Production|ArtefactDepartment $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('artefact_families.code', $value)
                    ->orWhereWith('artefact_families.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(ArtefactFamily::class);

        if ($parent instanceof ArtefactDepartment) {
            $queryBuilder->where('artefact_families.artefact_department_id', $parent->id);
        } else {
            $queryBuilder->where('artefact_families.production_id', $parent->id);
        }

        return $queryBuilder
            ->defaultSort('artefact_families.code')
            ->select([
                'artefact_families.id',
                'artefact_families.slug',
                'artefact_families.code',
                'artefact_families.name',
                'artefact_families.number_artefacts',
                'artefact_departments.name as artefact_department_name',
                'artefact_departments.slug as artefact_department_slug',
            ])
            ->leftJoin('artefact_departments', 'artefact_families.artefact_department_id', 'artefact_departments.id')
            ->allowedSorts(['code', 'name', 'number_artefacts', 'artefact_department_name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function getMoveToDepartmentProps(Production $production, bool $canEdit): ?array
    {
        if (!$canEdit) {
            return null;
        }

        return [
            'departments_route' => ['name' => 'grp.json.production.artefact_departments.index', 'parameters' => ['production' => $production->id]],
            'move_route'        => ['name' => 'grp.models.production.artefact_families.move_to_department', 'parameters' => [$production->id]],
            'create_route'      => [
                'name'       => 'grp.org.productions.show.crafts.artefact_departments.create',
                'parameters' => [$production->organisation->slug, $production->slug],
            ],
        ];
    }

    public function tableStructure(Production|ArtefactDepartment $parent, $prefix = null): Closure
    {
        $production = $parent instanceof Production ? $parent : $parent->production;

        return function (InertiaTable $table) use ($parent, $production, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }
            $table
                ->withGlobalSearch()
                ->withEmptyState([
                    'title'       => __('No artefact families yet'),
                    'description' => $this->canEdit ? __('Families group the artefacts of a department, the way stock families group org stocks.') : null,
                    'count'       => $parent->artefactFamilies()->count(),
                    'action'      => $this->canEdit ? [
                        'type'    => 'button',
                        'style'   => 'create',
                        'tooltip' => __('New family'),
                        'label'   => __('family'),
                        'route'   => [
                            'name'       => 'grp.org.productions.show.crafts.artefact_families.create',
                            'parameters' => [$production->organisation->slug, $production->slug],
                        ]
                    ] : null
                ])
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);

            if ($parent instanceof Production) {
                $table->column(key: 'artefact_department_name', label: __('Department'), canBeHidden: false, sortable: true);
            }

            $table
                ->column(key: 'number_artefacts', label: __('Artefacts'), canBeHidden: false, sortable: true, align: 'right')
                ->defaultSort('code');
        };
    }

    public function jsonResponse(LengthAwarePaginator $artefactFamilies): AnonymousResourceCollection
    {
        return ArtefactFamiliesResource::collection($artefactFamilies);
    }

    public function htmlResponse(LengthAwarePaginator $artefactFamilies, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Production/ArtefactFamilies',
            [
                'breadcrumbs'        => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'              => __('Artefact families'),
                'pageHead'           => [
                    'title'         => __('Artefact families'),
                    'icon'          => ['icon' => ['fal', 'fa-folder-tree'], 'title' => __('Artefact families')],
                    'subNavigation' => IndexArtefacts::make()->getArtefactsSubNavigation($this->production),
                    'actions'       => [
                        $this->canEdit ? [
                            'type'  => 'button',
                            'style' => 'create',
                            'label' => __('family'),
                            'route' => [
                                'name'       => 'grp.org.productions.show.crafts.artefact_families.create',
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ] : null,
                    ]
                ],
                'move_to_department' => $this->getMoveToDepartmentProps($this->production, $this->canEdit),
                'data'               => ArtefactFamiliesResource::collection($artefactFamilies),
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
                            'name'       => 'grp.org.productions.show.crafts.artefact_families.index',
                            'parameters' => $routeParameters
                        ],
                        'label' => __('Artefact families'),
                        'icon'  => 'fal fa-folder-tree',
                    ],
                    'suffix' => $suffix
                ]
            ]
        );
    }
}
