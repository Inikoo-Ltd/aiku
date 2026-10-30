<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Traits\Dashboards\WithPerformanceDateResolution;
use App\Actions\Web\Crawl\UI\IndexSiteAuditIssuePages;
use App\Actions\Web\SearchConsole\UI\IndexSearchConsoleQueries;
use App\Actions\Web\Seo\UI\IndexSeoBacklinks;
use App\Actions\Web\Seo\UI\IndexSeoContentSuggestions;
use App\Actions\Web\Seo\UI\IndexSeoRankings;
use App\Actions\Web\Seo\UI\IndexSeoReferringDomains;
use App\Actions\Web\Seo\UI\IndexSeoTrackedKeywords;
use App\Actions\Web\Seo\UI\ShowSeoKeywords;
use App\Actions\Web\Webpage\UI\IndexWebpagesPerformance;
use App\Actions\Web\WebsiteConversionEvent\UI\IndexWebsiteConversionCustomers;
use App\Actions\Web\WebsiteNotFoundPath\UI\IndexWebsiteNotFoundPaths;
use App\Enums\DateIntervals\DateIntervalEnum;
use App\Enums\UI\Web\SeoDashboardTabsEnum;
use App\Enums\Web\Crawl\CrawlIssueTypeEnum;
use App\Enums\Web\WebsiteVisitor\WebsiteVisitorChannelEnum;
use App\Exports\Web\Sheets\SiteAuditPagesSheet;
use App\Exports\Web\SeoTableExport;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\SeoBacklinkSummary;
use App\Models\Web\Website;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Any SEO table of a shop as an Excel file, with the filters and the sort it has on screen: the same
 * index action is read in pages of `PAGE_SIZE` (above the screen's limit, for this request only), up
 * to `MAX_ROWS` rows.
 */
class ExportSeoTable extends OrgAction
{
    use WithPerformanceDateResolution;
    use WithWebAuthorisation;

    public const int MAX_ROWS = 20000;

    public const array TABLES = ['research', 'rankings', 'tracked_keywords', 'top_pages', 'conversions', 'missing_pages', 'referring_domains', 'backlinks', 'keyword_gap', 'search_queries', 'search_opportunities', 'site_audit_issue_pages', 'content_suggestions'];

    private const int PAGE_SIZE = 2000;

    public function handle(Shop $shop, string $table, ActionRequest $request): BinaryFileResponse
    {
        $website = $shop->website;

        abort_unless($website || in_array($table, ['research', 'rankings', 'tracked_keywords'], true), 404);

        [$headings, $rows] = match ($table) {
            'research'          => $this->research($shop, $request),
            'rankings'          => $this->rankings($shop),
            'tracked_keywords'  => $this->trackedKeywords($shop),
            'top_pages'         => $this->topPages($website, $request),
            'conversions'       => $this->conversions($website, $request),
            'missing_pages'     => $this->missingPages($website),
            'referring_domains' => $this->referringDomains($website),
            'backlinks'         => $this->backlinks($website),
            'keyword_gap'       => $this->keywordGap($shop, $request),
            'search_queries'    => $this->searchQueries($website, $request, false),
            'search_opportunities' => $this->searchQueries($website, $request, true),
            'site_audit_issue_pages' => $this->siteAuditIssuePages($website, $request),
            'content_suggestions' => $this->contentSuggestions($website),
        };

        return Excel::download(new SeoTableExport($headings, $rows), now()->format('Y-m-d').'-seo-'.str_replace('_', '-', $table).'-'.$shop->slug.'.xlsx');
    }

    /**
     * @param  Closure(): LengthAwarePaginator  $page
     */
    private function allRows(string $prefix, Closure $page): Collection
    {
        $rows       = collect();
        $pageNumber = 1;

        config(['ui.table.max_records_per_page' => self::PAGE_SIZE]);

        do {
            request()->merge($prefix === ''
                ? ['perPage' => self::PAGE_SIZE, 'page' => $pageNumber]
                : [$prefix.'_perPage' => self::PAGE_SIZE, $prefix.'Page' => $pageNumber]);

            $paginator = $page();
            $rows->push(...$paginator->items());
            $pageNumber++;
        } while ($pageNumber <= $paginator->lastPage() && $rows->count() < self::MAX_ROWS);

        return $rows->take(self::MAX_ROWS);
    }

    private static function date(mixed $value): ?string
    {
        return $value ? Carbon::parse($value)->toDateString() : null;
    }

    /**
     * The keyword ideas of the search on screen, repeated from the cache the search filled, so the
     * export costs no DataForSEO credit within 24 hours of it; the intent filter is applied on
     * screen, so the button sends it along.
     */
    private function research(Shop $shop, ActionRequest $request): array
    {
        $research = ShowSeoKeywords::make()->research($shop, ShowSeoKeywords::make()->researchQuery($shop, $request));
        $intent   = $request->query('intent');

        abort_if(!$research || $research['keyword_data']['error'], 404);

        return [
            ['Keyword', 'Monthly searches', 'Trend (last 12 months)', 'Competition', 'CPC', 'Low top of page bid', 'High top of page bid', 'KD', 'Intent', 'Secondary intents', 'SERP features', 'SERP updated', 'Tracked'],
            collect($research['keyword_data']['ideas'])
                ->filter(fn (array $idea) => !$intent || $idea['intent'] === $intent)
                ->map(fn (array $idea) => [
                    $idea['keyword'],
                    $idea['avg_monthly_searches'],
                    collect($idea['monthly_searches'])->take(-12)->pluck('searches')->implode(', '),
                    $idea['competition'],
                    $idea['cpc'],
                    $idea['low_top_of_page_bid'],
                    $idea['high_top_of_page_bid'],
                    $idea['keyword_difficulty'],
                    $idea['intent'],
                    implode(', ', $idea['secondary_intents']),
                    implode(', ', $idea['serp_features']),
                    $idea['serp_updated_at'],
                    $idea['is_tracked'] ? 'yes' : 'no',
                ])
                ->values()
                ->all(),
        ];
    }

    private function rankings(Shop $shop): array
    {
        $rows = $this->allRows('rankings', fn () => IndexSeoRankings::make()->variants($shop, 'rankings'));

        return [
            ['Keyword', 'Country', 'Device', 'Check', 'Position', 'Previous position', 'Last checked', 'Ranking page', 'Monthly searches', 'KD', 'Intent', 'Tags', 'Search Console position (28 days)', 'On the results page', 'In AI Overview', 'Alert', 'Competitors'],
            $rows->map(fn ($row) => [
                $row->keyword,
                $row->country_code,
                $row->device?->value,
                $row->frequency?->value,
                $row->position,
                $row->previous_position,
                self::date($row->last_checked_at),
                $row->ranking_url,
                $row->avg_monthly_searches,
                $row->keyword_difficulty,
                $row->intent,
                collect($row->tags ?? [])->pluck('name')->implode(', '),
                $row->search_console_position,
                implode(', ', $row->serp_features ?? []),
                $row->in_ai_overview ? 'yes' : 'no',
                $row->alert,
                collect($row->competitor_positions ?? [])->filter(fn ($competitor) => $competitor['position'] !== null)->map(fn ($competitor) => $competitor['domain'].': '.$competitor['position'])->implode('; '),
            ])->all(),
        ];
    }

    private function trackedKeywords(Shop $shop): array
    {
        $rows = $this->allRows('tracked_keywords', fn () => IndexSeoTrackedKeywords::make()->markets($shop, 'tracked_keywords'));

        return [
            ['Keyword', 'Country', 'Language', 'Check', 'Active', 'Monthly searches', 'Tags', 'Target webpage', 'Added'],
            $rows->map(fn ($row) => [
                $row->keyword,
                $row->country_code,
                $row->language_code,
                $row->frequency?->value,
                $row->is_active ? 'yes' : 'no',
                $row->avg_monthly_searches,
                collect($row->tags ?? [])->pluck('name')->implode(', '),
                $row->target_webpage_code,
                self::date($row->created_at),
            ])->all(),
        ];
    }

    private function topPages(Website $website, ActionRequest $request): array
    {
        $userSettings = $request->user()->settings;
        $interval     = DateIntervalEnum::tryFrom(Arr::get($userSettings, 'selected_interval', 'all')) ?? DateIntervalEnum::ALL;

        [$fromDate, $toDate] = $this->resolvePerformanceDates($interval, $userSettings);

        $rows = $this->allRows('webpages', fn () => IndexWebpagesPerformance::make()->handle($website, $fromDate, $toDate, 'webpages'));

        return [
            ['Code', 'Title', 'URL', 'Visitors', 'Previous visitors', 'Page views', 'Previous page views', 'Avg. time on page (s)', 'Conversion %', 'Search clicks', 'Previous search clicks', 'Impressions', 'Previous impressions', 'Position', 'Previous position', 'Queries', 'Referring domains', 'Backlinks', 'AI prompts citing (28 days)'],
            $rows->map(fn ($row) => [
                $row->code,
                $row->title,
                $row->canonical_url ?? $row->url,
                (int) $row->visitors,
                $row->previous_visitors,
                (int) $row->page_views,
                $row->previous_page_views,
                (int) $row->avg_time_on_page,
                (float) $row->conversion_rate,
                (int) $row->search_clicks,
                $row->previous_search_clicks,
                (int) $row->search_impressions,
                $row->previous_search_impressions,
                $row->search_position,
                $row->previous_search_position,
                (int) $row->search_queries,
                (int) $row->referring_domains,
                (int) $row->backlinks,
                (int) $row->ai_prompts,
            ])->all(),
        ];
    }

    private function conversions(Website $website, ActionRequest $request): array
    {
        $userSettings = $request->user()->settings;
        $interval     = DateIntervalEnum::tryFrom(Arr::get($userSettings, 'selected_interval', 'all')) ?? DateIntervalEnum::ALL;

        [$fromDate, $toDate] = $this->resolvePerformanceDates($interval, $userSettings);

        $prefix = SeoDashboardTabsEnum::CONVERSIONS->value;
        $rows   = $this->allRows($prefix, fn () => IndexWebsiteConversionCustomers::make()->handle($website, $fromDate, $toDate, $prefix));

        return [
            ['Customer', 'Channel', 'Add to basket', 'Checkouts', 'Purchases', 'Revenue', 'Last activity'],
            $rows->map(fn ($row) => [
                $row->name ?: ($row->contact_name ?: $row->email),
                collect(explode(',', (string) $row->traffic_source_types))->filter()->map(fn (string $type) => WebsiteVisitorChannelEnum::typeLabel($type))->implode(', '),
                (int) $row->add_to_baskets,
                (int) $row->checkouts,
                (int) $row->purchases,
                (float) $row->revenue,
                self::date($row->last_event_at),
            ])->all(),
        ];
    }

    private function missingPages(Website $website): array
    {
        $rows = $this->allRows('missing_pages', fn () => IndexWebsiteNotFoundPaths::make()->handle($website, 'missing_pages'));

        return [
            ['Path', 'Hits', 'Backlinks', 'Last seen', 'First seen', 'Last came from', 'State'],
            $rows->map(fn ($row) => [
                $row->path,
                (int) $row->hits,
                (int) $row->backlinks,
                self::date($row->last_seen_at),
                self::date($row->first_seen_at),
                $row->last_referrer,
                $row->is_ignored ? 'ignored' : ($row->is_fixed ? 'redirected' : 'open'),
            ])->all(),
        ];
    }

    private function referringDomains(Website $website): array
    {
        $domain   = StoreSerpResult::normaliseDomain($website->domain);
        $runs     = SeoBacklinkSummary::where('domain', $domain)->orderByDesc('date')->limit(2)->pluck('date');
        $newSince = $runs->count() === 2 ? Carbon::parse($runs->first())->startOfDay() : null;

        $rows = $this->allRows('referring_domains', fn () => IndexSeoReferringDomains::make()->handle($domain, $newSince, 'referring_domains'));

        return [
            ['Referring domain', 'Rank', 'Backlinks', 'First seen', 'Our website', 'Lost'],
            $rows->map(fn ($row) => [
                $row->referring_domain,
                $row->rank,
                (int) $row->backlinks,
                self::date($row->first_seen),
                $row->is_own_website ? 'yes' : 'no',
                self::date($row->lost_at),
            ])->all(),
        ];
    }

    private function backlinks(Website $website): array
    {
        $rows = $this->allRows('backlinks', fn () => IndexSeoBacklinks::make()->handle($website, 'backlinks'));

        return [
            ['Linking page', 'Title', 'Linking domain', 'Domain rank', 'Our website', 'Anchor', 'Type', 'Dofollow', 'Our page', 'Our webpage', 'Broken', 'Status code', 'First seen', 'Last seen', 'Lost'],
            $rows->map(fn ($row) => [
                $row->source_url,
                $row->source_title,
                $row->source_domain,
                $row->domain_rank,
                $row->is_own_website ? 'yes' : 'no',
                $row->anchor,
                $row->link_type,
                $row->is_dofollow ? 'yes' : 'no',
                $row->target_url,
                $row->target_webpage_code,
                $row->is_broken ? 'yes' : 'no',
                $row->target_status_code,
                self::date($row->first_seen),
                self::date($row->last_seen),
                self::date($row->lost_at),
            ])->all(),
        ];
    }

    private function searchQueries(Website $website, ActionRequest $request, bool $lowCtrOnly): array
    {
        $userSettings = $request->user()->settings;
        $interval     = DateIntervalEnum::tryFrom(Arr::get($userSettings, 'selected_interval', 'all')) ?? DateIntervalEnum::ALL;

        [$fromDate, $toDate] = $this->resolvePerformanceDates($interval, $userSettings);

        $prefix = $lowCtrOnly ? SeoDashboardTabsEnum::SEARCH_OPPORTUNITIES->value : SeoDashboardTabsEnum::SEARCH_QUERIES->value;
        $rows   = $this->allRows($prefix, fn () => IndexSearchConsoleQueries::make()->handle($website, $fromDate, $toDate, $prefix, $lowCtrOnly));

        return [
            ['Query', 'Clicks', 'Impressions', 'CTR %', 'Position', 'Pages', 'Pages shown (most clicks first)'],
            $rows->map(fn ($row) => [
                $row->query,
                (int) $row->clicks,
                (int) $row->impressions,
                (float) $row->ctr,
                $row->position !== null ? (float) $row->position : null,
                (int) $row->pages,
                implode('; ', $row->page_urls ?? []),
            ])->all(),
        ];
    }

    private function siteAuditIssuePages(Website $website, ActionRequest $request): array
    {
        $issueType = CrawlIssueTypeEnum::tryFrom((string) $request->query('issue_type')) ?? abort(404);
        $audit     = IndexSiteAuditIssuePages::make()->latestAudit($website) ?? abort(404);

        $rows = $this->allRows('', fn () => IndexSiteAuditIssuePages::make()->handle($audit, $issueType));

        return [
            ['URL', 'Webpage', 'Status code', 'Internal links in', 'Response (ms)', 'Title', 'In sitemap', 'Details'],
            $rows->map(fn ($row) => [
                $row->url,
                $row->webpage_code,
                $row->status_code,
                (int) $row->inlinks,
                $row->response_ms,
                $row->title,
                $row->is_in_sitemap ? 'yes' : 'no',
                SiteAuditPagesSheet::detailsText($row->details ?? []),
            ])->all(),
        ];
    }

    private function contentSuggestions(Website $website): array
    {
        $rows = $this->allRows('', fn () => IndexSeoContentSuggestions::make()->handle($website));

        return [
            ['Webpage', 'Field', 'Current value', 'Suggestion', 'Why', 'State', 'Written', 'Decided', 'Decided by'],
            $rows->map(fn ($row) => [
                $row->webpage_code,
                $row->field,
                $row->current_value,
                $row->suggestion,
                $row->reason,
                $row->state->value,
                self::date($row->created_at),
                self::date($row->decided_at),
                $row->decided_by,
            ])->all(),
        ];
    }

    /**
     * The keyword gap is filtered on screen, not on the server, so the button sends the group, the
     * intent and the best position along with the domains.
     */
    private function keywordGap(Shop $shop, ActionRequest $request): array
    {
        $domains = array_values(array_filter(array_map('trim', explode(',', (string) $request->query('gap_domains', '')))));
        abort_if($domains === [], 404);

        $gap          = GetKeywordGap::run($shop, $domains);
        $group        = (string) $request->query('group', 'missing');
        $intent       = $request->query('intent');
        $bestPosition = $request->query('best') ? (int) $request->query('best') : null;

        $rows = collect($gap['rows'])
            ->filter(fn (array $row) => in_array($group, $row['groups'], true))
            ->filter(fn (array $row) => !$intent || $row['intent'] === $intent)
            ->filter(fn (array $row) => !$bestPosition || collect($row['positions'])->filter()->min() <= $bestPosition)
            ->take(self::MAX_ROWS);

        return [
            ['Keyword', 'Intent', 'Monthly searches', 'Difficulty', ...$gap['domains'], 'Groups'],
            $rows->map(fn (array $row) => [
                $row['keyword'],
                $row['intent'],
                $row['search_volume'],
                $row['keyword_difficulty'],
                ...array_map(fn ($domain) => $row['positions'][$domain], $gap['domains']),
                implode(', ', $row['groups']),
            ])->values()->all(),
        ];
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(Organisation $organisation, Shop $shop, string $table, ActionRequest $request): BinaryFileResponse
    {
        abort_unless(in_array($table, self::TABLES, true), 404);

        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $table, $request);
    }
}
