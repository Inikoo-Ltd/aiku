<?php

/*
 * author Arya Permana - Kirin
 * created on 24-12-2024-16h-13m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\SysAdmin\User\UI;

use App\Actions\GrpAction;
use App\Actions\SysAdmin\User\WithUserSubNavigation;
use App\Actions\SysAdmin\WithSysAdminAuthorization;
use App\Http\Resources\History\HistoryResource;
use App\InertiaTable\InertiaTable;
use App\Models\Helpers\Audit;
use App\Models\SysAdmin\User;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexUserActions extends GrpAction
{
    use WithSysAdminAuthorization;
    use WithUserSubNavigation;

    private User $user;

    public function handle(User $user, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('tags', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Audit::class);

        $queryBuilder->where('user_type', 'User')
            ->where('user_id', $user->id);

        return $queryBuilder
            ->defaultSort('created_at')
            ->allowedSorts(['ip_address', 'created_at', 'user_name', 'old_values', 'new_values', 'event'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure($prefix = null, ?array $exportLinks = null): Closure
    {
        return function (InertiaTable $table) use ($exportLinks, $prefix) {

            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withExportLinks($exportLinks)
                ->column(key: 'expand', label: '', type: 'icon')
                ->column(key: 'datetime', label: __('Date'), canBeHidden: false, sortable: false)
                ->column(key: 'user_name', label: __('User'), canBeHidden: false, sortable: false)
                ->column(key: 'old_values', label: __('Old Value'), canBeHidden: false, sortable: false)
                ->column(key: 'new_values', label: __('New Value'), canBeHidden: false, sortable: false)
                ->column(key: 'event', label: __('Action'), canBeHidden: false, sortable: false)
                ->defaultSort('ip_address');
        };
    }

    public function jsonResponse(LengthAwarePaginator $audits): AnonymousResourceCollection
    {
        return HistoryResource::collection($audits);
    }

    public function htmlResponse(LengthAwarePaginator $audits, ActionRequest $request): Response
    {
        $subNavigation = $this->getUserNavigation($this->user, $request);
        $title = __('User Actions');
        $model = __('Audit');
        $icon = [
            'icon' => ['fal', 'fa-clock'],
            'title' => __('User Actions'),
        ];

        return Inertia::render(
            'SysAdmin/UserActions',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'title' => __('User Actions'),
                'pageHead' => [
                    'title' => $title,
                    'model' => $model,
                    'icon' => $icon,
                    'subNavigation' => $subNavigation,
                ],

                'data' => HistoryResource::collection($audits),
            ]
        )->table($this->tableStructure());
    }

    public function asController(User $user, ActionRequest $request)
    {
        $this->user = $user;
        $this->initialisation(group(), $request);

        return $this->handle($user);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, ?string $suffix = null): array
    {
        $headCrumb = function (array $routeParameters, ?string $suffix) {
            return [
                [
                    'type' => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Actions'),
                        'icon' => 'fal fa-bars',
                    ],
                    'suffix' => $suffix,
                ],
            ];
        };

        return match ($routeName) {
            'grp.sysadmin.users.show.actions.index' => array_merge(
                ShowUser::make()->getBreadcrumbs('grp.sysadmin.users.show', $routeParameters),
                $headCrumb(
                    [
                        'name' => $routeName,
                        'parameters' => $routeParameters,
                    ],
                    $suffix
                )
            ),
            default => []
        };
    }
}
