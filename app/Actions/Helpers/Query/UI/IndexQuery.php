<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Oct 2023 08:55:05 Malaysia Time, Office, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Query\UI;

use App\Actions\InertiaAction;
use App\Actions\UI\Customer\Portfolio\ShowPortfolio;
use App\Http\Resources\Helpers\QueryResource;
use App\InertiaTable\InertiaTable;
use App\Models\Helpers\Query;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\Services\QueryBuilder;

class IndexQuery extends InertiaAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo('portfolio.banners.view');
    }


    public function handle($prefix = null): LengthAwarePaginator
    {
        $queryBuilder = QueryBuilder::for(Query::class);

        return $queryBuilder
            ->defaultSort('-published_at')
            ->allowedSorts(['published_at', 'published_until'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function asController(): LengthAwarePaginator
    {
        return $this->handle();
    }

    public function tableStructure(Query $query, ?array $modelOperations = null, $prefix = null, ?array $exportLinks = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix, $exportLinks) {
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
                        'title' => __('Query has not been published yet'),
                        'count' => 0
                    ]
                );
            if ($exportLinks) {
                $table->withExportLinks($exportLinks);
            }

            $table->column(key: 'name', label: __('name'), sortable: true)
                ->column(key: 'description', label: __('description'), sortable: true)
                ->column(key: 'number_items', label: __('prospects'))
                ->defaultSort('slug');
        };
    }

    public function htmlResponse(Query $query, ActionRequest $request): Response
    {
        return Inertia::render(
            'Query/Queries',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'    => __('images'),
                'pageHead' => [
                    'title'     => __('images'),
                    'iconRight' => [
                        'title' => __('Image'),
                        'icon'  => 'fal fa-images'
                    ],
                ],
                'data' => new QueryResource($query)
            ]
        );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('queries'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'org.query.index' =>
            array_merge(
                ShowPortfolio::make()->getBreadcrumbs(),
                $headCrumb(
                    [
                        'name' => 'portfolio.images.index',
                        null
                    ]
                ),
            ),

            default => []
        };
    }
}
