<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 Nov 2023 15:38:37 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Prospect\Queries\UI;

use App\Actions\CRM\Prospect\UI\IndexProspects;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\InertiaAction;
use App\Actions\Traits\WithProspectsSubNavigation;
use App\Enums\UI\CRM\ProspectsQueriesTabsEnum;
use App\Http\Resources\CRM\ProspectQueriesResource;
use App\Http\Resources\History\HistoryResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Prospect;
use App\Models\Helpers\Query;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexProspectQueries extends InertiaAction
{
    use WithProspectsSubNavigation;

    private Shop|Organisation $parent;

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->authTo('crm.prospects.edit');

        return $request->user()->authTo('crm.prospects.view');
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request)->withTab(ProspectsQueriesTabsEnum::values());
        $this->parent = $organisation;

        return $this->handle();
    }

    public function inShop(Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request)->withTab(ProspectsQueriesTabsEnum::values());
        $this->parent = $shop;

        return $this->handle($shop);
    }

    public function handle($prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('queries.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(Query::class)
            ->where('model', 'Prospect');


        return $query
            ->defaultSort('queries.name')
            ->allowedSorts(['name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withModelOperations($modelOperations)
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title'       => 'no lists found',
                        'description' => null,
                        'count'       => 0
                    ]
                )
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'description', label: __('description'), sortable: true)
                ->column(key: 'number_items', label: __('prospects'))
                ->column(key: 'actions', label: __('actions'));
        };
    }


    public function htmlResponse(LengthAwarePaginator $prospects, ActionRequest $request): Response
    {
        $subNavigation = $this->getSubNavigation($request);

        return Inertia::render(
            'CRM/Prospects/Queries',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters(),
                ),
                'title'       => __('prospect lists'),
                'pageHead'    => [
                    'title'         => __('prospect lists'),
                    'subNavigation' => $subNavigation,
                    'actions'       => [
                        $this->canEdit ? [
                            'type'  => 'button',
                            'style' => 'create',
                            'label' => __('prospect list'),
                            'route' => [
                                'name'       => 'grp.org.shops.show.crm.prospects.lists.create',
                                'parameters' => array_values($this->originalParameters)
                            ]
                        ] : []
                    ]
                ],

                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => ProspectsQueriesTabsEnum::navigation(),
                ],


                ProspectsQueriesTabsEnum::LISTS->value => $this->tab == ProspectsQueriesTabsEnum::LISTS->value ?
                    fn () => ProspectQueriesResource::collection(IndexProspectQueries::run(prefix: ProspectsQueriesTabsEnum::LISTS->value))
                    : Inertia::lazy(fn () => ProspectQueriesResource::collection(IndexProspectQueries::run(prefix: ProspectsQueriesTabsEnum::LISTS->value))),

                ProspectsQueriesTabsEnum::HISTORY->value => $this->tab == ProspectsQueriesTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run(model: Prospect::class, prefix: ProspectsQueriesTabsEnum::HISTORY->value))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run(model: Prospect::class, prefix: ProspectsQueriesTabsEnum::HISTORY->value))),


            ]
        )->table($this->tableStructure(prefix: ProspectsQueriesTabsEnum::LISTS->value))
            ->table(IndexHistory::make()->tableStructure(prefix: ProspectsQueriesTabsEnum::HISTORY->value));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, $suffix = null): array
    {
        return match ($routeName) {
            'grp.org.shops.show.crm.prospects.lists.index',
            'grp.org.shops.show.crm.prospects.lists.show' =>
            array_merge(
                (new IndexProspects())->getBreadcrumbs(
                    'grp.org.shops.show.crm.prospects.index',
                    $routeParameters
                ),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'grp.org.shops.show.crm.prospects.lists.index',
                                'parameters' => $routeParameters
                            ],
                            'label' => __('Lists'),
                            'icon'  => 'fal fa-bars',
                        ],
                        'suffix' => $suffix

                    ]
                ]
            ),
            default => []
        };
    }


}
