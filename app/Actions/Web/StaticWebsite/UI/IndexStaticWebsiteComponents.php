<?php

/*
 * Author: Rifqi Taufiqurrohman
 * Created: Thu, 12 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\StaticWebsite\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Web\StaticWebsite;
use App\Models\Web\StaticWebsiteComponent;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class IndexStaticWebsiteComponents extends OrgAction
{
    public function handle(StaticWebsite $staticWebsite, ?string $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(StaticWebsiteComponent::class)
            ->join('static_websites', 'static_websites.id', '=', 'static_website_components.static_website_id')
            ->where('static_website_components.static_website_id', $staticWebsite->id)
            ->defaultSort('static_website_components.code')
            ->select([
                'static_website_components.id',
                'static_website_components.code',
                'static_website_components.updated_at',
                'static_websites.slug as static_website_slug',
            ])
            ->selectRaw('octet_length(static_website_components.html) as size')
            ->selectRaw("(select count(*) from static_webpages where static_webpages.static_website_id = static_website_components.static_website_id and position(('<!--aiku:component:' || static_website_components.code || '-->') in static_webpages.html) > 0) as webpages_count")
            ->withCount('snapshots')
            ->allowedSorts(['code', 'updated_at', 'snapshots_count', 'webpages_count'])
            ->withPaginator($prefix, tableName: request()->route()?->getName())
            ->withQueryString();
    }

    public function tableStructure(?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table
                ->withEmptyState([
                    'title'       => __('No shared parts'),
                    'description' => __('The header and footer become shared parts when the website is imported'),
                ])
                ->column(key: 'code', label: __('Part'), canBeHidden: false, sortable: true)
                ->column(key: 'webpages_count', label: __('Used by pages'), canBeHidden: false, sortable: true, type: 'number')
                ->column(key: 'snapshots_count', label: __('Snapshots'), canBeHidden: false, sortable: true, type: 'number')
                ->column(key: 'size', label: __('Size'), canBeHidden: false, type: 'number')
                ->column(key: 'updated_at', label: __('Last change'), canBeHidden: false, sortable: true, type: 'date')
                ->defaultSort('code');
        };
    }
}
