<?php

namespace App\Actions\HumanResources\Leave\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Actions\UI\HumanResources\ShowHumanResourcesDashboard;
use App\Enums\HumanResources\Leave\LeaveCategoryEnum;
use App\InertiaTable\InertiaTable;
use App\Models\HumanResources\LeaveType;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexLeaveTypes extends OrgAction
{
    use WithHumanResourcesAuthorisation;
    use WithLeaveSubNavigation;


    private Organisation $parent;

    public function handle(Organisation $organisation, ?string $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('leave_types.code', $value)
                    ->orWhereAnyWordStartWith('leave_types.name', $value);
            });
        });

        $queryBuilder = QueryBuilder::for(LeaveType::class)
            ->where('leave_types.organisation_id', $organisation->id);

        return $queryBuilder
            ->defaultSort('code')
            ->select([
                'leave_types.id',
                'leave_types.code',
                'leave_types.name',
                'leave_types.color',
                'leave_types.description',
                'leave_types.category',
                'leave_types.requires_approval',
                'leave_types.max_days_per_year',
                'leave_types.is_active',
            ])
            ->allowedSorts(['code', 'name', 'category', 'requires_approval', 'max_days_per_year', 'is_active'])
            ->allowedFilters([$globalSearch, 'code', 'name', 'category', 'requires_approval', 'is_active'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix . 'Page');
            }

            $table
                ->withGlobalSearch()
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'color', label: __('Color'), canBeHidden: true)
                ->column(key: 'category', label: __('Category'), canBeHidden: false, sortable: true)
                ->column(key: 'requires_approval', label: __('Requires Approval'), canBeHidden: true, sortable: true)
                ->column(key: 'max_days_per_year', label: __('Maximum Days'), canBeHidden: true, sortable: true)
                ->column(key: 'is_active', label: __('Active'), canBeHidden: true, sortable: true)
                ->column(key: 'action', label: __('Actions'), canBeHidden: false)
                ->defaultSort('code');
        };
    }

    public function htmlResponse(LengthAwarePaginator $leaveTypes, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/HumanResources/LeaveTypes',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title' => __('Leave types'),
                'pageHead' => [
                    'icon' => ['fal', 'fa-calendar-minus'],
                    'title' => __('Leave Type'),
                    'actions' => [
                        [
                            'type' => 'button',
                            'style' => 'create',
                            'key' => 'leave type',
                            'label' => __('New Leave Type'),
                            'icon' => ['fal', 'fa-plus'],
                        ],
                    ],
                    'subNavigation' => $this->getLeaveSubNavigation($request),
                ],
                'data' => $leaveTypes,
                'categoryOptions' => collect(LeaveCategoryEnum::cases())
                    ->map(fn($case) => [
                        'value' => $case->value,
                        'label' => $case->label(),
                    ])
                    ->values(),
            ]
        )->table($this->tableStructure());
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (string $routeName, array $routeParameters) {
            return [
                [
                    'type' => 'simple',
                    'simple' => [
                        'route' => [
                            'name' => $routeName,
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Leave Type'),
                        'icon' => 'fal fa-bars',
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.hr.leaves.types.index' =>
            array_merge(
                ShowHumanResourcesDashboard::make()->getBreadcrumbs($routeParameters),
                $headCrumb($routeName, $routeParameters)
            ),
            default => [],
        };
    }
}
