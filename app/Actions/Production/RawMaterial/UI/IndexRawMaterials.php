<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Wed, 15 Mar 2023 08:39:46 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Production\RawMaterial\UI;

use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\Production\Production\UI\ShowCraftsDashboard;
use App\Enums\UI\Production\RawMaterialsTabsEnum;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\Production\RawMaterialsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Production\Production;
use App\Models\Production\RawMaterial;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexRawMaterials extends OrgAction
{
    protected Group|Production|Organisation $parent;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->parent instanceof Group) {
            return $request->user()->authTo('group-overview');
        }

        if ($this->parent instanceof Organisation) {
            $this->canEdit = $request->user()->authTo('org-supervisor.'.$this->organisation->id);

            return $request->user()->authTo(
                [
                    'productions-view.'.$this->organisation->id,
                    'org-supervisor.'.$this->organisation->id,
                ]
            );
        }

        $this->canEdit = $request->user()->authTo("productions_rd.{$this->production->id}.edit");

        return $request->user()->authTo("productions_rd.{$this->production->id}.view");
    }

    public function inGroup(ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = group();
        $this->initialisationFromGroup(group(), $request)->withTab(RawMaterialsTabsEnum::values());

        return $this->handle(parent: $this->parent, prefix: RawMaterialsTabsEnum::RAW_MATERIALS->value);
    }

    public function inOrganisation(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request)->withTab(RawMaterialsTabsEnum::values());

        return $this->handle($organisation);
    }

    public function asController(Organisation $organisation, Production $production, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $production;
        $this->initialisationFromProduction($production, $request)->withTab(RawMaterialsTabsEnum::values());

        return $this->handle(parent: $production, prefix: RawMaterialsTabsEnum::RAW_MATERIALS->value);
    }

    public function handle(Group|Production|Organisation $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('raw_materials.code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(RawMaterial::class)
            ->leftJoin('organisations', 'raw_materials.organisation_id', '=', 'organisations.id');

        if ($parent instanceof Organisation) {
            $queryBuilder->where('raw_materials.organisation_id', $parent->id);
        } elseif ($parent instanceof Group) {
            $queryBuilder->where('raw_materials.group_id', $parent->id);
        } else {
            $queryBuilder->where('raw_materials.production_id', $parent->id);
        }

        return $queryBuilder
            ->defaultSort('raw_materials.code')
            ->select(
                [
                    'raw_materials.code',
                    'raw_materials.id',
                    'productions.slug as production_slug',
                    'raw_materials.slug',
                    'organisations.name as organisation_name',
                    'organisations.slug as organisation_slug',
                ]
            )
            ->leftJoin('raw_material_stats', 'raw_material_stats.raw_material_id', 'raw_materials.id')
            ->leftJoin('productions', 'raw_materials.production_id', 'productions.id')
            ->allowedSorts(['code'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Group|Production|Organisation $parent, ?array $modelOperations = null, $prefix = null, bool $canEdit = false): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix, $canEdit) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }
            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                    match (class_basename($parent)) {
                        'Organisation' => [
                            'title' => __('No raw materials found'),
                            'count' => $parent->manufactureStats->number_raw_materials,
                            'action' => null,
                        ],
                        'Production' => [
                            'title' => __('No raw materials found'),
                            'description' => $this->canEdit ? __('Get started by creating your first raw material. ✨')
                                : null,
                            'count' => $parent->stats->number_raw_materials,
                            'action' => $canEdit ? [
                                'type' => 'button',
                                'style' => 'create',
                                'tooltip' => __('New raw material'),
                                'label' => __('raw material'),
                                'route' => [
                                    'name' => 'grp.org.productions.show.crafts.raw_materials.create',
                                    'parameters' => [
                                        $parent->organisation->slug,
                                        $parent->slug,
                                    ],
                                ],
                            ] : null,
                        ],
                        default => null
                    }
                )
                ->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true);
            if ($parent instanceof Group) {
                $table->column(key: 'organisation_name', label: __('organisation'), canBeHidden: false, sortable: true, searchable: true)
                    ->column(key: 'shop_name', label: __('shop'), canBeHidden: false, sortable: true, searchable: true);
            }
            $table->defaultSort('code');
        };
    }

    public function jsonResponse(LengthAwarePaginator $rawMaterials): AnonymousResourceCollection
    {
        return RawMaterialsResource::collection($rawMaterials);
    }

    public function htmlResponse(LengthAwarePaginator $rawMaterials, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Production/RawMaterials',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title' => __('Raw Materials'),
                'pageHead' => [
                    'title' => __('Raw Materials'),
                    'icon' => [
                        'icon' => ['fal', 'fa-drone'],
                        'title' => __('Raw Materials'),
                    ],
                    'actions' => [
                        $this->canEdit && $this->parent instanceof Production ? [
                            'type' => 'buttonGroup',
                            'key' => 'upload-add',
                            'button' => [
                                [
                                    'type' => 'button',
                                    'style' => 'primary',
                                    'icon' => ['fal', 'fa-upload'],
                                    'label' => 'upload',
                                    'route' => [
                                        'name' => 'grp.models.production.raw_materials.upload',
                                        'parameters' => [
                                            $this->parent->id,
                                        ],
                                    ],
                                ],
                                [

                                    'type' => 'button',
                                    'style' => 'create',
                                    'label' => __('Raw Material'),
                                    'route' => [
                                        'name' => 'grp.org.productions.show.crafts.raw_materials.create',
                                        'parameters' => $request->route()->originalParameters(),
                                    ],

                                ],
                            ],
                        ] : null,
                    ],
                ],
                'upload' => $this->parent instanceof Group ? null : [
                    'event' => 'action-progress',
                    'channel' => 'grp.personal.'.$this->organisation->id,
                ],
                'tabs' => [
                    'current' => $this->tab,
                    'navigation' => RawMaterialsTabsEnum::navigation(),
                ],

                RawMaterialsTabsEnum::RAW_MATERIALS->value => $this->tab == RawMaterialsTabsEnum::RAW_MATERIALS->value ?
                    fn () => RawMaterialsResource::collection($rawMaterials)
                    : Inertia::lazy(fn () => RawMaterialsResource::collection($rawMaterials)),

                RawMaterialsTabsEnum::RAW_MATERIALS_HISTORIES->value => $this->tab == RawMaterialsTabsEnum::RAW_MATERIALS_HISTORIES->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($rawMaterials))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($rawMaterials))),

            ]
        )->table(
            $this->tableStructure(
                parent: $this->parent,
                prefix: RawMaterialsTabsEnum::RAW_MATERIALS->value
            )
        )->table(IndexHistory::make()->tableStructure(prefix: RawMaterialsTabsEnum::RAW_MATERIALS_HISTORIES->value));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, $suffix = null): array
    {
        return match ($routeName) {
            'grp.overview.production.raw-materials.index' => array_merge(
                ShowGroupOverviewHub::make()->getBreadcrumbs(
                    $routeParameters
                ),
                [
                    [
                        'type' => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => $routeName,
                                'parameters' => $routeParameters,
                            ],
                            'label' => __('Raw materials'),
                            'icon' => 'fal fa-bars',
                        ],
                        'suffix' => $suffix,

                    ],
                ]
            ),
            default => array_merge(
                ShowCraftsDashboard::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type' => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'grp.org.productions.show.crafts.raw_materials.index',
                                'parameters' => $routeParameters,
                            ],
                            'label' => __('Raw materials'),
                            'icon' => 'fal fa-bars',
                        ],
                        'suffix' => $suffix,

                    ],
                ]
            )
        };
    }
}
