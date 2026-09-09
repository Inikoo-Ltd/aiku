<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Wed, 15 Mar 2023 08:39:46 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Production\Artefact\UI;

use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\Production\Production\UI\ShowCraftsDashboard;
use App\Enums\UI\Production\ArtefactsTabsEnum;
use App\Enums\Production\Artefact\ArtefactStateEnum;
use App\Http\Resources\Production\ArtefactsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Production\Artefact;
use App\Models\Production\ArtefactDepartment;
use App\Models\Production\ArtefactFamily;
use App\Models\Production\Production;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexArtefacts extends OrgAction
{
    protected Group|Production|Organisation|ArtefactDepartment|ArtefactFamily $parent;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->parent instanceof Group) {
            return $request->user()->authTo("group-overview");
        }
        if ($this->parent instanceof Organisation) {
            $this->canEdit = $request->user()->authTo('org-supervisor.'.$this->organisation->id);

            return $request->user()->authTo(
                [
                    'productions-view.'.$this->organisation->id,
                    'org-supervisor.'.$this->organisation->id
                ]
            );
        }

        $this->canEdit = $request->user()->authTo(["org-supervisor.{$this->organisation->id}", "productions_rd.{$this->production->id}.edit"]);

        return $request->user()->authTo(["org-supervisor.{$this->organisation->id}", "productions_rd.{$this->production->id}.view"]);
    }

    public function inGroup(ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = group();
        $this->initialisationFromGroup(group(), $request)->withTab(ArtefactsTabsEnum::values());

        return $this->handle(parent: $this->parent, prefix: ArtefactsTabsEnum::ARTEFACTS->value);
    }

    public function inOrganisation(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request)->withTab(ArtefactsTabsEnum::values());

        return $this->handle($organisation);
    }

    public function asController(Organisation $organisation, Production $production, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $production;
        $this->initialisationFromProduction($production, $request)->withTab(ArtefactsTabsEnum::values());

        return $this->handle(parent: $production, prefix: ArtefactsTabsEnum::ARTEFACTS->value);
    }

    protected function getElementGroups(Group|Production|Organisation|ArtefactDepartment|ArtefactFamily $parent): array
    {
        $assignmentCounts = $this->getAssignmentCounts($parent);

        return [
            'state' => [
                'label'    => __('State'),
                'default'  => ArtefactStateEnum::IN_PROCESS->value.','.ArtefactStateEnum::ACTIVE->value,
                'elements' => array_merge_recursive(ArtefactStateEnum::labels(), ArtefactStateEnum::count($parent)),
                'engine'   => function ($query, $elements) {
                    $query->whereIn('artefacts.state', $elements);
                },
            ],
            'department' => [
                'label'    => __('Department'),
                'elements' => [
                    'assigned'   => [__('With department'), $assignmentCounts['department_assigned']],
                    'unassigned' => [__('Without department'), $assignmentCounts['department_unassigned']],
                ],
                'engine'   => function ($query, $elements) {
                    $this->applyAssignmentFilter($query, 'artefacts.artefact_department_id', $elements);
                },
            ],
            'batch_size' => [
                'label'    => __('Batch size'),
                'elements' => [
                    'assigned'   => [__('With batch size'), $assignmentCounts['batch_size_assigned']],
                    'unassigned' => [__('Without batch size'), $assignmentCounts['batch_size_unassigned']],
                ],
                'engine'   => function ($query, $elements) {
                    $this->applyAssignmentFilter($query, 'artefacts.recommended_batch_size', $elements);
                },
            ],
            'family' => [
                'label'    => __('Family'),
                'elements' => [
                    'assigned'   => [__('With family'), $assignmentCounts['family_assigned']],
                    'unassigned' => [__('Without family'), $assignmentCounts['family_unassigned']],
                ],
                'engine'   => function ($query, $elements) {
                    $this->applyAssignmentFilter($query, 'artefacts.artefact_family_id', $elements);
                },
            ],
        ];
    }

    /**
     * @return array{department_assigned: int, department_unassigned: int, family_assigned: int, family_unassigned: int, batch_size_assigned: int, batch_size_unassigned: int}
     */
    private function getAssignmentCounts(Group|Production|Organisation|ArtefactDepartment|ArtefactFamily $parent): array
    {
        $column = match (true) {
            $parent instanceof Group              => 'group_id',
            $parent instanceof Organisation       => 'organisation_id',
            $parent instanceof ArtefactDepartment => 'artefact_department_id',
            $parent instanceof ArtefactFamily     => 'artefact_family_id',
            default                               => 'production_id',
        };

        $counts = Artefact::where($column, $parent->id)
            ->selectRaw('count(artefact_department_id) as department_assigned')
            ->selectRaw('count(*) - count(artefact_department_id) as department_unassigned')
            ->selectRaw('count(artefact_family_id) as family_assigned')
            ->selectRaw('count(*) - count(artefact_family_id) as family_unassigned')
            ->selectRaw('count(recommended_batch_size) as batch_size_assigned')
            ->selectRaw('count(*) - count(recommended_batch_size) as batch_size_unassigned')
            ->first();

        return [
            'department_assigned'   => (int) $counts->department_assigned,
            'department_unassigned' => (int) $counts->department_unassigned,
            'family_assigned'       => (int) $counts->family_assigned,
            'family_unassigned'     => (int) $counts->family_unassigned,
            'batch_size_assigned'   => (int) $counts->batch_size_assigned,
            'batch_size_unassigned' => (int) $counts->batch_size_unassigned,
        ];
    }

    private function applyAssignmentFilter($query, string $column, array $elements): void
    {
        if (in_array('unassigned', $elements)) {
            $query->whereNull($column);

            return;
        }

        $query->whereNotNull($column);
    }

    public function handle(Group|Production|Organisation|ArtefactDepartment|ArtefactFamily $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereWith('artefacts.code', $value)
                    ->orWhereWith('artefacts.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $tagFilter = AllowedFilter::callback('tag', function ($query, $value) {
            $query->whereHas('tags', fn ($query) => $query->where('tags.name', $value));
        });

        $queryBuilder = QueryBuilder::for(Artefact::class)
                        ->with('tags')
                        ->leftJoin('organisations', 'artefacts.organisation_id', '=', 'organisations.id');
        if ($parent instanceof Group) {
            $queryBuilder->where('artefacts.group_id', $parent->id);
        } elseif ($parent instanceof Organisation) {
            $queryBuilder->where('artefacts.organisation_id', $parent->id);
        } elseif ($parent instanceof ArtefactDepartment) {
            $queryBuilder->where('artefacts.artefact_department_id', $parent->id);
        } elseif ($parent instanceof ArtefactFamily) {
            $queryBuilder->where('artefacts.artefact_family_id', $parent->id);
        } else {
            $queryBuilder->where('artefacts.production_id', $parent->id);
        }

        foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix,
                default: $elementGroup['default'] ?? null,
            );
        }

        return $queryBuilder
            ->defaultSort('artefacts.code')
            ->select(
                [
                    'artefacts.code',
                    'artefacts.id',
                    'artefacts.name',
                    'artefacts.state',
                    'artefacts.recommended_batch_size',
                    'artefact_departments.name as artefact_department_name',
                    'artefact_departments.slug as artefact_department_slug',
                    'artefact_families.name as artefact_family_name',
                    'artefact_families.slug as artefact_family_slug',
                    'productions.slug as production_slug',
                    'artefacts.slug',
                    'organisations.name as organisation_name',
                    'organisations.slug as organisation_slug',
                ]
            )
            ->leftJoin('artefact_stats', 'artefact_stats.artefact_id', 'artefacts.id')
            ->leftJoin('artefact_departments', 'artefacts.artefact_department_id', 'artefact_departments.id')
            ->leftJoin('artefact_families', 'artefacts.artefact_family_id', 'artefact_families.id')
            ->leftJoin('productions', 'artefacts.production_id', 'productions.id')
            ->allowedSorts(['code', 'name', 'artefact_department_name', 'artefact_family_name', 'recommended_batch_size'])
            ->allowedFilters([$globalSearch, $tagFilter])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function getMoveToDepartmentProps(Production $production, bool $canEdit): ?array
    {
        if (!$canEdit) {
            return null;
        }

        $parameters = [$production->organisation->slug, $production->slug];

        return [
            'families_route' => ['name' => 'grp.json.production.artefact_departments.index', 'parameters' => ['production' => $production->id]],
            'move_route'  => ['name' => 'grp.models.production.artefacts.move_to_department', 'parameters' => [$production->id]],
            'create_route' => ['name' => 'grp.org.productions.show.crafts.artefact_departments.create', 'parameters' => $parameters],
        ];
    }

    public function getMoveToFamilyProps(Production $production, bool $canEdit): ?array
    {
        if (!$canEdit) {
            return null;
        }

        $parameters = [$production->organisation->slug, $production->slug];

        return [
            'families_route' => ['name' => 'grp.json.production.artefact_families.index', 'parameters' => ['production' => $production->id]],
            'move_route'     => ['name' => 'grp.models.production.artefacts.move_to_family', 'parameters' => [$production->id]],
            'create_route'   => ['name' => 'grp.org.productions.show.crafts.artefact_families.create', 'parameters' => $parameters],
        ];
    }

    public function getArtefactsSubNavigation(Production $production): array
    {
        $parameters = [$production->organisation->slug, $production->slug];

        return [
            [
                'label'    => __('Departments'),
                'leftIcon' => ['icon' => 'fal fa-folder-tree', 'tooltip' => __('Artefact departments')],
                'root'     => 'grp.org.productions.show.crafts.artefact_departments.',
                'route'    => ['name' => 'grp.org.productions.show.crafts.artefact_departments.index', 'parameters' => $parameters],
                'number'   => $production->artefactDepartments()->count(),
            ],
            [
                'label'    => __('Families'),
                'leftIcon' => ['icon' => 'fal fa-folder', 'tooltip' => __('Artefact families')],
                'root'     => 'grp.org.productions.show.crafts.artefact_families.',
                'route'    => ['name' => 'grp.org.productions.show.crafts.artefact_families.index', 'parameters' => $parameters],
                'number'   => $production->artefactFamilies()->count(),
            ],
            [
                'label'    => __('All artefacts'),
                'leftIcon' => ['icon' => 'fal fa-bars', 'tooltip' => __('All artefacts')],
                'root'     => 'grp.org.productions.show.crafts.artefacts.',
                'route'    => ['name' => 'grp.org.productions.show.crafts.artefacts.index', 'parameters' => $parameters],
                'number'   => $production->stats->number_artefacts,
                'align'    => 'right',
            ],
        ];
    }

    public function tableStructure(Group|Production|Organisation|ArtefactDepartment|ArtefactFamily $parent, ?array $modelOperations = null, $prefix = null, bool $canEdit = false): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix, $canEdit) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }
            foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements'],
                    default: $elementGroup['default'] ?? null,
                );
            }
            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('artefact'), __('artefacts')])
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                    match (class_basename($parent)) {
                        'Organisation' => [
                            'title'  => __("No artefacts found"),
                            'count'  => $parent->manufactureStats->number_artefacts,
                            'action' => null
                        ],
                        'Production' => [
                            'title'       => __("No artefacts found"),
                            'description' => $this->canEdit ? __('Get started by creating your first artefact. ✨')
                                : null,
                            'count'       => $parent->stats->number_artefacts,
                            'action'      => $canEdit ? [
                                'type'    => 'button',
                                'style'   => 'create',
                                'tooltip' => __('New artefact'),
                                'label'   => __('artefact'),
                                'route'   => [
                                    'name'       => 'grp.org.productions.show.crafts.artefacts.create',
                                    'parameters' => [
                                        $parent->organisation->slug,
                                        $parent->slug
                                    ]
                                ]
                            ] : null
                        ],
                        default => null
                    }
                )
                ->column(key: 'state', label: '', canBeHidden: false, type: 'icon')
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'artefact_department_name', label: __('Department'), canBeHidden: false, sortable: true)
                ->column(key: 'artefact_family_name', label: __('Family'), canBeHidden: false, sortable: true)
                ->column(key: 'recommended_batch_size', label: __('Batch'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'tags', label: __('Tags'), canBeHidden: false);
            if ($parent instanceof Group) {
                $table->column(key: 'organisation_name', label: __('organisation'), canBeHidden: false, sortable: true, searchable: true);
            }
            $table->defaultSort('code');
        };
    }

    public function jsonResponse(LengthAwarePaginator $artefacts): AnonymousResourceCollection
    {
        return ArtefactsResource::collection($artefacts);
    }

    public function htmlResponse(LengthAwarePaginator $artefacts, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Production/Artefacts',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Artefacts'),
                'pageHead'    => [
                    'model'     => $this->parent instanceof Production ? __('Crafts') : null,
                    'title'     => __('Artefacts'),
                    'icon'      => [
                        'icon'  => ['fal', 'fa-hamsa'],
                        'title' => __('Artefacts'),
                    ],
                    'subNavigation' => $this->parent instanceof Production ? $this->getArtefactsSubNavigation($this->parent) : null,
                    'actions'   => [
                        $this->canEdit && $this->parent instanceof Production ? [
                            'type'   => 'buttonGroup',
                            'key'    => 'upload-add',
                            'button' => [
                                [
                                    'type'  => 'button',
                                    'style' => 'secondary',
                                    'icon'  => ['fal', 'fa-upload'],
                                    'label' => __('Upload'),
                                    'route' => [
                                        'name'       => 'grp.models.production.artefacts.upload',
                                        'parameters' => [
                                            $this->parent->id
                                        ]
                                    ]
                                ],
                                [

                                    'type'  => 'button',
                                    'style' => 'create',
                                    'label' => __('Artefact'),
                                    'route' => [
                                        'name'       => 'grp.org.productions.show.crafts.artefacts.create',
                                        'parameters' => $request->route()->originalParameters()
                                    ]

                                ]
                            ]
                        ] : null,
                    ]
                ],
                'upload_artefacts' => $this->parent instanceof Production ? [
                    'title' => [
                        'label'       => __('Upload Artefacts'),
                        'information' => __('The list of column file: code, name, state'),
                    ],
                    'progressDescription' => __('Importing artefacts'),
                    'preview_template'    => [
                        'header' => ['code', 'name', 'state'],
                        'rows'   => [
                            [
                                'code'  => 'ART-001',
                                'name'  => 'Lavender pillow mist',
                                'state' => ArtefactStateEnum::IN_PROCESS->value,
                            ],
                        ],
                    ],
                    'upload_spreadsheet' => [
                        'event'           => 'action-progress',
                        'channel'         => 'grp.personal.'.$request->user()->id,
                        'required_fields' => ['code', 'name', 'state'],
                        'template'        => [
                            'label' => __('Download template (.xlsx)'),
                        ],
                        'route' => [
                            'upload' => [
                                'name'       => 'grp.models.production.artefacts.upload',
                                'parameters' => [$this->parent->id],
                            ],
                        ],
                    ],
                ] : null,
                'move_to_department' => $this->parent instanceof Production ? $this->getMoveToDepartmentProps($this->parent, $this->canEdit) : null,
                'move_to_family'     => $this->parent instanceof Production ? $this->getMoveToFamilyProps($this->parent, $this->canEdit) : null,
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => $this->parent instanceof Group ? Arr::except(ArtefactsTabsEnum::navigation(), [ArtefactsTabsEnum::ARTEFACTS_HISTORIES->value]) : ArtefactsTabsEnum::navigation(),
                ],

                ArtefactsTabsEnum::ARTEFACTS->value => $this->tab == ArtefactsTabsEnum::ARTEFACTS->value ?
                    fn () => ArtefactsResource::collection($artefacts)
                    : Inertia::optional(fn () => ArtefactsResource::collection($artefacts)),

            ]
        )->table(
            $this->tableStructure(
                parent: $this->parent,
                prefix: ArtefactsTabsEnum::ARTEFACTS->value
            )
        );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, $suffix = null): array
    {

        return match ($routeName) {
            'grp.overview.production.artefacts.index' =>
            array_merge(
                ShowGroupOverviewHub::make()->getBreadcrumbs(
                    $routeParameters
                ),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => $routeName,
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Artefacts'),
                            'icon'  => 'fal fa-bars',
                        ],
                        'suffix' => $suffix

                    ]
                ]
            ),
            default => array_merge(
                ShowCraftsDashboard::make()->getBreadcrumbs($routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.productions.show.crafts.artefacts.index',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Artefacts'),
                            'icon'  => 'fal fa-bars',
                        ],
                        'suffix' => $suffix

                    ]
                ]
            )
        };
    }

}
