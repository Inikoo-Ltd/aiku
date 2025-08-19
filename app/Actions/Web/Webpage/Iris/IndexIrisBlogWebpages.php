<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Feb 2024 14:48:23 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage\Iris;

use App\Actions\IrisAction;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Http\Resources\Web\BlogWebpagesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexIrisBlogWebpages extends IrisAction
{
    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);
        return $this->handle($this->website);
    }


    public function handle(Website $website, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('webpages.code', $value)
                    ->orWhereStartWith('webpages.url', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Webpage::class);
        $queryBuilder->where('webpages.website_id', $website->id);
        $queryBuilder->where('webpages.type', WebpageTypeEnum::BLOG);
        $queryBuilder->where('webpages.state', WebpageStateEnum::LIVE);

        return $queryBuilder
            ->defaultSort('id')
            ->allowedSorts(['code', 'title'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Website $parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                    [
                            'title' => __("No webpages found"),
                            'count' => $parent->webStats->number_webpages,
                        ],
                );
            $table->column(key: 'title', label: __('title'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'url', label: __('url'), canBeHidden: false, sortable: true, searchable: true);

            $table->defaultSort('title');
        };
    }

    public function htmlResponse(LengthAwarePaginator $webpages, ActionRequest $request): Response
    {
        return Inertia::render(
            'BlogsTable',
            [
                'title'       => __('blogs'),
                'pageHead'    => [
                    'title'         => __('blogs'),
                    'icon'          => [
                        'icon'  => ['fal', 'fa-browser'],
                        'title' => __('blog')
                    ],
                ],
                'data'        => BlogWebpagesResource::collection($webpages),

            ]
        )->table($this->tableStructure(parent: $this->website));
    }
}
