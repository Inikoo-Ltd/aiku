<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Exports\Web\Sheets;

use App\Enums\Web\Crawl\CrawlIssueTypeEnum;
use App\Models\Web\CrawlIssue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * One row per page and issue of an audit, the worst issues first, read in chunks so a large audit
 * does not have to fit in memory.
 */
class SiteAuditPagesSheet implements FromQuery, WithHeadings, WithMapping, WithTitle, ShouldAutoSize, WithStrictNullComparison
{
    /**
     * @var array<string, string>|null
     */
    private ?array $issueLabels = null;

    public function __construct(private readonly int $crawlId)
    {
    }

    /**
     * @return Builder<CrawlIssue>
     */
    public function query(): Builder
    {
        return CrawlIssue::query()
            ->join('crawl_pages', 'crawl_pages.id', '=', 'crawl_issues.crawl_page_id')
            ->leftJoin('webpages', 'webpages.id', '=', 'crawl_pages.webpage_id')
            ->where('crawl_issues.crawl_id', $this->crawlId)
            ->select([
                'crawl_issues.id',
                'crawl_issues.type',
                'crawl_issues.severity',
                'crawl_issues.details',
                'crawl_pages.url',
                'crawl_pages.status_code',
                'crawl_pages.inlinks',
                'crawl_pages.response_ms',
                'crawl_pages.title',
                'crawl_pages.is_in_sitemap',
                'webpages.code as webpage_code',
            ])
            ->orderByRaw("CASE crawl_issues.severity WHEN 'error' THEN 1 WHEN 'warning' THEN 2 ELSE 3 END")
            ->orderBy('crawl_issues.type')
            ->orderByDesc('crawl_pages.inlinks')
            ->orderBy('crawl_issues.id');
    }

    public function headings(): array
    {
        return ['Severity', 'Issue', 'URL', 'Webpage', 'Status code', 'Internal links in', 'Response (ms)', 'Title', 'In sitemap', 'Details'];
    }

    /**
     * @param  CrawlIssue  $row
     */
    public function map($row): array
    {
        return [
            $row->severity->value,
            ($this->issueLabels ??= CrawlIssueTypeEnum::labels())[$row->type->value],
            $row->url,
            $row->webpage_code,
            $row->status_code,
            (int) $row->inlinks,
            $row->response_ms,
            $row->title,
            $row->is_in_sitemap ? 'yes' : 'no',
            self::detailsText($row->details ?? []),
        ];
    }

    public function title(): string
    {
        return __('Pages with issues');
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function detailsText(array $details): string
    {
        return collect($details)
            ->map(fn (mixed $value, string $key) => str_replace('_', ' ', $key).': '.match (true) {
                is_bool($value)  => $value ? 'yes' : 'no',
                is_array($value) => collect($value)
                    ->map(fn (mixed $item) => is_array($item) ? implode(' ', Arr::flatten($item)) : (string) $item)
                    ->implode($key === 'chain' ? ' > ' : ', '),
                default          => (string) $value,
            })
            ->implode('; ');
    }
}
