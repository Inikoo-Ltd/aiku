<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Web\SeoReferringDomain;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class IndexSeoReferringDomains extends OrgAction
{
    /**
     * @param  Carbon|null  $newSince  start of the latest weekly run, null when it was the first one
     */
    public function handle(string $domain, ?Carbon $newSince, ?string $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where('referring_domain', 'ilike', '%'.addcslashes(strip_tags($value), '%_\\').'%');
        });

        return QueryBuilder::for(SeoReferringDomain::class)
            ->where('domain', $domain)
            ->defaultSort('-rank')
            ->select(['id', 'referring_domain', 'rank', 'backlinks', 'is_own_website', 'first_seen', 'first_fetched_at', 'lost_at'])
            ->allowedSorts([
                AllowedSort::field('referring_domain'),
                AllowedSort::callback('rank', fn ($query, bool $descending) => $query->orderByRaw('rank '.($descending ? 'DESC' : 'ASC').' NULLS LAST')),
                AllowedSort::field('backlinks'),
                AllowedSort::field('first_seen'),
            ])
            ->allowedFilters([
                $globalSearch,
                AllowedFilter::callback('status', fn ($query, $value) => match ($value) {
                    'new'   => $newSince ? $query->whereNull('lost_at')->where('first_fetched_at', '>=', $newSince) : $query->whereRaw('false'),
                    'lost'  => $query->whereNotNull('lost_at'),
                    default => $query->whereNull('lost_at'),
                })->default('live'),
                AllowedFilter::callback('websites', fn ($query, $value) => $value === 'include' ? $query : $query->where('is_own_website', false))->default('exclude'),
            ])
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
                ->withLabelRecord([__('referring domain'), __('referring domains')])
                ->withEmptyState([
                    'title'       => __('No referring domains here'),
                    'description' => __('Referring domains are fetched every week. Try another status, or include our own websites.'),
                ])
                ->column(key: 'referring_domain', label: __('Referring domain'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'rank', label: __('Rank'), tooltip: __('DataForSEO domain rank, 0 to 100. Not the Semrush Authority Score or Moz DA, so compare trends, not values'), sortable: true, align: 'right', tooltipIcon: true)
                ->column(key: 'backlinks', label: __('Backlinks'), tooltip: __('Links from this domain to our website'), sortable: true, align: 'right', tooltipIcon: true)
                ->column(key: 'first_seen', label: __('First seen'), tooltip: __('When DataForSEO first found a link from this domain'), sortable: true, align: 'right', tooltipIcon: true)
                ->column(key: 'status', label: __('Status'))
                ->defaultSort('-rank');
        };
    }
}
