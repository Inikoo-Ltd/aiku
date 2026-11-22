<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 23 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Web\Website;
use App\Models\Web\WebsiteTextTranslation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * The website's texts in its other languages (INI-510), for staff to correct a machine translation.
 */
class IndexWebsiteTextTranslations extends OrgAction
{
    public function handle(Website $website, string $prefix): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->where('source_text', 'ilike', '%'.$value.'%')
                    ->orWhere('text', 'ilike', '%'.$value.'%');
            });
        });

        InertiaTable::updateQueryBuilderParameters($prefix);

        return QueryBuilder::for(WebsiteTextTranslation::class)
            ->where('website_id', $website->id)
            ->whereIn('locale', $website->shop->websiteLanguageCodes())
            ->defaultSort('-updated_at')
            ->select(['id', 'locale', 'source_text', 'text', 'origin', 'updated_at'])
            ->allowedSorts(['locale', 'origin', 'updated_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(string $prefix): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            $table
                ->name($prefix)
                ->pageName($prefix.'Page')
                ->withGlobalSearch()
                ->withEmptyState(['title' => __('No translated texts yet')])
                ->column(key: 'locale', label: __('Language'), sortable: true)
                ->column(key: 'source_text', label: __('Original'))
                ->column(key: 'text', label: __('Translation'))
                ->column(key: 'origin', label: __('By'), sortable: true)
                ->column(key: 'actions', label: '')
                ->defaultSort('-updated_at');
        };
    }
}
