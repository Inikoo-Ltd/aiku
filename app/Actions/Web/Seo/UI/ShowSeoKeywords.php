<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Web\Seo\GenerateSeoContentBrief;
use App\Actions\Web\Seo\GetKeywordIdeas;
use App\Actions\Web\Seo\GetSeoRankingsOverview;
use App\Actions\Web\Seo\PostSerpTasks;
use App\Actions\Web\Website\UI\ShowSeoDashboard;
use App\Enums\UI\Web\SeoKeywordsTabsEnum;
use App\Enums\Web\Seo\SeoContentBriefStateEnum;
use App\Enums\Web\Seo\SeoKeywordFrequencyEnum;
use App\Enums\Web\Seo\SeoResearchScopeEnum;
use App\Http\Resources\Web\SeoRankingResource;
use App\Http\Resources\Web\SeoTrackedKeywordResource;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Country;
use App\Models\Helpers\Language;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\SeoContentBrief;
use App\Services\DataForSeo\DataForSeoClient;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowSeoKeywords extends OrgAction
{
    use WithWebAuthorisation;

    private const int SEARCH_CONSOLE_DAYS = 90;

    private const int SEARCH_CONSOLE_ROWS = 50;

    private const int CONTENT_BRIEFS = 50;

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Shop
    {
        $this->initialisationFromShop($shop, $request)->withTab(SeoKeywordsTabsEnum::values());

        return $shop;
    }

    /**
     * @return array{seed: string, url: string, scope: string, country_code: string, language_code: string}
     */
    public function researchQuery(Shop $shop, ActionRequest $request): array
    {
        $url = Str::limit((string) $request->query('url', ''), 2048, '');

        return [
            'seed'          => Str::limit((string) $request->query('seed', ''), 1000, ''),
            'url'           => $url,
            'scope'         => (SeoResearchScopeEnum::tryFrom((string) $request->query('scope')) ?? SeoResearchScopeEnum::guess($url))->value,
            'country_code'  => strtoupper((string) $request->query('country_code', $shop->country?->code ?? '')),
            'language_code' => strtolower((string) $request->query('language_code', $shop->language?->code ?? '')),
        ];
    }

    public function research(Shop $shop, array $query): ?array
    {
        $seedKeywords = array_values(array_filter(array_map('trim', preg_split('/[,\n]+/', $query['seed']))));

        if ($seedKeywords === [] && $query['url'] === '') {
            return null;
        }

        $keywordData = ['ideas' => [], 'error' => null];

        try {
            $keywordData = [...GetKeywordIdeas::run($shop, $seedKeywords, $query['url'] ?: null, $query['country_code'], $query['language_code'], SeoResearchScopeEnum::from($query['scope'])), 'error' => null];
        } catch (ValidationException $e) {
            $keywordData['error'] = Arr::first(Arr::flatten($e->errors()));
        }

        $trackedKeys = $shop->seoTrackedKeywords()
            ->where('country_code', $query['country_code'])
            ->where('language_code', $query['language_code'])
            ->pluck('keyword')
            ->flip();

        return [
            'keyword_data'   => [
                ...$keywordData,
                'ideas' => array_map(fn (array $idea) => [...$idea, 'is_tracked' => $trackedKeys->has($idea['keyword'])], $keywordData['ideas']),
            ],
            'search_console' => $this->searchConsoleQueries($shop, $seedKeywords, $trackedKeys->all()),
        ];
    }

    private function searchConsoleQueries(Shop $shop, array $seedKeywords, array $trackedKeys): array
    {
        if (!$shop->website || $seedKeywords === []) {
            return [];
        }

        return DB::connection('aiku_no_sticky')->table('search_console_page_queries')
            ->where('website_id', $shop->website->id)
            ->where('date', '>=', now()->subDays(self::SEARCH_CONSOLE_DAYS)->toDateString())
            ->where(function ($query) use ($seedKeywords) {
                foreach ($seedKeywords as $seedKeyword) {
                    $query->orWhere('query', 'ilike', '%'.addcslashes(Str::lower($seedKeyword), '%_\\').'%');
                }
            })
            ->groupBy('query')
            ->select('query')
            ->selectRaw('SUM(clicks) as clicks')
            ->selectRaw('SUM(impressions) as impressions')
            ->selectRaw('ROUND(SUM(position * impressions) / NULLIF(SUM(impressions), 0), 1) as position')
            ->orderByDesc('impressions')
            ->limit(self::SEARCH_CONSOLE_ROWS)
            ->get()
            ->map(fn ($row) => [
                'keyword'     => $row->query,
                'clicks'      => (int) $row->clicks,
                'impressions' => (int) $row->impressions,
                'position'    => $row->position !== null ? (float) $row->position : null,
                'is_tracked'  => array_key_exists($row->query, $trackedKeys),
            ])
            ->all();
    }

    private function rankings(Shop $shop): array
    {
        return [
            ...GetSeoRankingsOverview::run($shop),
            'domain'       => $shop->website?->domain,
            'isConfigured' => DataForSeoClient::make() !== null,
            'depths'       => [
                'weekly' => PostSerpTasks::WEEKLY_DEPTH,
                'daily'  => PostSerpTasks::DAILY_DEPTH,
            ],
            'costPerPage'  => PostSerpTasks::COST_PER_PAGE,
            'checkNow'     => $this->checkNow($shop),
            'table'        => SeoRankingResource::collection(IndexSeoRankings::run($shop, SeoKeywordsTabsEnum::RANKINGS->value)),
        ];
    }

    /**
     * @return array{keywords: int, cost: float, route: array{name: string, parameters: array<int, int>}}|null
     */
    private function checkNow(Shop $shop): ?array
    {
        if (!$this->canEdit) {
            return null;
        }

        return [
            ...PostSerpTasks::make()->requestable($shop),
            'route' => [
                'name'       => 'grp.models.shop.seo.keyword_checks.store',
                'parameters' => [$shop->id],
            ],
        ];
    }

    private function contentBriefs(Shop $shop): array
    {
        return [
            'results'      => GenerateSeoContentBrief::RESULTS,
            'isConfigured' => DataForSeoClient::make() !== null,
            'storeRoute'   => $this->canEdit ? [
                'name'       => 'grp.models.shop.seo.content_brief.store',
                'parameters' => [$shop->id],
            ] : null,
            'briefs'       => $shop->seoContentBriefs()
                ->with('requestedBy:id,contact_name,username')
                ->latest('created_at')
                ->limit(self::CONTENT_BRIEFS)
                ->get()
                ->map(fn (SeoContentBrief $brief) => [
                    'id'            => $brief->id,
                    'keyword'       => $brief->keyword,
                    'country_code'  => $brief->country_code,
                    'language_code' => $brief->language_code,
                    'state'         => $brief->state === SeoContentBriefStateEnum::PENDING && !$brief->isBeingWritten() ? SeoContentBriefStateEnum::FAILED->value : $brief->state->value,
                    'error'         => $brief->state === SeoContentBriefStateEnum::PENDING && !$brief->isBeingWritten() ? __('The brief was not finished. Write it again.') : $brief->error,
                    'title'         => Arr::get($brief->brief ?? [], 'title'),
                    'pages_read'    => Arr::get($brief->stats ?? [], 'pages_read'),
                    'median_words'  => Arr::get($brief->stats ?? [], 'median_words'),
                    'requested_by'  => $brief->requestedBy?->contact_name ?? $brief->requestedBy?->username,
                    'created_at'    => $brief->created_at?->toIso8601String(),
                    'show_route'    => ['name' => 'grp.json.seo_content_brief.show', 'parameters' => [$brief->id]],
                    'retry_route'   => $this->canEdit ? ['name' => 'grp.models.seo_content_brief.retry', 'parameters' => [$brief->id]] : null,
                    'delete_route'  => $this->canEdit ? ['name' => 'grp.models.seo_content_brief.delete', 'parameters' => [$brief->id]] : null,
                ])
                ->all(),
        ];
    }

    /**
     * The shop's tags with how many keywords carry each, and the result-page features seen in the
     * latest checks with how many keywords show them and how many show our website in them.
     *
     * @return array{tags: array<int, array<string, mixed>>, serp_features: array<int, array{value: string, keywords: int, ours: int}>}
     */
    private function keywordFilterOptions(Shop $shop): array
    {
        $tags = $shop->seoKeywordTags()
            ->leftJoin('seo_keyword_tag_keyword', 'seo_keyword_tag_keyword.tag_id', '=', 'seo_keyword_tags.id')
            ->groupBy('seo_keyword_tags.id', 'seo_keyword_tags.name')
            ->orderBy('seo_keyword_tags.name')
            ->select('seo_keyword_tags.id', 'seo_keyword_tags.name')
            ->selectRaw('COUNT(seo_keyword_tag_keyword.keyword) as keywords')
            ->get()
            ->map(fn ($tag) => [
                'id'           => $tag->id,
                'name'         => $tag->name,
                'keywords'     => (int) $tag->keywords,
                'update_route' => ['name' => 'grp.models.seo_keyword_tag.update', 'parameters' => [$tag->id], 'method' => 'patch'],
                'delete_route' => ['name' => 'grp.models.seo_keyword_tag.delete', 'parameters' => [$tag->id], 'method' => 'delete'],
            ])
            ->all();

        $serpFeatures = DB::table('seo_tracked_keywords')
            ->crossJoin(DB::raw("jsonb_array_elements_text(COALESCE(seo_tracked_keywords.serp_features, '[]'::jsonb)) as feature"))
            ->where('seo_tracked_keywords.shop_id', $shop->id)
            ->where('seo_tracked_keywords.is_active', true)
            ->groupBy('feature')
            ->select('feature')
            ->selectRaw('COUNT(DISTINCT seo_tracked_keywords.keyword) as keywords')
            ->selectRaw("COUNT(DISTINCT seo_tracked_keywords.keyword) FILTER (WHERE jsonb_exists(COALESCE(seo_tracked_keywords.serp_features_owned, '[]'::jsonb), feature)) as ours")
            ->orderByDesc('keywords')
            ->get()
            ->map(fn ($row) => ['value' => $row->feature, 'keywords' => (int) $row->keywords, 'ours' => (int) $row->ours])
            ->all();

        return ['tags' => $tags, 'serp_features' => $serpFeatures];
    }

    private function tabProp(SeoKeywordsTabsEnum $tab, Closure $resolver): mixed
    {
        return $this->tab === $tab->value ? $resolver : Inertia::optional($resolver);
    }

    public function htmlResponse(Shop $shop, ActionRequest $request): Response
    {
        $title = __('Keywords');
        $query = $this->researchQuery($shop, $request);

        return Inertia::render(
            'Org/Web/SeoKeywords',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => $title,
                'pageHead'    => [
                    'icon'  => [
                        'icon'  => ['fal', 'fa-key'],
                        'title' => $title,
                    ],
                    'title' => $title,
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => SeoKeywordsTabsEnum::navigation(),
                ],
                'canEdit'     => $this->canEdit,
                'routes'      => [
                    'track'          => [
                        'name'       => 'grp.models.shop.seo.tracked_keyword.store',
                        'parameters' => [$shop->id],
                        'method'     => 'post',
                    ],
                    'bulkTrack'      => [
                        'name'       => 'grp.models.shop.seo.tracked_keywords.bulk_store',
                        'parameters' => [$shop->id],
                        'method'     => 'post',
                    ],
                    'bulkDelete'     => [
                        'name'       => 'grp.models.shop.seo.tracked_keywords.delete',
                        'parameters' => [$shop->id],
                        'method'     => 'delete',
                    ],
                    'storeTag'       => [
                        'name'       => 'grp.models.shop.seo.keyword_tag.store',
                        'parameters' => [$shop->id],
                        'method'     => 'post',
                    ],
                    'assignTags'     => [
                        'name'       => 'grp.models.shop.seo.keyword_tags.assign',
                        'parameters' => [$shop->id],
                        'method'     => 'post',
                    ],
                ],
                'keywordFilters' => fn () => $this->keywordFilterOptions($shop),
                'options'     => [
                    'countries'   => Country::orderBy('name')->get(['code', 'name'])->map(fn (Country $country) => ['value' => $country->code, 'label' => $country->name])->all(),
                    'languages'   => Language::orderBy('name')->get(['code', 'name'])->map(fn (Language $language) => ['value' => $language->code, 'label' => $language->name])->all(),
                    'frequencies' => collect(SeoKeywordFrequencyEnum::labels())->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all(),
                    'scopes'      => collect(SeoResearchScopeEnum::labels())->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all(),
                ],
                'query'       => $query,

                SeoKeywordsTabsEnum::RESEARCH->value => $this->tabProp(
                    SeoKeywordsTabsEnum::RESEARCH,
                    fn () => $this->research($shop, $query)
                ),

                SeoKeywordsTabsEnum::TRACKED_KEYWORDS->value => $this->tabProp(
                    SeoKeywordsTabsEnum::TRACKED_KEYWORDS,
                    fn () => SeoTrackedKeywordResource::collection(IndexSeoTrackedKeywords::run($shop, SeoKeywordsTabsEnum::TRACKED_KEYWORDS->value))
                ),

                SeoKeywordsTabsEnum::RANKINGS->value => $this->tabProp(
                    SeoKeywordsTabsEnum::RANKINGS,
                    fn () => $this->rankings($shop)
                ),

                SeoKeywordsTabsEnum::CONTENT_BRIEFS->value => $this->tabProp(
                    SeoKeywordsTabsEnum::CONTENT_BRIEFS,
                    fn () => $this->contentBriefs($shop)
                ),
            ]
        )->table(IndexSeoTrackedKeywords::make()->tableStructure(prefix: SeoKeywordsTabsEnum::TRACKED_KEYWORDS->value))
            ->table(IndexSeoRankings::make()->tableStructure(prefix: SeoKeywordsTabsEnum::RANKINGS->value));
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        $shopParameters = Arr::only($routeParameters, ['organisation', 'shop']);

        return array_merge(
            ShowSeoDashboard::make()->getBreadcrumbs($shopParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.shops.show.seo.keywords.show',
                            'parameters' => $shopParameters,
                        ],
                        'label' => __('Keywords'),
                    ],
                ],
            ]
        );
    }
}
