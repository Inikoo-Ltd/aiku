<?php

namespace App\Actions\Helpers\Tag\UI;

use App\Actions\OrgAction;
use App\Enums\Helpers\Tag\TagScopeEnum;
use App\Http\Resources\Catalogue\TagsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Helpers\Tag;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use App\Actions\Goods\TradeUnit\UI\ShowTradeUnitsDashboard;

class IndexTagsProductProperty extends OrgAction
{
    public function handle($prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query
                    ->whereStartWith('name', $value)
                    ->orWhereWith('scope', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(Tag::class)
            ->where('scope', TagScopeEnum::PRODUCT_PROPERTY)
            ->defaultSort('name')
            ->select(['id', 'name', 'slug', 'scope'])
            ->allowedSorts(['name', 'scope'])
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
                    ->pageName($prefix . 'Page');
            }

            $table
                ->column(key: 'image', label: __(''), sortable: true, searchable: true , type: 'avatar')
                ->column(key: 'name', label: __('Name'), sortable: true, searchable: true)
                ->column(key: 'scope', label: __('Scope'), canBeHidden: false)
                ->column(key: 'action', label: __('Action'))
                ->withGlobalSearch()
                ->defaultSort('name');
        };
    }

    public function jsonResponse(LengthAwarePaginator $tags): AnonymousResourceCollection
    {
        return TagsResource::collection($tags);
    }

    public function htmlResponse(LengthAwarePaginator $tags, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Tags/TagsProductProperty',
            [
               /*  'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ), */
                'title'       => __('Tags'),
                'pageHead'    => [
                    'title'     => __('Tags'),
                    'iconRight' => [
                        'icon'  => ['fal', 'fa-tags'],
                        'title' => __('tags'),
                    ],
                ],
                'data' => TagsResource::collection($tags),
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
