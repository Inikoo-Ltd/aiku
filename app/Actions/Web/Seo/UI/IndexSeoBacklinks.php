<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Web\SeoBacklink;
use App\Models\Web\Website;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class IndexSeoBacklinks extends OrgAction
{
    public const int RECENT_DAYS = 30;

    public function handle(Website $website, ?string $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $value = '%'.addcslashes(strip_tags($value), '%_\\').'%';

            $query->where(fn ($query) => $query
                ->where('seo_backlinks.source_url', 'ilike', $value)
                ->orWhere('seo_backlinks.anchor', 'ilike', $value)
                ->orWhere('seo_backlinks.target_path', 'ilike', $value));
        });

        return QueryBuilder::for(SeoBacklink::class)
            ->where('seo_backlinks.website_id', $website->id)
            ->leftJoin('webpages', 'webpages.id', '=', 'seo_backlinks.target_webpage_id')
            ->defaultSort('-domain_rank')
            ->select([
                'seo_backlinks.id',
                'seo_backlinks.source_url',
                'seo_backlinks.source_domain',
                'seo_backlinks.source_title',
                'seo_backlinks.domain_rank',
                'seo_backlinks.is_own_website',
                'seo_backlinks.target_url',
                'seo_backlinks.target_path',
                'seo_backlinks.anchor',
                'seo_backlinks.link_type',
                'seo_backlinks.is_dofollow',
                'seo_backlinks.is_broken',
                'seo_backlinks.target_status_code',
                'seo_backlinks.first_seen',
                'seo_backlinks.last_seen',
                'seo_backlinks.lost_at',
                'webpages.code as target_webpage_code',
            ])
            ->allowedSorts([
                AllowedSort::callback('domain_rank', fn ($query, bool $descending) => $query->orderByRaw('seo_backlinks.domain_rank '.($descending ? 'DESC' : 'ASC').' NULLS LAST')),
                AllowedSort::field('first_seen', 'seo_backlinks.first_seen'),
                AllowedSort::field('last_seen', 'seo_backlinks.last_seen'),
            ])
            ->allowedFilters([
                $globalSearch,
                AllowedFilter::callback('status', fn ($query, $value) => match ($value) {
                    'new'    => $query->whereNull('seo_backlinks.lost_at')->where('seo_backlinks.first_seen', '>=', now()->subDays(self::RECENT_DAYS)),
                    'lost'   => $query->where('seo_backlinks.lost_at', '>=', now()->subDays(self::RECENT_DAYS)),
                    'broken' => $query->whereNull('seo_backlinks.lost_at')->where('seo_backlinks.is_broken', true),
                    default  => $query->whereNull('seo_backlinks.lost_at'),
                })->default('live'),
                AllowedFilter::callback('websites', fn ($query, $value) => $value === 'include' ? $query : $query->where('seo_backlinks.is_own_website', false))->default('exclude'),
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
                ->withLabelRecord([__('backlink'), __('backlinks')])
                ->withEmptyState([
                    'title'       => __('No backlinks here'),
                    'description' => __('Our link lists are fetched every four weeks. Try another status, or include our own websites.'),
                ])
                ->column(key: 'source_url', label: __('Linking page'), canBeHidden: false, searchable: true)
                ->column(key: 'domain_rank', label: __('Rank'), tooltip: __('DataForSEO rank of the linking domain, 0 to 100'), sortable: true, align: 'right', tooltipIcon: true)
                ->column(key: 'anchor', label: __('Anchor'))
                ->column(key: 'target_path', label: __('Our page'))
                ->column(key: 'first_seen', label: __('First seen'), sortable: true, align: 'right')
                ->column(key: 'last_seen', label: __('Last seen'), sortable: true, align: 'right')
                ->defaultSort('-domain_rank');
        };
    }
}
