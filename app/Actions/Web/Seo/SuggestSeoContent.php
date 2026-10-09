<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Enums\Web\Crawl\CrawlIssueTypeEnum;
use App\Enums\Web\Crawl\CrawlTypeEnum;
use App\Enums\Web\Seo\SeoContentSuggestionStateEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Website\WebsiteStateEnum;
use App\Models\Web\Crawl;
use App\Models\Web\SeoContentSuggestion;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Weekly, after the Sunday audits: for each live website, up to `PAGES_PER_WEBSITE` pages get a
 * suggested meta title and/or description. Pages with many Google impressions and few clicks come
 * first, then the title and description issues of the latest audit. A page with a pending
 * suggestion, or one decided in the last 90 days, is left alone for that field.
 */
class SuggestSeoContent
{
    use AsAction;

    public const int PAGES_PER_WEBSITE = 30;

    public const int LOW_CTR_DAYS = 28;

    public const int LOW_CTR_MIN_IMPRESSIONS = 500;

    public const int LOW_CTR_MAX_POSITION = 10;

    public const float LOW_CTR_MAX_CTR = 2.0;

    private const int DECIDED_DAYS = 90;

    /**
     * Audit issues in the order they are worked on, with the field each one is about.
     */
    private const array ISSUE_FIELDS = [
        'missing_meta_description'   => SeoContentSuggestion::FIELD_DESCRIPTION,
        'missing_title'              => SeoContentSuggestion::FIELD_TITLE,
        'duplicate_meta_description' => SeoContentSuggestion::FIELD_DESCRIPTION,
        'duplicate_title'            => SeoContentSuggestion::FIELD_TITLE,
        'meta_description_too_short' => SeoContentSuggestion::FIELD_DESCRIPTION,
        'meta_description_too_long'  => SeoContentSuggestion::FIELD_DESCRIPTION,
        'title_too_short'            => SeoContentSuggestion::FIELD_TITLE,
        'title_too_long'             => SeoContentSuggestion::FIELD_TITLE,
    ];

    public string $commandSignature = 'seo:suggest_content {website? : Website slug} {--limit= : Pages per website}';

    public string $commandDescription = 'Suggest meta titles and descriptions for the pages the audit or Search Console flag';

    public string $jobQueue = 'long-low-priority';

    public int $jobTimeout = 7200;

    public int $jobTries = 1;

    public function handle(?Website $website = null, int $limit = self::PAGES_PER_WEBSITE): int
    {
        $websites = Website::query()
            ->where('state', WebsiteStateEnum::LIVE)
            ->when($website, fn ($query) => $query->where('id', $website->id))
            ->get();

        $suggested = 0;

        foreach ($websites as $liveWebsite) {
            foreach ($this->candidates($liveWebsite)->take($limit) as $candidate) {
                $webpage = Webpage::find($candidate['webpage_id']);

                if (!$webpage) {
                    continue;
                }

                try {
                    $suggested += GenerateSeoContentSuggestions::run($webpage, $candidate['fields'], $candidate['reason'])->count();
                } catch (ValidationException) {
                    return $suggested;
                }
            }
        }

        return $suggested;
    }

