<?php

/*
 * author Arya Permana - Kirin
 * created on 12-03-2025-11h-30m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Web\Redirect\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Http\Resources\Web\RedirectsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Web\Redirect;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;

class IndexRedirects extends OrgAction
{
    use WithWebAuthorisation;

    public function handle(Website|Webpage $parent, $prefix = null): LengthAwarePaginator
    {
        // dd("test");
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('redirects.url', $value)
                    ->orWhereStartWith('webpages.title', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Redirect::class);
        if ($parent instanceof Website) {
            $queryBuilder->where('redirects.website_id', $parent->id);
        } else {
            $queryBuilder->where('redirects.to_webpage_id', $parent->id);
        }

        $queryBuilder->leftjoin('webpages', 'redirects.to_webpage_id', '=', 'webpages.id');

        $queryBuilder
            ->defaultSort('redirects.id')
            ->select([
                'redirects.id',
                'redirects.type',
                'redirects.from_url as url',
                'redirects.from_path as path',
                'webpages.title as to_webpage_title',
                'webpages.url as to_webpage_url',
                'webpages.slug as to_webpage_slug',
            ]);

        return $queryBuilder
            ->allowedSorts(['url', 'type', 'to_webpage_url'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(
        Website|Webpage $parent,
        ?array $modelOperations = null,
        $prefix = null,
    ): Closure {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix . 'Page');
            }

            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                    match (class_basename($parent)) {
                        'Website', 'Webpage' => [
                            'title'       => __("No redirects found"),
                        ],
                        default => null
                    }
                );

            $table
                ->column(key: 'type', label: __('Type'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'url', label: __('From URL'), canBeHidden: false, sortable: true, searchable: true);

            if ($parent instanceof Website) {
                $table
                    ->column(key: 'to_webpage_url', label: __('To Webpage'), canBeHidden: false, sortable: true, searchable: true);
            }

        };
    }

    public function jsonResponse(LengthAwarePaginator $redirects): AnonymousResourceCollection
    {
        return RedirectsResource::collection($redirects);
    }
}
