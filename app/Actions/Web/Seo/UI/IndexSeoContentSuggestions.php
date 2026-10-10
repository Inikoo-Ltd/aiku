<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Web\Crawl\UI\ShowSiteAudit;
use App\Actions\Web\Seo\GenerateSeoContentSuggestions;
use App\Enums\Web\Crawl\CrawlIssueTypeEnum;
use App\Enums\Web\Seo\SeoContentSuggestionStateEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\SeoContentSuggestion;
use App\Models\Web\Website;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexSeoContentSuggestions extends OrgAction
{
    use WithWebAuthorisation;

    public function handle(Website $website): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $value = '%'.addcslashes(strip_tags($value), '%_\\').'%';

            $query->where(fn ($query) => $query
                ->where('webpages.code', 'ilike', $value)
                ->orWhere('seo_content_suggestions.suggestion', 'ilike', $value));
        });

        return QueryBuilder::for(SeoContentSuggestion::class)
            ->where('seo_content_suggestions.website_id', $website->id)
            ->join('webpages', 'webpages.id', '=', 'seo_content_suggestions.webpage_id')
            ->leftJoin('users', 'users.id', '=', 'seo_content_suggestions.decided_by_user_id')
            ->defaultSort('-seo_content_suggestions.created_at')
            ->select([
                'seo_content_suggestions.id',
                'seo_content_suggestions.field',
                'seo_content_suggestions.current_value',
                'seo_content_suggestions.suggestion',
                'seo_content_suggestions.reason',
                'seo_content_suggestions.state',
                'seo_content_suggestions.created_at',
                'seo_content_suggestions.decided_at',
                'webpages.slug as webpage_slug',
                'webpages.code as webpage_code',
                'users.contact_name as decided_by',
            ])
            ->allowedSorts(['created_at'])
            ->allowedFilters([
                $globalSearch,
                AllowedFilter::callback('state', fn ($query, $value) => $query->where('seo_content_suggestions.state', SeoContentSuggestionStateEnum::tryFrom($value) ?? SeoContentSuggestionStateEnum::PENDING))->default(SeoContentSuggestionStateEnum::PENDING->value),
            ])
            ->withPaginator(null, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(): Closure
    {
        return function (InertiaTable $table) {
            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('suggestion'), __('suggestions')])
                ->withEmptyState([
                    'title'       => __('No suggestion here'),
                    'description' => __('Suggestions are written every Tuesday for the pages the latest audit flags and the pages with many Google impressions and few clicks.'),
                ])
                ->column(key: 'webpage_code', label: __('Webpage'), canBeHidden: false)
                ->column(key: 'suggestion', label: __('Suggestion'), canBeHidden: false)
                ->column(key: 'reason', label: __('Why'))
                ->column(key: 'created_at', label: __('Written'), sortable: true, align: 'right')
                ->defaultSort('-created_at');
        };
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        abort_unless($shop->website, 404);

        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop->website);
    }

    public function htmlResponse(LengthAwarePaginator $suggestions, ActionRequest $request): Response
    {
        $title   = __('Suggested titles and descriptions');
        $website = $this->shop->website;

        return Inertia::render(
            'Org/Web/SeoContentSuggestions',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => $title,
                'pageHead'    => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-magic'],
                        'title' => __('Site audit'),
                    ],
                    'title' => $title,
                ],
                'websiteSlug' => $website->slug,
                'canEdit'     => $this->canEdit,
                'counts'      => SeoContentSuggestion::where('website_id', $website->id)
                    ->toBase()
                    ->groupBy('state')
                    ->selectRaw('state, COUNT(*) AS suggestions')
                    ->pluck('suggestions', 'state'),
                'limits'      => [
                    'title'       => CrawlIssueTypeEnum::TITLE_MAX_LENGTH,
                    'description' => CrawlIssueTypeEnum::META_DESCRIPTION_MAX_LENGTH,
                ],
                'model'       => GenerateSeoContentSuggestions::MODEL,
                'data'        => JsonResource::collection($suggestions->through(fn (SeoContentSuggestion $suggestion) => [
                    'id'            => $suggestion->id,
                    'field'         => $suggestion->field,
                    'current_value' => $suggestion->current_value,
                    'suggestion'    => $suggestion->suggestion,
                    'reason'        => $suggestion->reason,
                    'state'         => $suggestion->state->value,
                    'created_at'    => $suggestion->created_at?->toDateString(),
                    'decided_at'    => $suggestion->decided_at?->toDateString(),
                    'decided_by'    => $suggestion->getAttribute('decided_by'),
                    'webpage_slug'  => $suggestion->getAttribute('webpage_slug'),
                    'webpage_code'  => $suggestion->getAttribute('webpage_code'),
                    'accept_route'  => ['name' => 'grp.models.seo_content_suggestion.accept', 'parameters' => [$suggestion->id]],
                    'dismiss_route' => ['name' => 'grp.models.seo_content_suggestion.dismiss', 'parameters' => [$suggestion->id]],
                ])),
            ]
        )->table($this->tableStructure());
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowSiteAudit::make()->getBreadcrumbs(Arr::only($routeParameters, ['organisation', 'shop'])),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.seo.site_audit.suggestions',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Suggested titles and descriptions'),
                    ],
                ],
            ]
        );
    }
}
