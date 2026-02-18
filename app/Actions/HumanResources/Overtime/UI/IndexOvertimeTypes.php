<?php

namespace App\Actions\HumanResources\Overtime\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Actions\UI\HumanResources\ShowHumanResourcesDashboard;
use App\Enums\HumanResources\Overtime\OvertimeCategoryEnum;
use App\Enums\HumanResources\Overtime\OvertimeCompensationTypeEnum;
use App\InertiaTable\InertiaTable;
use App\Models\HumanResources\OvertimeType;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexOvertimeTypes extends OrgAction
{
    use WithHumanResourcesAuthorisation;

    private Organisation $parent;

    public function handle(Organisation $organisation, ?string $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('overtime_types.code', $value)
                    ->orWhereAnyWordStartWith('overtime_types.name', $value);
            });
        });

        $queryBuilder = QueryBuilder::for(OvertimeType::class)
            ->where('overtime_types.organisation_id', $organisation->id);

        return $queryBuilder
            ->defaultSort('code')
            ->select([
                'overtime_types.id',
                'overtime_types.code',
                'overtime_types.name',
                'overtime_types.color',
                'overtime_types.description',
                'overtime_types.category',
                'overtime_types.compensation_type',
                'overtime_types.multiplier',
                'overtime_types.is_active',
            ])
            ->allowedSorts(['code', 'name', 'category', 'compensation_type', 'is_active'])
            ->allowedFilters([$globalSearch, 'code', 'name', 'category', 'compensation_type'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'color', label: __('Color'), canBeHidden: true)
                ->column(key: 'category', label: __('Category'), canBeHidden: false, sortable: true)
                ->column(key: 'compensation_type', label: __('Compensation type'), canBeHidden: false, sortable: true)
                ->column(key: 'multiplier', label: __('Multiplier'), canBeHidden: true, sortable: true)
                ->column(key: 'is_active', label: __('Active'), canBeHidden: true, sortable: true)
                ->column(key: 'action', label: __('Actions'), canBeHidden: false)
                ->defaultSort('code');
        };
    }

    public function htmlResponse(LengthAwarePaginator $overtimeTypes, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/HumanResources/OvertimeTypes',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'                 => __('Overtime types'),
                'pageHead'              => [
                    'icon'    => ['fal', 'clock'],
                    'title'   => __('Overtime types'),
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'create',
                            'key'   => 'overtime type',
                            'label' => __('New overtime type'),
                            'icon'  => ['fal', 'fa-plus'],
                        ],
                    ],
                ],
                'data'                  => $overtimeTypes,
                'categoryOptions'       => collect(OvertimeCategoryEnum::labels())
                    ->map(fn ($label, $value) => [
                        'value' => $value,
                        'label' => $label,
                    ])
                    ->values(),
                'compensationTypeOptions' => collect(OvertimeCompensationTypeEnum::labels())
                    ->map(fn ($label, $value) => [
                        'value' => $value,
                        'label' => $label,
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
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => $routeName,
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Overtime types'),
                        'icon'  => 'fal fa-bars',
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.hr.overtime_types.index' =>
            array_merge(
                ShowHumanResourcesDashboard::make()->getBreadcrumbs($routeParameters),
                $headCrumb($routeName, $routeParameters)
            ),
            default => [],
        };
    }
}
