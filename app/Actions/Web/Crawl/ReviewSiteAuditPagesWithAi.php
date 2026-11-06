<?php

/*
 * Created: Fri, 06 Nov 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Crawl;

use App\Actions\Helpers\AI\Traits\WithAIGateway;
use App\Models\Web\Crawl;
use App\Models\Web\CrawlPage;
use App\Models\Web\SeoApiRequest;
use App\Services\SiteAudit\AuditedHtml;
use App\Services\SeoApi\SeoApiBudget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * After a completed audit, a strong model reads the pages that matter most, the home page and the
 * pages with the most Google clicks in the last 28 days, the way an SEO consultant would: what the
 * page is for, whether its title, description, headings and text serve the searches it gets, and
 * what to change first. Each review is kept on the audit's page row; paid from the SEO API budget.
 */
class ReviewSiteAuditPagesWithAi
{
    use AsAction;
    use WithAIGateway;

    public const string MODEL = 'anthropic/claude-opus-5.5';

    public const int PAGES_REVIEWED = 20;

    private const int TEXT_LENGTH = 8000;

    private const int SEARCH_QUERIES = 15;

    public string $jobQueue = 'long-low-priority';

    public int $jobTimeout = 1800;

    public function handle(Crawl $crawl): int
    {
        if (!$this->usesOpenRouter()) {
            return 0;
        }

        $reviewed = 0;

        foreach ($this->pagesToReview($crawl) as $page) {
            if (SeoApiBudget::isReached()) {
                break;
            }

            $review = $this->review($crawl, $page);

            if ($review) {
                $page->update(['ai_review' => $review]);
                $reviewed++;
            }
        }

        return $reviewed;
    }

    /**
     * @return Collection<int, CrawlPage>
     */
    public function pagesToReview(Crawl $crawl): Collection
    {
        $clicksByWebpage = DB::table('search_console_page_days')
            ->where('website_id', $crawl->website_id)
            ->where('date', '>=', now()->subDays(28)->toDateString())
            ->whereNotNull('webpage_id')
            ->groupBy('webpage_id')
            ->orderByRaw('SUM(clicks) DESC')
            ->limit(self::PAGES_REVIEWED * 2)
            ->pluck(DB::raw('SUM(clicks)'), 'webpage_id');

        $pages = CrawlPage::where('crawl_id', $crawl->id)
            ->where('status_code', 200)
            ->where('is_indexable', true)
            ->where(fn ($query) => $query->where('link_depth', 0)->orWhereIn('webpage_id', $clicksByWebpage->keys()))
            ->get();

        return $pages
            ->sortByDesc(fn (CrawlPage $page) => $page->link_depth === 0 ? PHP_INT_MAX : (int) $clicksByWebpage->get($page->webpage_id, 0))
            ->unique('webpage_id')
            ->take(self::PAGES_REVIEWED)
            ->values();
    }

