<?php

/*
 * author Arya Permana - Kirin
 * created on 04-12-2024-14h-07m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Goods\Ingredient\UI;

use App\Actions\Goods\UI\ShowGoodsDashboard;
use App\Actions\GrpAction;
use App\Actions\Traits\Authorisations\WithGoodsAuthorisation;
use App\Http\Resources\Goods\IngredientsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Goods\Ingredient;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexIngredients extends GrpAction
{
    use WithGoodsAuthorisation;

    public function asController(ActionRequest $request): LengthAwarePaginator
    {

        $this->initialisation(group(), $request);

        return $this->handle($this->group);
    }

    public function handle(Group $group, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('ingredients.name', $value);
            });
        });
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Ingredient::class);
        $queryBuilder->where('ingredients.group_id', $group->id);


        return $queryBuilder
            ->defaultSort('ingredients.name')
            ->select([
                'ingredients.slug',
                'ingredients.name',
                'ingredients.number_trade_units',
                'ingredients.number_stocks',
                'ingredients.number_trade_units',
            ])
            ->allowedSorts(['name', 'number_trade_units', 'number_stocks', 'number_trade_units'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure($prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }
            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title'       => __('no ingredients'),
                    ]
                )
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_trade_units', label: __('Trade Units'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_stocks', label: __('Stocks'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_trade_units', label: __('Master Products'), canBeHidden: false, sortable: true, searchable: true)
                ->defaultSort('code');
        };
    }

    public function htmlResponse(LengthAwarePaginator $ingredients, ActionRequest $request): Response
    {

        return Inertia::render(
            'Goods/Ingredients',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __("Ingredients"),
                'pageHead'    => [
                    'title'   => __("Ingredients"),
                    'icon'    => [
                        'title' => __("Ingredients"),
                        'icon'  => 'fal fa-boxes-alt'
                    ],
                ],
                'data' => IngredientsResource::collection($ingredients),
            ]
        )->table($this->tableStructure());
    }

    public function getBreadcrumbs($suffix = null): array
    {
        return array_merge(
            ShowGoodsDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name' => 'grp.goods.ingredients.index'
                        ],
                        'label' => __("Ingredients"),
                        'icon'  => 'fal fa-bars',
                    ],
                    'suffix' => $suffix

                ]
            ]
        );
    }
}
