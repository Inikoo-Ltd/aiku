<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:23:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation\UI;

use App\Actions\OrgAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Http\Resources\SysAdmin\Organisation\OrganisationsResource;
use App\InertiaTable\InertiaTable;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexOrganisations extends OrgAction
{
    protected function getElementGroups(): array
    {
        return [
            'status' => [
                'label'    => __('Type'),
                'elements' => ['active' => __('Active'), 'suspended' => __('Suspended')],
                'engine'   => function ($query, $elements) {
                    $query->where('users.status', array_pop($elements) === 'active');
                }

            ]
        ];
    }


    public function handle(Group $group, $prefix = null): LengthAwarePaginator
    {
        $this->group  = $group;
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('name', $value)->orWhereStartWith('code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Organisation::class);
        $queryBuilder->where('group_id', $group->id);
        $queryBuilder->where('type', '!=', OrganisationTypeEnum::AGENT);
        $queryBuilder->leftJoin('organisation_human_resources_stats', 'organisations.id', '=', 'organisation_human_resources_stats.organisation_id');
        $queryBuilder->leftJoin('organisation_catalogue_stats', 'organisations.id', '=', 'organisation_catalogue_stats.organisation_id');

        foreach ($this->getElementGroups() as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }

        return $queryBuilder
            ->defaultSort('code')
            ->select(['name', 'slug', 'type', 'code', 'number_employees_state_working', 'number_shops_state_open'])
            ->allowedSorts(['name', 'type', 'code', 'number_employees_state_working', 'number_shops_state_open'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Group $group, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix, $group) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }
            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title'       => __('no organisation'),
                        'description' => $this->canEdit ? __('Get started by creating a new organisation.') : null,
                        'count'       => $group->number_organisations,
                        'action'      => $this->canEdit ? [
                            'type'    => 'button',
                            'style'   => 'create',
                            'tooltip' => __('new organisation'),
                            'label'   => __('organisation'),
                            'route'   => [
                                'name'       => 'grp.organisations.create',
                                'parameters' => array_values(request()->route()->originalParameters())
                            ]
                        ] : null
                    ]
                )
                ->column(key: 'type', label: '', canBeHidden: false, sortable: false, searchable: false, type: 'icon')
                ->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_employees_state_working', label: __('employees'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_shops_state_open', label: __('shops'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'action', label: __('action'), canBeHidden: false, sortable: false, searchable: false)
                ->defaultSort('code');
        };
    }

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->authTo('sysadmin.edit');

        return $request->user()->authTo('sysadmin.view');
    }


    public function htmlResponse(LengthAwarePaginator $organisations): Response
    {
        return Inertia::render(
            'Organisations/Organisations',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('organisations'),
                'pageHead'    => [
                    'icon'    => [
                        'icon'  => ['fal', 'fa-building'],
                        'title' => __('organisations')
                    ],
                    'title'   => __('organisations'),
                    'actions' => [
                        $this->canEdit ? [
                            'type'  => 'button',
                            'style' => 'create',
                            'label' => __('organisation'),
                            'route' => [
                                'name'       => 'grp.organisations.create',
                                'parameters' => []
                            ]
                        ] : false
                    ]
                ],
                'data'        => OrganisationsResource::collection($organisations),
            ]
        )->table($this->tableStructure($this->group));
    }


    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($this->group);
    }

    public function getBreadcrumbs(): array
    {
        return
            array_merge(
                ShowGroupDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'grp.organisations.index'
                            ],
                            'label' => __('Organisations'),
                        ]
                    ]
                ]
            );
    }


}