    /**
     * @return array{score: int, summary: string, findings: array<int, array{priority: string, area: string, finding: string, fix: string}>, model: string, reviewed_at: string}|null
     */
    private function review(Crawl $crawl, CrawlPage $page): ?array
    {
        $startedAt = hrtime(true);

        try {
            $html     = Http::withHeaders(['User-Agent' => AuditWebsite::USER_AGENT, AuditWebsite::AUDIT_HEADER => (string) $crawl->id])->connectTimeout(10)->timeout(30)->get($page->url)->throw()->body();
            $response = $this->aiRequest()
                ->timeout(180)
                ->post('chat/completions', [
                    'model'           => self::MODEL,
                    'messages'        => [
                        ['role' => 'system', 'content' => $this->instructions()],
                        ['role' => 'user', 'content' => $this->pageContext($page, $html)],
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'max_tokens'      => 3000,
                ])
                ->throw();
        } catch (Throwable $e) {
            $this->log($crawl, false, null, $startedAt, $e->getMessage());

            return null;
        }

        $content = (string) $response->json('choices.0.message.content');
        $review  = preg_match('/\{.*\}/s', $content, $match) ? json_decode($match[0], true) : null;
        $isValid = is_array($review) && is_numeric($review['score'] ?? null) && is_string($review['summary'] ?? null) && is_array($review['findings'] ?? null);

        $this->log($crawl, $isValid, $this->aiUsageCost($response->json('usage') ?? []), $startedAt, $isValid ? null : 'No usable JSON in the answer: '.Str::limit($content, 300));

        if (!$isValid) {
            return null;
        }

        return [
            'score'       => max(0, min(100, (int) $review['score'])),
            'summary'     => Str::limit(trim($review['summary']), 1000),
            'findings'    => collect($review['findings'])
                ->filter(fn ($finding) => is_array($finding) && filled($finding['finding'] ?? null))
                ->take(10)
                ->map(fn (array $finding) => [
                    'priority' => in_array($finding['priority'] ?? null, ['high', 'medium', 'low'], true) ? $finding['priority'] : 'medium',
                    'area'     => Str::limit((string) ($finding['area'] ?? ''), 60, ''),
                    'finding'  => Str::limit((string) $finding['finding'], 500),
                    'fix'      => Str::limit((string) ($finding['fix'] ?? ''), 500),
                ])
                ->values()
                ->all(),
            'model'       => self::MODEL,
            'reviewed_at' => now()->toIso8601String(),
        ];
    }

    private function instructions(): string
    {
        return <<<'TEXT'
You are a senior e-commerce SEO consultant reviewing one page of a wholesale gift shop's storefront.
Judge it as Google and a buyer arriving from Google would: is it clear what the page offers, do the
title, meta description, h1, headings and text match the searches it already gets, is there enough
useful, specific text, are internal links helping, does anything look broken, thin, duplicated or
misleading. Ignore prices and stock. Only state what the page content given shows.

Answer only with JSON:
{"score": 0-100, "summary": "two sentences on the page's biggest strength and its biggest problem",
 "findings": [{"priority": "high|medium|low", "area": "title|description|headings|content|links|structure|other",
   "finding": "what is wrong, quoting the page", "fix": "the change to make, concrete enough for a web editor"}]}
At most 8 findings, most important first. Write in English.
TEXT;
    }

    private function pageContext(CrawlPage $page, string $html): string
    {
        $queries = DB::table('search_console_page_queries')
            ->where('webpage_id', $page->webpage_id)
            ->where('date', '>=', now()->subDays(28)->toDateString())
            ->groupBy('query')
            ->orderByRaw('SUM(impressions) DESC')
            ->limit(self::SEARCH_QUERIES)
            ->selectRaw('query, SUM(clicks) AS clicks, SUM(impressions) AS impressions, ROUND(AVG(position::numeric), 1) AS position')
            ->get()
            ->map(fn ($query) => "- $query->query: $query->clicks clicks, $query->impressions impressions, position $query->position")
            ->implode("\n");

        $issues = DB::table('crawl_issues')->where('crawl_page_id', $page->id)->pluck('type')->implode(', ');

        return implode("\n", [
            "URL: $page->url",
            'Title: '.($page->title ?? '(none)'),
            'Meta description: '.($page->meta_description ?? '(none)'),
            'H1: '.($page->h1 ?? '(none)'),
            "Words: $page->word_count, internal links in: $page->inlinks, clicks from home: ".($page->link_depth ?? 'only in the sitemap'),
            'Issues the crawler found: '.($issues ?: 'none'),
            '',
            'Google searches it appeared for in the last 28 days:',
            $queries ?: '(no Search Console data)',
            '',
            'Visible text of the page:',
            Str::limit(AuditedHtml::visibleText($html), self::TEXT_LENGTH),
        ]);
    }

    private function log(Crawl $crawl, bool $isSuccess, ?float $cost, int $startedAt, ?string $error = null): void
    {
        SeoApiRequest::create([
            'provider'    => $this->usesOpenRouter() ? 'openrouter' : 'openai',
            'endpoint'    => 'site_audit_ai_review',
            'website_id'  => $crawl->website_id,
            'is_success'  => $isSuccess,
            'rows'        => $isSuccess ? 1 : 0,
            'duration_ms' => intdiv(hrtime(true) - $startedAt, 1_000_000),
            'cost'        => $cost,
            'error'       => $error ? Str::limit($error, 2000) : null,
        ]);
    }
}
