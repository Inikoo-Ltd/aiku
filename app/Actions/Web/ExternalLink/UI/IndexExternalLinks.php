<?php

/*
 * author Arya Permana - Kirin
 * created on 21-10-2024-08h-39m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Web\ExternalLink\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Http\Resources\Web\ExternalLinksResource;
use App\InertiaTable\InertiaTable;
use App\Models\Web\ExternalLink;
use App\Models\Web\WebBlock;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;

class IndexExternalLinks extends OrgAction
{
    use WithWebAuthorisation;

    private Website|Webpage|WebBlock $parent;

    public function handle(Website|Webpage|WebBlock $parent, $prefix = null, $bucket = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('external_links.url', $value)
                    ->orWhereStartWith('external_links.status', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(ExternalLink::class);
        $queryBuilder->join('web_block_has_external_link', 'web_block_has_external_link.external_link_id', '=', 'external_links.id');

        if ($parent instanceof Website) {
            $queryBuilder->where('web_block_has_external_link.website_id', $parent->id);
        } elseif ($parent instanceof Webpage) {
            $queryBuilder->where('web_block_has_external_link.webpage_id', $parent->id);
        } elseif ($parent instanceof WebBlock) {
            $queryBuilder->where('web_block_has_external_link.web_block_id', $parent->id);
        }

        return $queryBuilder
            ->defaultSort('external_links.url')
            ->select(
                [
                    'external_links.id',
                    'external_links.url',
                    'external_links.number_websites_shown',
                    'external_links.number_webpages_shown',
                    'external_links.number_web_blocks_shown',
                    'external_links.number_websites_hidden',
                    'external_links.number_webpages_hidden',
                    'external_links.number_web_blocks_hidden',
                    'external_links.status',
                ]
            )
            ->allowedSorts(['url', 'number_websites_shown', 'number_webpages_shown', 'number_web_blocks_shown', 'number_websites_hidden', 'number_webpages_hidden', 'number_web_blocks_hidden', 'status'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Website|Webpage|WebBlock $parent, ?array $modelOperations = null, $prefix = null, $bucket = null): Closure
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
                        'title'       => __("No external links found"),
                        'description' => __('Nor any website exist 🤭'),
                    ],
                )
                ->column(key: 'url', label: __('url'), canBeHidden: false, sortable: true, searchable: false)
                ->column(key: 'number_websites_shown', label: __('websites shown'), canBeHidden: false, sortable: true, searchable: false)
                ->column(key: 'number_webpages_shown', label: __('webpages shown'), canBeHidden: false, sortable: true, searchable: false)
                ->column(key: 'number_web_blocks_shown', label: __('web blocks shown'), canBeHidden: false, sortable: true, searchable: false)
                ->column(key: 'number_websites_hidden', label: __('websites hidden'), canBeHidden: false, sortable: true, searchable: false)
                ->column(key: 'number_webpages_hidden', label: __('webpages hidden'), canBeHidden: false, sortable: true, searchable: false)
                ->column(key: 'number_web_blocks_hidden', label: __('web blocks hidden'), canBeHidden: false, sortable: true, searchable: false)
                ->column(key: 'status', label: __('status'), canBeHidden: false, sortable: true, searchable: false)
                ->defaultSort('url');
        };
    }

    public function jsonResponse(LengthAwarePaginator $externalLinks): AnonymousResourceCollection
    {
        return ExternalLinksResource::collection($externalLinks);
    }
}
