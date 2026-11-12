<?php

/*
 * Author: Rifqi Taufiqurrohman
 * Created: Thu, 12 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\StaticWebsite\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\SysAdmin\Group;
use App\Models\Web\StaticWebsite;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class IndexStaticWebsites extends OrgAction
{
    public function handle(Group $group, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('static_websites.name', $value)
                    ->orWhereStartWith('static_websites.domain', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(StaticWebsite::class)
            ->where('static_websites.group_id', $group->id)
            ->defaultSort('static_websites.name')
            ->select([
                'static_websites.id',
                'static_websites.slug',
                'static_websites.name',
                'static_websites.domain',
                'static_websites.state',
                'static_websites.crawled_at',
                'static_websites.updated_at',
            ])
            ->withCount('webpages')
            ->allowedSorts(['name', 'domain', 'state', 'webpages_count', 'crawled_at', 'updated_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()?->getName())
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
                ->withEmptyState(
                    [
                        'title'       => __('No info websites yet'),
                        'description' => __('Information-only websites served by Aiku, without a shop, appear here'),
                    ],
                )
                ->column(key: 'state_icon', label: '', canBeHidden: false, type: 'icon')
                ->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'domain', label: __('Domain'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'webpages_count', label: __('Pages'), canBeHidden: false, sortable: true, type: 'number')
                ->column(key: 'crawled_at', label: __('Copied from source'), canBeHidden: false, sortable: true, type: 'date')
                ->column(key: 'updated_at', label: __('Last change'), canBeHidden: false, sortable: true, type: 'date')
                ->column(key: 'preview', label: '', canBeHidden: false)
                ->defaultSort('name');
        };
    }
}
