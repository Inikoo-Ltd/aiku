<?php

namespace App\Actions\Helpers\Brand\UI;

use App\Actions\Goods\TradeUnit\UI\ShowTradeUnitsDashboard;
use App\Actions\GrpAction;
use App\Actions\Traits\Authorisations\WithGoodsAuthorisation;
use App\Http\Resources\Helpers\BrandResource;
use App\InertiaTable\InertiaTable;
use App\Models\Helpers\Brand;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexBrands extends GrpAction
{
    use WithGoodsAuthorisation;

    private Group $parent;

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = group();
        $this->initialisation($this->parent, $request);

        return $this->handle();
    }

    public function handle($prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('brands.reference', $value)
                    ->orWhereAnyWordStartWith('brands.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Brand::class);
        $queryBuilder->where('brands.group_id', $this->group->id);

        return $queryBuilder
            ->defaultSort('brands.name')
            ->select([
                'brands.slug',
                'brands.reference',
                'brands.name',
                'brands.id',
                'brands.number_models',
            ])
            ->allowedSorts(['name', 'reference', 'number_models'])
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
                ->defaultSort('name')
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState([
                    'title' => __('No brands found'),
                ])
                ->column(key: 'reference', label: __('Reference'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_models', label: __('Models'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'actions', label: '', canBeHidden: false, align: 'right');
        };
    }

    public function jsonResponse(LengthAwarePaginator $brands): AnonymousResourceCollection
    {
        return BrandResource::collection($brands);
    }

    public function htmlResponse(LengthAwarePaginator $brands, ActionRequest $request): Response
    {
        return Inertia::render(
            'Goods/Brands',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Brands'),
                'pageHead'    => [
                    'title'     => __('Brands'),
                    'iconRight' => [
                        'icon'  => ['fal', 'fa-copyright'],
                        'title' => __('Brands'),
                    ],
                    'actions'   => [
                        [
                            'type'    => 'button',
                            'style'   => 'create',
                            'tooltip' => __('New brand'),
                            'label'   => __('Brand'),
                            'route'   => [
                                'name'       => 'grp.trade_units.brands.create',
                                'parameters' => []
                            ],
                            'method'  => 'get'
                        ]
                    ],
                ],
                'data' => BrandResource::collection($brands),
            ]
        )->table($this->tableStructure());
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, ?string $suffix = null): array
    {
        $headCrumb = function (array $routeParameters, ?string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Brands'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ]
            ];
        };

        return array_merge(
            ShowTradeUnitsDashboard::make()->getBreadcrumbs(),
            $headCrumb(
                [
                    'name'       => $routeName,
                    'parameters' => $routeParameters
                ],
                $suffix
            )
        );
    }
}
