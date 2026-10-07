<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Web\SeoTrackedKeyword;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class IndexSeoTrackedKeywords extends OrgAction
{
    public function handle(Shop $shop, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where('seo_tracked_keywords.keyword', 'ilike', '%'.addcslashes(strip_tags($value), '%_\\').'%');
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(SeoTrackedKeyword::class)
            ->where('seo_tracked_keywords.shop_id', $shop->id)
            ->leftJoin('webpages', 'webpages.id', '=', 'seo_tracked_keywords.target_webpage_id')
            ->defaultSort('seo_tracked_keywords.keyword')
            ->select([
                'seo_tracked_keywords.id',
                'seo_tracked_keywords.keyword',
                'seo_tracked_keywords.country_code',
                'seo_tracked_keywords.language_code',
                'seo_tracked_keywords.device',
                'seo_tracked_keywords.frequency',
                'seo_tracked_keywords.is_active',
                'seo_tracked_keywords.created_at',
                'webpages.code as target_webpage_code',
            ])
            ->allowedSorts([
                AllowedSort::field('keyword', 'seo_tracked_keywords.keyword'),
                AllowedSort::field('country_code', 'seo_tracked_keywords.country_code'),
                AllowedSort::field('created_at', 'seo_tracked_keywords.created_at'),
            ])
            ->allowedFilters([$globalSearch])
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
                ->withLabelRecord([__('keyword'), __('keywords')])
                ->withEmptyState([
                    'title'       => __('No keywords tracked yet'),
                    'description' => __('Add the keywords the team wants to rank for. Positions are checked once rank tracking is switched on.'),
                ])
                ->column(key: 'keyword', label: __('Keyword'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'country_code', label: __('Country'), sortable: true)
                ->column(key: 'language_code', label: __('Language'))
                ->column(key: 'device', label: __('Device'))
                ->column(key: 'frequency', label: __('Check'), tooltip: __('How often the Google position is checked once rank tracking is switched on'), tooltipIcon: true)
                ->column(key: 'is_active', label: __('Active'))
                ->column(key: 'actions', label: '', canBeHidden: false)
                ->defaultSort('keyword');
        };
    }
}
