<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Models\Catalogue\Shop;
use App\Models\Helpers\Language;
use App\Models\Web\SeoAiAnswer;
use App\Models\Web\SeoAiMention;
use App\Models\Web\SeoAiPrompt;
use App\Models\Web\SeoCompetitor;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * What the AI visibility page shows for one shop. Rates are over the answers of the last `DAYS` days
 * (about four weekly runs per prompt), because ChatGPT changes its answer between runs and one answer
 * says little. The trend is the weekly mention rate of the last `WEEKS` weeks.
 */
class GetSeoAiVisibility
{
    use AsObject;

    public const int DAYS = 28;

    public const int WEEKS = 12;

    private const int TOP_DOMAINS = 15;

    public function overview(Shop $shop): array
    {
        $competitors = $shop->seoCompetitors()->orderBy('domain')->get();
        $since       = today()->subDays(self::DAYS - 1);
        $answers     = $this->answers($shop, today()->subWeeks(self::WEEKS)->startOfWeek());
        $recent      = $answers->filter(fn (SeoAiAnswer $answer) => $answer->date->gte($since));

        return [
            'days'    => self::DAYS,
            'prompts' => [
                'active'   => $shop->seoAiPrompts()->where('is_active', true)->count(),
                'answered' => $recent->pluck('prompt_id')->unique()->count(),
            ],
            'brands'  => [
                $this->brandRow($recent, null, $shop->website?->domain ?? '', implode(', ', StoreSeoAiAnswer::brandNames($shop))),
                ...$competitors->map(fn (SeoCompetitor $competitor) => $this->brandRow($recent, $competitor->id, $competitor->domain, $competitor->label))->all(),
            ],
            'trend'   => $this->trend($answers, $competitors),
            'llm_mentions' => $this->llmMentions($shop, $competitors),
        ];
    }

    public function prompts(Shop $shop): array
    {
        $since       = today()->subDays(self::DAYS - 1);
        $competitors = $shop->seoCompetitors->keyBy('id');
        $prompts     = $shop->seoAiPrompts()->orderByDesc('is_active')->orderBy('prompt')->get();

        $answers = SeoAiAnswer::query()
            ->whereIn('prompt_id', $prompts->pluck('id'))
            ->where('date', '>=', $since)
            ->orderByDesc('date')
            ->get(['id', 'prompt_id', 'date', 'is_mentioned', 'is_cited'])
            ->groupBy('prompt_id');

        $latest = SeoAiAnswer::query()
            ->whereIn('id', DB::table('seo_ai_answers')->whereIn('prompt_id', $prompts->pluck('id'))->selectRaw('MAX(id)')->groupBy('prompt_id'))
            ->with(['citations' => fn ($query) => $query->orderBy('position')])
            ->get()
            ->keyBy('prompt_id');

        return $prompts->map(function (SeoAiPrompt $prompt) use ($answers, $latest, $competitors) {
            $runs   = $answers->get($prompt->id, collect());
            $answer = $latest->get($prompt->id);

            return [
                'id'            => $prompt->id,
                'prompt'        => $prompt->prompt,
                'country_code'  => $prompt->country_code,
                'language_code' => $prompt->language_code,
                'is_active'     => $prompt->is_active,
                'is_pending'    => $prompt->queued_at !== null,
                'runs'          => $runs->count(),
                'mentioned'     => $runs->where('is_mentioned', true)->count(),
                'cited'         => $runs->where('is_cited', true)->count(),
                'latest'        => $answer ? [
                    'date'             => $answer->date->toDateString(),
                    'model'            => $answer->model,
                    'is_mentioned'     => $answer->is_mentioned,
                    'brand_position'   => $answer->brand_position,
                    'is_cited'         => $answer->is_cited,
                    'brands'           => $answer->brands ?? [],
                    'competitors'      => collect($answer->competitors ?? [])
                        ->filter(fn (array $competitor) => $competitor['is_mentioned'] || $competitor['is_cited'])
                        ->map(fn (array $competitor) => [
                            'name'         => $competitors->get($competitor['competitor_id'])?->label ?: $competitors->get($competitor['competitor_id'])?->domain,
                            'is_mentioned' => $competitor['is_mentioned'],
                            'is_cited'     => $competitor['is_cited'],
                        ])
                        ->filter(fn (array $competitor) => $competitor['name'])
                        ->values()
                        ->all(),
                    'our_citations'    => $answer->citations->whereNotNull('website_id')->map(fn ($citation) => ['url' => $citation->url, 'title' => $citation->title])->values()->all(),
                    'citations'        => $answer->citations->map(fn ($citation) => ['url' => $citation->url, 'domain' => $citation->domain, 'title' => $citation->title, 'is_ours' => $citation->website_id !== null])->values()->all(),
                    'answer'           => $answer->answer,
                    'check_url'        => $answer->check_url,
                ] : null,
                'update_route'  => [
                    'name'       => 'grp.models.seo_ai_prompt.update',
                    'parameters' => [$prompt->id],
                    'method'     => 'patch',
                ],
                'delete_route'  => [
                    'name'       => 'grp.models.seo_ai_prompt.delete',
                    'parameters' => [$prompt->id],
                    'method'     => 'delete',
                ],
            ];
        })->all();
    }

