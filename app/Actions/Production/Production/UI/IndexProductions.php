<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 May 2024 19:25:21 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Production\UI;

use App\Actions\Dashboard\ShowOrganisationDashboard;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\OrgAction;
use App\Enums\UI\Production\ProductionsTabsEnum;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\Production\ProductionsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexProductions extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->authTo('org-supervisor.'.$this->organisation->id);
        return $request->user()->authTo(['org-supervisor.'.$this->organisation->id,'productions-view.'.$this->organisation->id]);
    }

    public function asController(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($organisation, $request)->withTab(ProductionsTabsEnum::values());

        return $this->handle($organisation);
    }

    public function handle(Organisation $organisation, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('productions.name', $value)
                    ->orWhereStartWith('productions.code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Production::class);

        $queryBuilder->where('organisation_id', $organisation->id);


        return $queryBuilder
            ->defaultSort('productions.code')
            ->select([
                'productions.code as code',
                'productions.id',
                'productions.name',
                'productions.state',
                'productions.slug as slug',
                'number_raw_materials',
                'number_artefacts',
                'number_manufacture_tasks',

            ])
            ->leftJoin('production_stats', 'production_stats.production_id', 'productions.id')
            ->allowedSorts(['code', 'name', 'number_raw_materials', 'number_artefacts','number_manufacture_tasks'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Organisation $organisation, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($organisation, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }
            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title'       => __('no production plant'),
                        'description' => $this->canEdit ? __('Get started set up your new production plant.') : null,
                        'count'       => $organisation->manufactureStats->number_productions,
                        'action'      => $this->canEdit ? [
                            'type'    => 'button',
                            'style'   => 'create',
                            'tooltip' => __('new production plant'),
                            'label'   => __('production plant'),
                            'route'   => [
                                'name'       => 'grp.org.productions.create',
                                'parameters' => $organisation->slug
                            ]
                        ] : null
                    ]
                )
                ->column(key: 'state_icon', label: '', canBeHidden: false, sortable: false, searchable: false, type: 'icon')
                ->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_raw_materials', label: __('materials'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_manufacture_tasks', label: __('tasks'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_artefacts', label: __('artefacts'), canBeHidden: false, sortable: true, searchable: true)
                ->defaultSort('code');
        };
    }


    public function jsonResponse(LengthAwarePaginator $productions): AnonymousResourceCollection
    {
        return ProductionsResource::collection($productions);
    }


    public function htmlResponse(LengthAwarePaginator $productions, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Production/Productions',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('factories'),
                'pageHead'    => [
                    'title'   => __('Production plants'),
                    'icon'    => [
                        'title' => __('factories'),
                        'icon'  => 'fal fa-industry'
                    ],
                    'actions' => [
                        $this->canEdit && $request->route()->routeName == 'grp.org.productions.index' ? [
                            'type'    => 'button',
                            'style'   => 'create',
                            'tooltip' => __('Set production'),
                            'label'   => __('Factory'),
                            'route'   => [
                                'name'       => 'grp.org.productions.create',
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ] : false,
                    ]
                ],

                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => ProductionsTabsEnum::navigation(),
                ],

                ProductionsTabsEnum::PRODUCTIONS->value => $this->tab == ProductionsTabsEnum::PRODUCTIONS->value ?
                    fn () => ProductionsResource::collection($productions)
                    : Inertia::lazy(fn () => ProductionsResource::collection($productions)),


                ProductionsTabsEnum::PRODUCTIONS_HISTORIES->value => $this->tab == ProductionsTabsEnum::PRODUCTIONS_HISTORIES->value ?
                    fn () => HistoryResource::collection(IndexHistory::run(Production::class))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run(Production::class)))


            ]
        )->table(
            $this->tableStructure(
                organisation: $this->organisation,
                prefix: ProductionsTabsEnum::PRODUCTIONS->value
            )
        );
    }

    public function getBreadcrumbs(array $routeParameters, $suffix = null): array
    {
        return array_merge(
            ShowOrganisationDashboard::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.productions.index',
                            'parameters' => $routeParameters
                        ],
                        'label' => __('Factories'),
                        'icon'  => 'fal fa-bars',
                    ],
                    'suffix' => $suffix

                ]
            ]
        );
    }
}
