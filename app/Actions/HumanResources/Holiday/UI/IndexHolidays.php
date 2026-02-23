<?php

namespace App\Actions\HumanResources\Holiday\UI;

use App\Actions\HumanResources\Calendar\WithCalendarSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Actions\UI\HumanResources\ShowHumanResourcesDashboard;
use App\Http\Resources\HumanResources\HolidayResource;
use App\InertiaTable\InertiaTable;
use App\Models\HumanResources\Holiday;
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

class IndexHolidays extends OrgAction
{
    use WithHumanResourcesAuthorisation;
    use WithCalendarSubNavigation;

    private Group|Organisation $parent;

    public function handle(Group|Organisation $parent, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('holidays.label', $value);
            });
        });

        $yearFilter = AllowedFilter::callback('year', function ($query, $value) {
            $query->where('holidays.year', $value);
        });

        $typeFilter = AllowedFilter::callback('type', function ($query, $value) {
            $query->where('holidays.type', $value);
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Holiday::class);

        if ($parent instanceof Organisation) {
            $queryBuilder->where('holidays.organisation_id', $parent->id);
        } else {
            $queryBuilder->where('holidays.group_id', $parent->id);
        }

        $queryBuilder->leftJoin('organisations', 'holidays.organisation_id', '=', 'organisations.id');

        return $queryBuilder
            ->defaultSort('from')
            ->select([
                'holidays.id',
                'holidays.type',
                'holidays.year',
                'holidays.label',
                'holidays.from',
                'holidays.to',
                'organisations.name as organisation_name',
                'organisations.slug as organisation_slug',
            ])
            ->allowedSorts(['year', 'from', 'to', 'label', 'type'])
            ->allowedFilters([$globalSearch, $yearFilter, $typeFilter])
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
                ->column(key: 'year', label: __('Year'), sortable: true)
                ->column(key: 'label', label: __('Name'), sortable: true, searchable: true)
                ->column(key: 'type_label', label: __('Type'), sortable: true)
                ->column(key: 'from', label: __('From'), sortable: true)
                ->column(key: 'to', label: __('To'), sortable: true)
                ->column(key: 'duration_days', label: __('Days'), sortable: true);

            if ($parent instanceof Group) {
                $table->column(key: 'organisation_name', label: __('Organisation'), searchable: true);
            }

            $table->defaultSort('from');
        };
    }

    public function jsonResponse(LengthAwarePaginator $holidays): AnonymousResourceCollection
    {
        return HolidayResource::collection($holidays);
    }

    public function htmlResponse(LengthAwarePaginator $holidays, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/HumanResources/Holidays',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'title'       => __('Holidays'),
                'pageHead'    => [
                    'icon'          => ['fal', 'fa-umbrella-beach'],
                    'title'         => __('Holidays'),
                    'subNavigation' => $this->getCalendarSubNavigation(),
                ],
                'data'        => $this->jsonResponse($holidays),
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
                        'label' => __('Holidays'),
                        'icon'  => 'fal fa-umbrella-beach',
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.hr.holidays.index' =>
            array_merge(
                ShowHumanResourcesDashboard::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $routeName,
                    $routeParameters
                )
            ),
            'grp.overview.hr.holidays.index' =>
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
