<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Traits\Dashboards\WithPerformanceDateResolution;
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
use App\Enums\Web\WebsiteConversionEvent\WebsiteConversionEventTypeEnum;
use App\Enums\Web\WebsiteVisitor\WebsiteVisitorChannelEnum;
use App\Exports\Web\SeoTableExport;
use App\Exports\Web\SeoWorkbookExport;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\SeoBacklinkSummary;
use App\Models\Web\Website;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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

    public const array TABLES = ['research', 'rankings', 'tracked_keywords', 'top_pages', 'conversions', 'missing_pages', 'referring_domains', 'backlinks', 'keyword_gap', 'search_queries', 'search_opportunities', 'content_suggestions'];

    private const int PAGE_SIZE = 2000;

    public function handle(Shop $shop, string $table, ActionRequest $request): BinaryFileResponse
    {
        $website = $shop->website;

        abort_unless($website || in_array($table, ['research', 'rankings', 'tracked_keywords'], true), 404);

        $fileName = now()->format('Y-m-d').'-seo-'.str_replace('_', '-', $table).'-'.$shop->slug.'.xlsx';

        if ($table === 'conversions') {
            return Excel::download(new SeoWorkbookExport($this->conversions($website, $request)), $fileName);
        }

        [$headings, $rows] = match ($table) {
            'research'          => $this->research($shop, $request),
            'rankings'          => $this->rankings($shop),
            'tracked_keywords'  => $this->trackedKeywords($shop),
            'top_pages'         => $this->topPages($website, $request),
            'missing_pages'     => $this->missingPages($website),
            'referring_domains' => $this->referringDomains($website),
            'backlinks'         => $this->backlinks($website),
            'keyword_gap'       => $this->keywordGap($shop, $request),
            'search_queries'    => $this->searchQueries($website, $request, false),
            'search_opportunities' => $this->searchQueries($website, $request, true),
            'content_suggestions' => $this->contentSuggestions($website),
        };

        return Excel::download(new SeoTableExport($headings, $rows), $fileName);
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
            ['Keyword', 'Country', 'Check', 'Position', 'Previous position', 'Last checked', 'Ranking page', 'Monthly searches', 'KD', 'Intent', 'Tags', 'Search Console position (28 days)', 'On the results page', 'In AI Overview', 'Alert', 'Competitors'],
            $rows->map(fn ($row) => [
                $row->keyword,
                $row->country_code,
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

    /**
     * The Performance tab as a workbook: the customers as on screen, then every product behind their
     * counts: each add to basket, and each product of the orders they checked out and submitted. A
     * product code on screen keeps only that product in every sheet.
     *
     * @return array<int, array{title: string, headings: array<int, string>, rows: array<int, array<int, mixed>>}>
     */
    private function conversions(Website $website, ActionRequest $request): array
    {
        $userSettings = $request->user()->settings;
        $interval     = DateIntervalEnum::tryFrom(Arr::get($userSettings, 'selected_interval', 'all')) ?? DateIntervalEnum::ALL;

        [$fromDate, $toDate] = $this->resolvePerformanceDates($interval, $userSettings);

        $prefix    = SeoDashboardTabsEnum::CONVERSIONS->value;
        $index     = IndexWebsiteConversionCustomers::make();
        $customers = $this->allRows($prefix, fn () => $index->handle($website, $fromDate, $toDate, $prefix));

        $matchingAssets = $index->matchingAssets($website, $index->productCode($prefix));
        $customerOrder  = $customers->pluck('id')->flip();
        $customerIds    = $customers->pluck('id')->all();

        $orderHeadings = fn (string $when) => ['Customer', 'Email', $when, 'Order', 'Order net amount', 'Currency', 'Product code', 'Product', 'Quantity', 'Line net amount'];

        return [
            [
                'title'    => 'Customers',
                'headings' => ['Customer', 'Email', 'Channel', 'Add to basket', 'Checkouts', 'Purchases', 'Revenue', 'Last activity'],
                'rows'     => $customers->map(fn ($row) => [
                    self::customerName($row),
                    $row->email,
                    self::channels($row->traffic_source_types),
                    (int) $row->add_to_baskets,
                    (int) $row->checkouts,
                    (int) $row->purchases,
                    (float) $row->revenue,
                    self::date($row->last_event_at),
                ])->all(),
            ],
            [
                'title'    => 'Add to basket',
                'headings' => ['Customer', 'Email', 'Added at', 'Product code', 'Product', 'Quantity', 'Page', 'Channel'],
                'rows'     => $this->basketEvents($website, $customerIds, $fromDate, $toDate, $matchingAssets)
                    ->sortBy(fn ($event) => [$customerOrder[$event->customer_id] ?? PHP_INT_MAX, -strtotime($event->created_at)])
                    ->map(fn ($event) => [
                        self::customerName($event),
                        $event->email,
                        self::dateTime($event->created_at),
                        $event->code,
                        $event->product_name,
                        (int) $event->quantity,
                        $event->page_path,
                        self::channels($event->traffic_source_type),
                    ])->values()->all(),
            ],
            [
                'title'    => 'Checkouts',
                'headings' => $orderHeadings('Checkout at'),
                'rows'     => $this->orderLines($website, WebsiteConversionEventTypeEnum::CHECKOUT, $customerIds, $customerOrder, $fromDate, $toDate, $matchingAssets),
            ],
            [
                'title'    => 'Purchases',
                'headings' => $orderHeadings('Purchased at'),
                'rows'     => $this->orderLines($website, WebsiteConversionEventTypeEnum::PURCHASE, $customerIds, $customerOrder, $fromDate, $toDate, $matchingAssets),
            ],
        ];
    }

    /**
     * @param  array<int, int>  $customerIds
     */
    private function basketEvents(Website $website, array $customerIds, ?string $fromDate, ?string $toDate, mixed $matchingAssets): Collection
    {
        $events = collect();

        foreach (array_chunk($customerIds, 1000) as $chunk) {
            $events->push(...DB::connection('aiku_no_sticky')->table('website_conversion_events as ce')
                ->join('website_visitors as v', 'v.id', '=', 'ce.website_visitor_id')
                ->join('web_users as wu', 'wu.id', '=', 'v.web_user_id')
                ->join('customers as c', 'c.id', '=', 'wu.customer_id')
                ->leftJoin('assets as a', 'a.id', '=', 'ce.product_id')
                ->where('ce.website_id', $website->id)
                ->where('ce.event_type', WebsiteConversionEventTypeEnum::ADD_TO_BASKET->value)
                ->whereIn('wu.customer_id', $chunk)
                ->when($fromDate, fn ($query) => $query->where('ce.event_date', '>=', Carbon::parse($fromDate)->toDateString()))
                ->when($toDate, fn ($query) => $query->where('ce.event_date', '<=', Carbon::parse($toDate)->toDateString()))
                ->when($matchingAssets, fn ($query) => $query->whereIn('ce.product_id', $matchingAssets))
                ->get([
                    'wu.customer_id',
                    'c.name',
                    'c.contact_name',
                    'c.email',
                    'ce.created_at',
                    'a.code',
                    'a.name as product_name',
                    'ce.quantity',
                    'ce.page_path',
                    'v.traffic_source_type',
                ])
                ->all());
        }

        return $events;
    }

    /**
     * One row per product of each order checked out or submitted; an order with no product left in it
     * keeps one row without a product, unless a product code is asked for.
     *
     * @param  array<int, int>  $customerIds
     * @param  Collection<int, int>  $customerOrder
     * @return array<int, array<int, mixed>>
     */
    private function orderLines(Website $website, WebsiteConversionEventTypeEnum $type, array $customerIds, Collection $customerOrder, ?string $fromDate, ?string $toDate, mixed $matchingAssets): array
    {
        $lines = collect();

        foreach (array_chunk($customerIds, 1000) as $chunk) {
            $lines->push(...DB::connection('aiku_no_sticky')->table('website_conversion_events as ce')
                ->join('orders as o', 'o.id', '=', 'ce.order_id')
                ->join('customers as c', 'c.id', '=', 'o.customer_id')
                ->leftJoin('currencies as cur', 'cur.id', '=', 'o.currency_id')
                ->{$matchingAssets ? 'join' : 'leftJoin'}('transactions as t', function ($join) use ($matchingAssets) {
                    $join->on('t.order_id', '=', 'o.id')
                        ->where('t.model_type', 'Product')
                        ->whereNull('t.deleted_at')
                        ->where('t.quantity_ordered', '>', 0);

                    if ($matchingAssets) {
                        $join->whereIn('t.asset_id', $matchingAssets);
                    }
                })
                ->leftJoin('assets as a', 'a.id', '=', 't.asset_id')
                ->where('ce.website_id', $website->id)
                ->where('ce.event_type', $type->value)
                ->whereIn('o.customer_id', $chunk)
                ->when($fromDate, fn ($query) => $query->where('ce.event_date', '>=', Carbon::parse($fromDate)->toDateString()))
                ->when($toDate, fn ($query) => $query->where('ce.event_date', '<=', Carbon::parse($toDate)->toDateString()))
                ->orderBy('t.id')
                ->get([
                    'o.customer_id',
                    'c.name',
                    'c.contact_name',
                    'c.email',
                    'ce.id as event_id',
                    'ce.created_at',
                    'o.reference',
                    DB::raw('COALESCE(ce.net_amount, o.net_amount) as order_net_amount'),
                    'cur.code as currency_code',
                    'a.code',
                    'a.name as product_name',
                    't.quantity_ordered',
                    't.net_amount',
                ])
                ->all());
        }

        return $lines
            ->sortBy(fn ($line) => [$customerOrder[$line->customer_id] ?? PHP_INT_MAX, -strtotime($line->created_at), $line->event_id])
            ->map(fn ($line) => [
                self::customerName($line),
                $line->email,
                self::dateTime($line->created_at),
                $line->reference,
                (float) $line->order_net_amount,
                $line->currency_code,
                $line->code,
                $line->product_name,
                $line->quantity_ordered !== null ? (float) $line->quantity_ordered : null,
                $line->net_amount !== null ? (float) $line->net_amount : null,
            ])
            ->values()
            ->all();
    }

    private static function customerName(object $row): ?string
    {
        return $row->name ?: ($row->contact_name ?: $row->email);
    }

    private static function channels(?string $trafficSourceTypes): string
    {
        return collect(explode(',', (string) $trafficSourceTypes))->filter()->map(fn (string $type) => WebsiteVisitorChannelEnum::typeLabel($type))->implode(', ');
    }

    private static function dateTime(mixed $value): ?string
    {
        return $value ? Carbon::parse($value)->format('Y-m-d H:i') : null;
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
