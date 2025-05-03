<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 21 Sep 2023 11:34:12 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\Workplace\UI;

use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Actions\UI\HumanResources\ShowHumanResourcesDashboard;
use App\Http\Resources\HumanResources\WorkplaceInertiaResource;
use App\Http\Resources\HumanResources\WorkplaceResource;
use App\InertiaTable\InertiaTable;
use App\Models\HumanResources\Workplace;
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

class IndexWorkplaces extends OrgAction
{
    use WithHumanResourcesAuthorisation;


    private array $originalParameters;
    private Group|Organisation $parent;

    public function handle(Group|Organisation $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('workplaces.name', $value)
                    ->orWhereStartWith('workplaces.slug', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Workplace::class);

        if ($parent instanceof Organisation) {
            $queryBuilder->where('workplaces.organisation_id', $parent->id);
        } else { //Group
            $queryBuilder->where('workplaces.group_id', $parent->id);
        }

        $queryBuilder->leftjoin('organisations', 'workplaces.organisation_id', '=', 'organisations.id');

        return $queryBuilder
            ->defaultSort('slug')
            ->select([
                'workplaces.slug',
                'workplaces.id',
                'workplaces.name',
                'workplaces.type',
                'workplaces.created_at',
                'workplaces.updated_at',
                'workplaces.timezone_id',
                'workplaces.address_id',
                'organisations.name as organisation_name',
                'organisations.slug as organisation_slug',
            ])
            ->with('address')
            ->with('stats')
            ->allowedSorts(['slug', 'name'])
            ->allowedFilters([$globalSearch, 'slug', 'name', 'type'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure($parent, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title'       => __('no working places'),
                        'description' => $this->canEdit ? __('Get started by creating a new working place.') : null,
                        'count'       => 0,
                        'action'      => $this->canEdit ? [
                            'type'    => 'button',
                            'style'   => 'create',
                            'tooltip' => __('new working place'),
                            'label'   => __('working place'),
                            'route'   => [
                                'name'       => 'grp.org.hr.workplaces.create',
                                'parameters' => array_values($this->originalParameters)
                            ]
                        ] : null
                    ]
                )
                ->column(key: 'slug', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'type', label: __('type'), canBeHidden: false, sortable: true, searchable: true);
            if ($parent instanceof Group) {
                $table->column(key: 'organisation_name', label: __('organisation'), canBeHidden: false, searchable: true);
            }
            $table->defaultSort('slug');
        };
    }


    public function jsonResponse(LengthAwarePaginator $workplace): AnonymousResourceCollection
    {
        return WorkplaceResource::collection($workplace);
    }


    public function htmlResponse(LengthAwarePaginator $workplace, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/HumanResources/Workplaces',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'title'       => __('working places'),
                'pageHead'    => [
                    'icon'    => ['fal', 'building'],
                    'title'   => __('Working places'),
                    'actions' => [
                        $this->canEdit ? [
                            'type'  => 'button',
                            'style' => 'create',
                            'label' => __('Working place'),
                            'route' => [
                                'name'       => 'grp.org.hr.workplaces.create',
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ] : false
                    ]
                ],

                'data' => WorkplaceInertiaResource::collection($workplace),
            ]
        )->table($this->tableStructure($this->parent));
    }


    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request);
        $this->originalParameters = $request->route()->originalParameters();

        return $this->handle($organisation);
    }

    public function inGroup(ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = group();
        $this->initialisationFromGroup(group(), $request);

        return $this->handle(group());
    }


    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function ($routeName, $routeParameters) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => $routeName,
                            'parameters' => $routeParameters
                        ],
                        'label' => __('Working places'),
                        'icon'  => 'fal fa-bars',
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.hr.workplaces.index' =>
            array_merge(
                ShowHumanResourcesDashboard::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $routeName,
                    $routeParameters
                )
            ),
            'grp.overview.hr.workplaces.index' =>
            array_merge(
                ShowGroupOverviewHub::make()->getBreadcrumbs(),
                $headCrumb(
                    $routeName,
                    $routeParameters
                )
            ),
        };
    }
}
