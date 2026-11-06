<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 06 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Crawl\UI;

use App\Enums\Web\Crawl\CrawlIssueTypeEnum;
use App\Enums\Web\Crawl\CrawlTabEnum;
use App\Models\Web\Crawl;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Every tab of a crawl with the number of rows behind each of its filters, for the tab bar and the
 * overview beside the table.
 */
class GetCrawlTabSummary
{
    use AsObject;

    /**
     * @return array<int, array{key: string, label: string, total: int, issues: int, filters: array<int, array{key: string, label: string, count: int, severity: string|null, description: string|null, how_to_fix: string|null}>}>
     */
    public function handle(Crawl $crawl): array
    {
        $pageCounts     = $this->pageCounts($crawl);
        $resourceCounts = $this->resourceCounts($crawl);
        $issueCounts    = DB::table('crawl_issues')
            ->where('crawl_id', $crawl->id)
            ->groupBy('type')
            ->selectRaw('type, count(*) as pages')
            ->pluck('pages', 'type')
            ->map(fn ($pages) => (int) $pages);

        $labels       = CrawlTabEnum::labels();
        $issueLabels  = CrawlIssueTypeEnum::labels();
        $descriptions = CrawlIssueTypeEnum::descriptions();
        $howToFix     = CrawlIssueTypeEnum::howToFix();

        return array_map(function (CrawlTabEnum $tab) use ($pageCounts, $resourceCounts, $issueCounts, $labels, $issueLabels, $descriptions, $howToFix) {
            $counts = $tab->source() === 'pages' ? $pageCounts : ($resourceCounts[$tab->source()] ?? []);

            $stateFilters = [];

            foreach ($tab->stateFilters() as $key => $label) {
                $stateFilters[] = [
                    'key'         => $key,
                    'label'       => $label,
                    'count'       => (int) ($counts[$key] ?? 0),
                    'severity'    => null,
                    'description' => null,
                    'how_to_fix'  => null,
                ];
            }

            $issueFilters = array_map(fn (CrawlIssueTypeEnum $type) => [
                'key'         => $type->value,
                'label'       => $issueLabels[$type->value],
                'count'       => $issueCounts->get($type->value, 0),
                'severity'    => $type->severity()->value,
                'description' => $descriptions[$type->value],
                'how_to_fix'  => $howToFix[$type->value],
            ], $tab->issueTypes());

            return [
                'key'     => $tab->value,
                'label'   => $labels[$tab->value],
                'total'   => (int) ($counts['all'] ?? 0),
                'issues'  => array_sum(array_column($issueFilters, 'count')),
                'filters' => [...$stateFilters, ...$issueFilters],
            ];
        }, CrawlTabEnum::cases());
    }

    /**
     * @return array<string, int>
     */
    private function pageCounts(Crawl $crawl): array
    {
        $counts = DB::table('crawl_pages')
            ->where('crawl_id', $crawl->id)
            ->selectRaw('count(*) as "all"')
            ->selectRaw('count(*) filter (where is_indexable) as indexable')
            ->selectRaw('count(*) filter (where not is_indexable) as non_indexable')
            ->selectRaw('count(*) filter (where status_code between 200 and 299) as success')
            ->selectRaw('count(*) filter (where status_code between 300 and 399) as redirection')
            ->selectRaw('count(*) filter (where is_in_sitemap) as in_sitemap')
            ->selectRaw('count(*) filter (where not is_in_sitemap) as not_in_sitemap')
            ->first();

        return array_map(intval(...), (array) $counts);
    }

    /**
     * @return array<string, array<string, int>>
     */
    private function resourceCounts(Crawl $crawl): array
    {
        $counts = DB::table('crawl_resources')
            ->where('crawl_id', $crawl->id)
            ->groupBy('kind')
            ->select('kind')
            ->selectRaw('count(*) as "all"')
            ->selectRaw('count(*) filter (where status_code between 200 and 299) as success')
            ->selectRaw('count(*) filter (where status_code between 300 and 399) as redirection')
            ->selectRaw('count(*) filter (where status_code between 400 and 499) as client_error')
            ->selectRaw('count(*) filter (where status_code between 500 and 599) as server_error')
            ->selectRaw('count(*) filter (where status_code is null) as no_response')
            ->selectRaw('count(*) filter (where bytes > ?) as over_size', [CrawlIssueTypeEnum::LARGE_IMAGE_KB * 1024])
            ->get()
            ->mapWithKeys(fn (object $row) => [$row->kind => array_map(intval(...), array_diff_key((array) $row, ['kind' => true]))])
            ->all();

        $counts['image']['missing_alt'] = GetCrawlTabRows::make()->query($crawl, CrawlTabEnum::IMAGES, 'missing_alt')->count();

        return $counts;
    }
}
