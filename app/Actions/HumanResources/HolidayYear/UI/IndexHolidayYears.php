<?php

namespace App\Actions\HumanResources\HolidayYear\UI;

use App\Actions\HumanResources\Calendar\WithCalendarSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Actions\UI\HumanResources\ShowHumanResourcesDashboard;
use App\Http\Resources\HumanResources\HolidayYearResource;
use App\InertiaTable\InertiaTable;
use App\Models\HumanResources\HolidayYear;
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

class IndexHolidayYears extends OrgAction
{
    use WithHumanResourcesAuthorisation;
    use WithCalendarSubNavigation;

    private Group|Organisation $parent;

    public function handle(Group|Organisation $parent, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('holiday_years.label', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(HolidayYear::class);

        if ($parent instanceof Organisation) {
            $queryBuilder->where('holiday_years.organisation_id', $parent->id);
        } else {
            $queryBuilder->where('holiday_years.group_id', $parent->id);
        }

        $queryBuilder->leftJoin('organisations', 'holiday_years.organisation_id', '=', 'organisations.id');

        return $queryBuilder
            ->defaultSort('-start_date')
            ->select([
                'holiday_years.*',
                'organisations.name as organisation_name',
                'organisations.slug as organisation_slug',
            ])
            ->allowedSorts(['label', 'start_date', 'end_date', 'is_active'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Group|Organisation $parent, ?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix . 'Page');
            }

            $table
                ->withGlobalSearch()
                ->column(key: 'label', label: __('Label'), sortable: true, searchable: true)
                ->column(key: 'start_date', label: __('Start Date'), sortable: true)
                ->column(key: 'end_date', label: __('End Date'), sortable: true)
                ->column(key: 'is_active', label: __('Active'), sortable: true)
                ->column(key: 'action', label: __('Actions'));

            if ($parent instanceof Group) {
                $table->column(key: 'organisation_name', label: __('Organisation'), searchable: true);
            }

            $table->defaultSort('-start_date');
        };
    }

    public function jsonResponse(LengthAwarePaginator $holidayYears): AnonymousResourceCollection
    {
        return HolidayYearResource::collection($holidayYears);
    }

    public function htmlResponse(LengthAwarePaginator $holidayYears, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/HumanResources/HolidayYears',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'title'       => __('Holiday Years'),
                'pageHead'    => [
                    'icon'          => ['fal', 'fa-calendar-alt'],
                    'title'         => __('Holiday Years'),
                    'subNavigation' => $this->getCalendarSubNavigation(),
                    'actions'       => [
                        [
                            'type'  => 'button',
                            'style' => 'create',
                            'key'   => 'create holiday year',
                            'label' => __('Add Holiday Year'),
                            'icon'  => ['fal', 'fa-plus'],
                        ],
                    ],
                ],
                'data'        => $this->jsonResponse($holidayYears),
            ]
        )->table($this->tableStructure($this->parent));
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request);

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
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Holiday Years'),
                        'icon'  => 'fal fa-calendar-alt',
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.hr.holiday_years.index' =>
            array_merge(
                ShowHumanResourcesDashboard::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $routeName,
                    $routeParameters
                )
            ),
            'grp.overview.hr.holiday_years.index' =>
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