    public function citedPages(Shop $shop): array
    {
        $since   = today()->subDays(self::DAYS - 1)->toDateString();
        $website = $shop->website;

        $base = DB::table('seo_ai_citations')
            ->join('seo_ai_answers', 'seo_ai_answers.id', '=', 'seo_ai_citations.answer_id')
            ->join('seo_ai_prompts', 'seo_ai_prompts.id', '=', 'seo_ai_answers.prompt_id')
            ->where('seo_ai_prompts.shop_id', $shop->id)
            ->where('seo_ai_answers.date', '>=', $since);

        $pages = $website ? (clone $base)
            ->leftJoin('webpages', 'webpages.id', '=', 'seo_ai_citations.webpage_id')
            ->where('seo_ai_citations.website_id', $website->id)
            ->groupBy('seo_ai_citations.url', 'webpages.id', 'webpages.code', 'webpages.slug')
            ->select('seo_ai_citations.url', 'webpages.id as webpage_id', 'webpages.code as webpage_code', 'webpages.slug as webpage_slug')
            ->selectRaw('COUNT(DISTINCT seo_ai_answers.prompt_id) AS prompts')
            ->selectRaw('COUNT(*) AS citations')
            ->selectRaw('MAX(seo_ai_answers.date) AS last_cited')
            ->selectRaw('MIN(seo_ai_citations.position) AS best_position')
            ->orderByDesc('prompts')
            ->orderByDesc('citations')
            ->get()
            ->map(fn ($page) => [
                'url'           => $page->url,
                'webpage_code'  => $page->webpage_code,
                'webpage_route' => $page->webpage_slug ? [
                    'name'       => 'grp.org.shops.show.web.webpages.show',
                    'parameters' => [$shop->organisation->slug, $shop->slug, $website->slug, $page->webpage_slug],
                ] : null,
                'prompts'       => (int) $page->prompts,
                'citations'     => (int) $page->citations,
                'best_position' => (int) $page->best_position,
                'last_cited'    => $page->last_cited,
            ])
            ->all() : [];

        $competitors = $shop->seoCompetitors->keyBy('id');

        $domains = (clone $base)
            ->groupBy('seo_ai_citations.domain')
            ->select('seo_ai_citations.domain')
            ->selectRaw('COUNT(DISTINCT seo_ai_answers.prompt_id) AS prompts')
            ->selectRaw('COUNT(*) AS citations')
            ->selectRaw('MAX(seo_ai_citations.competitor_id) AS competitor_id')
            ->selectRaw('BOOL_OR(seo_ai_citations.website_id IS NOT NULL) AS is_ours')
            ->orderByDesc('prompts')
            ->orderByDesc('citations')
            ->limit(self::TOP_DOMAINS)
            ->get()
            ->map(fn ($domain) => [
                'domain'     => $domain->domain,
                'prompts'    => (int) $domain->prompts,
                'citations'  => (int) $domain->citations,
                'competitor' => $domain->competitor_id ? ($competitors->get($domain->competitor_id)?->label ?: $competitors->get($domain->competitor_id)?->domain) : null,
                'is_ours'    => (bool) $domain->is_ours,
            ])
            ->all();

        return [
            'days'    => self::DAYS,
            'pages'   => $pages,
            'domains' => $domains,
        ];
    }

    /**
     * @return Collection<int, SeoAiAnswer>
     */
    private function answers(Shop $shop, Carbon $since): Collection
    {
        return SeoAiAnswer::query()
            ->whereIn('prompt_id', $shop->seoAiPrompts()->select('id'))
            ->where('date', '>=', $since)
            ->get(['id', 'prompt_id', 'date', 'is_mentioned', 'is_cited', 'brand_position', 'competitors']);
    }

