<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Traits\Dashboards\WithPerformanceDateResolution;
use App\Actions\Web\Seo\UI\IndexSeoBacklinks;
use App\Actions\Web\Seo\UI\IndexSeoRankings;
use App\Actions\Web\Seo\UI\IndexSeoReferringDomains;
use App\Actions\Web\Seo\UI\IndexSeoTrackedKeywords;
use App\Actions\Web\Webpage\UI\IndexWebpagesPerformance;
use App\Actions\Web\WebsiteNotFoundPath\UI\IndexWebsiteNotFoundPaths;
use App\Enums\DateIntervals\DateIntervalEnum;
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

    public const array TABLES = ['rankings', 'tracked_keywords', 'top_pages', 'missing_pages', 'referring_domains', 'backlinks', 'keyword_gap'];

    private const int PAGE_SIZE = 2000;

    public function handle(Shop $shop, string $table, ActionRequest $request): BinaryFileResponse
    {
        $website = $shop->website;

        abort_unless($website || in_array($table, ['rankings', 'tracked_keywords'], true), 404);

        [$headings, $rows] = match ($table) {
            'rankings'          => $this->rankings($shop),
            'tracked_keywords'  => $this->trackedKeywords($shop),
            'top_pages'         => $this->topPages($website, $request),
            'missing_pages'     => $this->missingPages($website),
            'referring_domains' => $this->referringDomains($website),
            'backlinks'         => $this->backlinks($website),
            'keyword_gap'       => $this->keywordGap($shop, $request),
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
            request()->merge([$prefix.'_perPage' => self::PAGE_SIZE, $prefix.'Page' => $pageNumber]);

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

    private function rankings(Shop $shop): array
    {
        $rows = $this->allRows('rankings', fn () => IndexSeoRankings::make()->handle($shop, 'rankings'));

        return [
            ['Keyword', 'Country', 'Device', 'Check', 'Position', 'Previous position', 'Last checked', 'Ranking page', 'Monthly searches', 'Intent', 'Search Console position (28 days)', 'On the results page', 'In AI Overview', 'Alert', 'Competitors'],
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
                $row->intent,
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
        $rows = $this->allRows('tracked_keywords', fn () => IndexSeoTrackedKeywords::make()->handle($shop, 'tracked_keywords'));

        return [
            ['Keyword', 'Country', 'Language', 'Device', 'Check', 'Active', 'Monthly searches', 'Target webpage', 'Added'],
            $rows->map(fn ($row) => [
                $row->keyword,
                $row->country_code,
                $row->language_code,
                $row->device?->value,
                $row->frequency?->value,
                $row->is_active ? 'yes' : 'no',
                $row->avg_monthly_searches,
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