    /**
     * @return Collection<int, array{webpage_id: int, fields: array<int, string>, reason: string}>
     */
    public function candidates(Website $website): Collection
    {
        $candidates = collect();

        foreach ($this->lowCtrWebpageIds($website) as $webpageId) {
            $candidates->put($webpageId, ['webpage_id' => $webpageId, 'fields' => [SeoContentSuggestion::FIELD_TITLE, SeoContentSuggestion::FIELD_DESCRIPTION], 'reason' => SeoContentSuggestion::REASON_LOW_CTR]);
        }

        foreach ($this->auditIssues($website) as $issue) {
            $field = self::ISSUE_FIELDS[$issue->type];

            if ($candidates->has($issue->webpage_id)) {
                continue;
            }

            if ($issue->type === CrawlIssueTypeEnum::TITLE_TOO_LONG->value && !$this->isPageTitleTooLong($issue->webpage_id)) {
                continue;
            }

            $candidates->put($issue->webpage_id, ['webpage_id' => $issue->webpage_id, 'fields' => [$field], 'reason' => $issue->type]);
        }

        $taken = SeoContentSuggestion::query()
            ->where('website_id', $website->id)
            ->where(fn ($query) => $query
                ->where('state', SeoContentSuggestionStateEnum::PENDING)
                ->orWhere('decided_at', '>=', now()->subDays(self::DECIDED_DAYS)))
            ->get(['webpage_id', 'field'])
            ->groupBy('webpage_id')
            ->map(fn ($suggestions) => $suggestions->pluck('field')->all());

        return $candidates
            ->map(fn (array $candidate) => [...$candidate, 'fields' => array_values(array_diff($candidate['fields'], $taken->get($candidate['webpage_id'], [])))])
            ->filter(fn (array $candidate) => $candidate['fields'] !== [])
            ->values();
    }

    /**
     * @return array<int, int>
     */
    private function lowCtrWebpageIds(Website $website): array
    {
        return DB::table('search_console_page_days')
            ->join('webpages', 'webpages.id', '=', 'search_console_page_days.webpage_id')
            ->where('search_console_page_days.website_id', $website->id)
            ->where('webpages.state', WebpageStateEnum::LIVE->value)
            ->where('search_console_page_days.date', '>=', now()->subDays(self::LOW_CTR_DAYS)->toDateString())
            ->groupBy('search_console_page_days.webpage_id')
            ->havingRaw('SUM(search_console_page_days.impressions) >= ?', [self::LOW_CTR_MIN_IMPRESSIONS])
            ->havingRaw('SUM(search_console_page_days.position * search_console_page_days.impressions) / NULLIF(SUM(search_console_page_days.impressions), 0) <= ?', [self::LOW_CTR_MAX_POSITION])
            ->havingRaw('SUM(search_console_page_days.clicks) * 100.0 / NULLIF(SUM(search_console_page_days.impressions), 0) < ?', [self::LOW_CTR_MAX_CTR])
            ->orderByRaw('SUM(search_console_page_days.impressions) DESC')
            ->pluck('search_console_page_days.webpage_id')
            ->all();
    }

    private function auditIssues(Website $website): Collection
    {
        $audit = Crawl::where('website_id', $website->id)
            ->where('type', CrawlTypeEnum::AUDIT)
            ->whereNotNull('health_score')
            ->latest('id')
            ->first();

        if (!$audit) {
            return collect();
        }

        $order = array_keys(self::ISSUE_FIELDS);

        return DB::table('crawl_issues')
            ->join('crawl_pages', 'crawl_pages.id', '=', 'crawl_issues.crawl_page_id')
            ->join('webpages', 'webpages.id', '=', 'crawl_pages.webpage_id')
            ->where('crawl_issues.crawl_id', $audit->id)
            ->whereIn('crawl_issues.type', $order)
            ->where('webpages.state', WebpageStateEnum::LIVE->value)
            ->get(['crawl_issues.type', 'crawl_pages.webpage_id'])
            ->sortBy(fn ($issue) => array_search($issue->type, $order, true))
            ->values();
    }

    /**
     * The website's own title text can push every title over the limit; only a page title that is
     * too long by itself is worth rewriting.
     */
    private function isPageTitleTooLong(int $webpageId): bool
    {
        $webpage = Webpage::find($webpageId);

        return $webpage && mb_strlen((string) $webpage->title) > GenerateSeoContentSuggestions::make()->titleMaxLength($webpage);
    }

    public function asCommand(Command $command): int
    {
        $website = $command->argument('website') ? Website::where('slug', $command->argument('website'))->firstOrFail() : null;

        $command->line($this->handle($website, (int) ($command->option('limit') ?: self::PAGES_PER_WEBSITE)).' suggestions written');

        return 0;
    }
}