    /**
     * @param  Collection<int, SeoAiAnswer>  $answers
     */
    private function brandRow(Collection $answers, ?int $competitorId, string $domain, ?string $label): array
    {
        $results = $answers->map(fn (SeoAiAnswer $answer) => $competitorId === null
            ? ['is_mentioned' => $answer->is_mentioned, 'is_cited' => $answer->is_cited, 'brand_position' => $answer->brand_position]
            : Arr::first($answer->competitors ?? [], fn (array $competitor) => $competitor['competitor_id'] === $competitorId) ?? ['is_mentioned' => false, 'is_cited' => false, 'brand_position' => null]);

        $count     = $results->count();
        $positions = $results->pluck('brand_position')->filter();

        return [
            'competitor_id'  => $competitorId,
            'is_ours'        => $competitorId === null,
            'domain'         => $domain,
            'label'          => $label,
            'answers'        => $count,
            'mentioned'      => $results->where('is_mentioned', true)->count(),
            'cited'          => $results->where('is_cited', true)->count(),
            'mention_rate'   => $count ? round($results->where('is_mentioned', true)->count() / $count * 100, 1) : null,
            'citation_rate'  => $count ? round($results->where('is_cited', true)->count() / $count * 100, 1) : null,
            'brand_position' => $positions->isNotEmpty() ? round($positions->avg(), 1) : null,
        ];
    }

    /**
     * @param  Collection<int, SeoAiAnswer>  $answers
     * @param  Collection<int, SeoCompetitor>  $competitors
     */
    private function trend(Collection $answers, Collection $competitors): array
    {
        return $answers
            ->groupBy(fn (SeoAiAnswer $answer) => $answer->date->copy()->startOfWeek()->toDateString())
            ->sortKeys()
            ->map(fn (Collection $week, string $start) => [
                'week'        => $start,
                'answers'     => $week->count(),
                'ours'        => round($week->where('is_mentioned', true)->count() / $week->count() * 100, 1),
                'competitors' => $competitors->mapWithKeys(fn (SeoCompetitor $competitor) => [
                    $competitor->id => round($week->filter(fn (SeoAiAnswer $answer) => (bool) Arr::get(Arr::first($answer->competitors ?? [], fn (array $row) => $row['competitor_id'] === $competitor->id), 'is_mentioned'))->count() / $week->count() * 100, 1),
                ])->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * The latest LLM Mentions fetch per platform, with each domain's share of all the mentions
     * fetched and its count in the fetch before.
     *
     * @param  Collection<int, SeoCompetitor>  $competitors
     */
    private function llmMentions(Shop $shop, Collection $competitors): array
    {
        $labels = $competitors->keyBy('id');

        return SeoAiMention::where('shop_id', $shop->id)
            ->orderByDesc('date')
            ->get()
            ->groupBy('platform')
            ->map(function (Collection $rows, string $platform) use ($shop, $labels) {
                $dates    = $rows->pluck('date')->map(fn (Carbon $date) => $date->toDateString())->unique()->values();
                $latest   = $rows->filter(fn (SeoAiMention $row) => $row->date->toDateString() === $dates->first());
                $previous = $dates->count() > 1 ? $rows->filter(fn (SeoAiMention $row) => $row->date->toDateString() === $dates->get(1))->keyBy('domain') : collect();
                $total    = $latest->sum('mentions');
                $first    = $latest->first();

                return [
                    'platform'      => $platform,
                    'date'          => $dates->first(),
                    'previous_date' => $dates->get(1),
                    'country'       => $first->location_code === FetchSeoAiMentions::US_LOCATION_CODE ? __('United States') : $shop->country?->name,
                    'language'      => Language::where('code', $first->language_code)->value('name') ?? $first->language_code,
                    'rows'          => $latest
                        ->sortByDesc('mentions')
                        ->map(fn (SeoAiMention $row) => [
                            'domain'            => $row->domain,
                            'label'             => $row->competitor_id ? $labels->get($row->competitor_id)?->label : $shop->website?->name,
                            'is_ours'           => $row->competitor_id === null,
                            'mentions'          => $row->mentions,
                            'previous_mentions' => $previous->get($row->domain)?->mentions,
                            'ai_search_volume'  => $row->ai_search_volume,
                            'share'             => $total ? round($row->mentions / $total * 100, 1) : null,
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->sortKeys()
            ->values()
            ->all();
    }
}
