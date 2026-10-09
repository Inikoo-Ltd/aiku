<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Web\Seo\GetKeywordIdeas;
use App\Actions\Web\Seo\GetSeoRankingsOverview;
use App\Actions\Web\Seo\PostSerpTasks;
use App\Actions\Web\Website\UI\ShowSeoDashboard;
use App\Enums\UI\Web\SeoKeywordsTabsEnum;
use App\Enums\Web\Seo\SeoKeywordDeviceEnum;
use App\Enums\Web\Seo\SeoKeywordFrequencyEnum;
use App\Http\Resources\Web\SeoRankingResource;
use App\Http\Resources\Web\SeoTrackedKeywordResource;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Country;
use App\Models\Helpers\Language;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\SeoCompetitor;
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

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Shop
    {
        $this->initialisationFromShop($shop, $request)->withTab(SeoKeywordsTabsEnum::values());

        return $shop;
    }

    /**
     * @return array{seed: string, url: string, country_code: string, language_code: string}
     */
    private function researchQuery(Shop $shop, ActionRequest $request): array
    {
        return [
            'seed'          => Str::limit((string) $request->query('seed', ''), 1000, ''),
            'url'           => Str::limit((string) $request->query('url', ''), 2048, ''),
            'country_code'  => strtoupper((string) $request->query('country_code', $shop->country?->code ?? '')),
            'language_code' => strtolower((string) $request->query('language_code', $shop->language?->code ?? '')),
        ];
    }

    private function research(Shop $shop, array $query): ?array
    {
        $seedKeywords = array_values(array_filter(array_map('trim', preg_split('/[,\n]+/', $query['seed']))));

        if ($seedKeywords === [] && $query['url'] === '') {
            return null;
        }

        $keywordData = ['ideas' => [], 'error' => null];

        try {
            $keywordData = [...GetKeywordIdeas::run($shop, $seedKeywords, $query['url'] ?: null, $query['country_code'], $query['language_code']), 'error' => null];
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

    private function competitors(Shop $shop): array
    {
        return $shop->seoCompetitors()
            ->orderBy('domain')
            ->get()
            ->map(fn (SeoCompetitor $competitor) => [
                'id'           => $competitor->id,
                'domain'       => $competitor->domain,
                'label'        => $competitor->label,
                'delete_route' => [
                    'name'       => 'grp.models.seo_competitor.delete',
                    'parameters' => [$competitor->id],
                    'method'     => 'delete',
                ],
            ])
            ->all();
    }

    private function rankings(Shop $shop): array
    {
        return [
            ...GetSeoRankingsOverview::run($shop),
            'domain'         => $shop->website?->domain,
            'isConfigured'   => DataForSeoClient::make() !== null,
            'depths'         => [
                'weekly' => PostSerpTasks::WEEKLY_DEPTH,
                'daily'  => PostSerpTasks::DAILY_DEPTH,
            ],
            'runChecksRoute' => app()->environment('local') && $this->canEdit ? [
                'name'       => 'grp.models.shop.seo.rank_checks.store',
                'parameters' => [$shop->id],
            ] : null,
            'table'          => SeoRankingResource::collection(IndexSeoRankings::run($shop, SeoKeywordsTabsEnum::RANKINGS->value)),
        ];
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
                    'add_competitor' => [
                        'name'       => 'grp.models.shop.seo.competitor.store',
                        'parameters' => [$shop->id],
                        'method'     => 'post',
                    ],
                ],
                'options'     => [
                    'countries'   => Country::orderBy('name')->get(['code', 'name'])->map(fn (Country $country) => ['value' => $country->code, 'label' => $country->name])->all(),
                    'languages'   => Language::orderBy('name')->get(['code', 'name'])->map(fn (Language $language) => ['value' => $language->code, 'label' => $language->name])->all(),
                    'devices'     => collect(SeoKeywordDeviceEnum::labels())->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all(),
                    'frequencies' => collect(SeoKeywordFrequencyEnum::labels())->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all(),
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

                SeoKeywordsTabsEnum::COMPETITORS->value => $this->tabProp(
                    SeoKeywordsTabsEnum::COMPETITORS,
                    fn () => $this->competitors($shop)
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
