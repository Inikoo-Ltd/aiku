<?php

/*
 * Author: Rifqi <rifqitaufiqurrohman1@gmail.com>
 * Created: Fri, 16 Oct 2026
 * Copyright (c) 2026
 */

namespace App\Actions\Catalogue\Product\UI;

use App\Actions\Catalogue\Product\CheckProductWebpage;
use App\Enums\Web\Crawl\CrawlIssueTypeEnum;
use App\Enums\Web\Crawl\CrawlStateEnum;
use App\Enums\Web\Crawl\CrawlTypeEnum;
use App\Models\Web\CrawlIssue;
use App\Models\Web\CrawlPage;
use App\Models\Web\Webpage;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * What the latest finished site audit found on a shop's product webpage: the checks only a crawl
 * can answer, null until the page has been crawled, and its issues grouped the way the webmaster
 * fixes them.
 */
class GetProductWebpageAudit
{
    use AsObject;

    /**
     * crawl_page is the audited or checked page with its issues, for the page score; it stays server side.
     *
     * @return array{last_crawl_at: ?string, links_check: array{webpage_id: int, is_checking: bool}, crawl_page: ?CrawlPage, checks: array<string, array{label: string, is_passed: ?bool}>, issue_count: int, issue_groups: array<int, array{key: string, label: string, intro: string, issues: array<int, array{id: int, title: string, description: ?string, severity: string, found: ?string}>}>}
     */
    public function handle(Webpage $webpage): array
    {
        $auditPage   = $this->latestCrawlPages(collect([$webpage]))->get($webpage->id);
        $crawlPage   = CheckProductWebpage::checkedPage($webpage, $auditPage);
        $checkedBrokenLinks = CheckProductWebpage::checkedBrokenLinkIssues($webpage, $auditPage);
        $brokenLinks        = $checkedBrokenLinks ?? ($auditPage ? CrawlIssue::whereIn('type', [CrawlIssueTypeEnum::HTTP_4XX, CrawlIssueTypeEnum::HTTP_5XX])
            ->where('crawl_id', $auditPage->crawl_id)
            ->whereJsonContains('details->linked_from', $auditPage->url)
            ->with('crawlPage')
            ->get() : collect());

        $issueGroups = $crawlPage ? $this->issueGroups($crawlPage, $brokenLinks) : [];

        return [
            'last_crawl_at' => $crawlPage?->crawl->end_at?->toIso8601String(),
            'links_check'   => CheckProductWebpage::linksCheck($webpage),
            'crawl_page'    => $crawlPage,
            'checks'        => $this->checks($webpage, $crawlPage, $checkedBrokenLinks !== null || $auditPage ? $brokenLinks->isNotEmpty() : null),
            'issue_count'   => collect($issueGroups)->sum(fn (array $group) => count($group['issues'])),
            'issue_groups'  => $issueGroups,
        ];
    }

    /**
     * The audit checks of many webpages at once, for a table of products: one query for their latest
     * crawled pages and one for the broken pages of those audits.
     *
     * @param Collection<int, Webpage> $webpages
     *
     * @return array<int, array<string, array{label: string, is_passed: ?bool}>> keyed by webpage id
     */
    public function checksForWebpages(Collection $webpages): array
    {
        if ($webpages->isEmpty()) {
            return [];
        }

        $crawlPages = $this->latestCrawlPages($webpages);

        $brokenPageLinks = CrawlIssue::whereIn('crawl_id', $crawlPages->pluck('crawl_id')->unique())
            ->whereIn('type', [CrawlIssueTypeEnum::HTTP_4XX, CrawlIssueTypeEnum::HTTP_5XX])
            ->get(['crawl_id', 'details'])
            ->groupBy('crawl_id')
            ->map(fn (Collection $issues) => $issues->flatMap(fn (CrawlIssue $issue) => $issue->details['linked_from'] ?? [])->unique()->flip());

        return $webpages->mapWithKeys(function (Webpage $webpage) use ($crawlPages, $brokenPageLinks) {
            /** @var CrawlPage|null $auditPage */
            $auditPage          = $crawlPages->get($webpage->id);
            $checkedBrokenLinks = CheckProductWebpage::checkedBrokenLinks($webpage, $auditPage);

            return [
                $webpage->id => $this->checks(
                    $webpage,
                    CheckProductWebpage::checkedPage($webpage, $auditPage),
                    $checkedBrokenLinks !== null ? $checkedBrokenLinks !== [] : ($auditPage ? $brokenPageLinks->get($auditPage->crawl_id)?->has($auditPage->url) ?? false : null)
                ),
            ];
        })->all();
    }

    /**
     * The page each webpage had in the latest finished site audit that crawled it.
     *
     * @param Collection<int, Webpage> $webpages
     *
     * @return Collection<int, CrawlPage> keyed by webpage id
     */
    private function latestCrawlPages(Collection $webpages): Collection
    {
        return CrawlPage::whereIn('webpage_id', $webpages->pluck('id'))
            ->whereHas('crawl', fn ($query) => $query->where('type', CrawlTypeEnum::AUDIT)->where('state', CrawlStateEnum::FINISH))
            ->with(['crawl', 'issues'])
            ->orderByDesc('crawl_id')
            ->orderByRaw('status_code = 200 desc nulls last')
            ->get()
            ->unique('webpage_id')
            ->keyBy('webpage_id');
    }

    /**
     * Null while the page has not been crawled. Structured data comes only from "Crawl page", which
     * checks the rendered page like the Google Rich Results Test.
     *
     * @return array<string, array{label: string, is_passed: ?bool}>
     */
    private function checks(Webpage $webpage, ?CrawlPage $crawlPage, ?bool $hasBrokenLinks): array
    {
        $issueTypes = $crawlPage?->issues->map(fn (CrawlIssue $issue) => $issue->type);
        $isClearOf  = fn (array $types): ?bool => $issueTypes ? !$issueTypes->contains(fn (CrawlIssueTypeEnum $type) => in_array($type, $types, true)) : null;

        $results = [
            'h1'              => $isClearOf([CrawlIssueTypeEnum::MISSING_H1, CrawlIssueTypeEnum::MULTIPLE_H1]),
            'alt_image'       => $isClearOf([CrawlIssueTypeEnum::IMAGES_WITHOUT_ALT]),
            'broken_links'    => $hasBrokenLinks === null ? null : !$hasBrokenLinks,
            'page_speed'      => $isClearOf([CrawlIssueTypeEnum::SLOW_RESPONSE]),
            'structured_data' => CheckProductWebpage::cached($webpage)['structured_data']['is_valid'] ?? null,
            'canonical_url'   => filled($webpage->canonical_url) && $isClearOf([CrawlIssueTypeEnum::MISSING_CANONICAL, CrawlIssueTypeEnum::CANONICAL_TO_BROKEN]) !== false,
            'sitemap'         => $isClearOf([CrawlIssueTypeEnum::NOT_IN_SITEMAP, CrawlIssueTypeEnum::NOINDEX_IN_SITEMAP]),
        ];

        return collect($this->labels())->map(fn (string $label, string $key) => ['label' => $label, 'is_passed' => $results[$key]])->all();
    }

    /**
     * @return array<string, string>
     */
    public function labels(): array
    {
        return [
            'h1'              => __('H1'),
            'alt_image'       => __('Alt image'),
            'broken_links'    => __('Broken links'),
            'page_speed'      => __('Page speed'),
            'structured_data' => __('Structured data'),
            'canonical_url'   => __('Canonical URL'),
            'sitemap'         => __('Sitemap'),
        ];
    }

    /**
     * @param Collection<int, CrawlIssue> $brokenLinks
     */
    private function issueGroups(CrawlPage $crawlPage, Collection $brokenLinks): array
    {
        $groups = [
            'h1'           => [__('H1 heading'), __('The page should have exactly one H1 heading. Extra H1s usually come from the page template.'), [CrawlIssueTypeEnum::MISSING_H1, CrawlIssueTypeEnum::MULTIPLE_H1]],
            'broken_links' => [__('Broken links'), __('Links on this page that lead to a page answering with an error.'), []],
            'alt_text'     => [__('Missing alt text'), __('Alt text is stored with each image of the product.'), [CrawlIssueTypeEnum::IMAGES_WITHOUT_ALT]],
            'page_speed'   => [__('Page speed'), __('Measured when the page was crawled. Fixes are made in the template or the image files.'), [CrawlIssueTypeEnum::SLOW_RESPONSE]],
            'other'        => [__('Other issues'), __('Everything else the site audit found on this page.'), []],
        ];

        $pageIssues   = $crawlPage->issues->sortBy(fn (CrawlIssue $issue) => $issue->severity->rank());
        $groupedTypes = collect($groups)->pluck(2)->flatten()->all();

        return collect($groups)
            ->map(fn (array $group, string $key) => [
                'key'    => $key,
                'label'  => $group[0],
                'intro'  => $group[1],
                'issues' => (match ($key) {
                    'broken_links' => $brokenLinks,
                    'other'        => $pageIssues->reject(fn (CrawlIssue $issue) => in_array($issue->type, $groupedTypes, true)),
                    default        => $pageIssues->filter(fn (CrawlIssue $issue) => in_array($issue->type, $group[2], true)),
                })->map(fn (CrawlIssue $issue) => $this->issueData($issue, $key))->values()->all(),
            ])
            ->filter(fn (array $group) => $group['issues'] !== [])
            ->values()
            ->all();
    }

    private function issueData(CrawlIssue $issue, string $groupKey): array
    {
        $details = $issue->details ?? [];

        if ($groupKey === 'broken_links') {
            return [
                'id'          => $issue->id,
                'title'       => ($details['status_code'] ?? $issue->crawlPage?->status_code) ? __('Link to a page answering with :status', ['status' => $details['status_code'] ?? $issue->crawlPage?->status_code]) : __('Link to a page that could not be loaded'),
                'description' => CrawlIssueTypeEnum::descriptions()[$issue->type->value] ?? null,
                'severity'    => $issue->severity->value,
                'found'       => $issue->crawlPage?->url,
            ];
        }

        return [
            'id'          => $issue->id,
            'title'       => CrawlIssueTypeEnum::labels()[$issue->type->value] ?? $issue->type->value,
            'description' => CrawlIssueTypeEnum::descriptions()[$issue->type->value] ?? null,
            'severity'    => $issue->severity->value,
            'found'       => match ($issue->type) {
                CrawlIssueTypeEnum::MULTIPLE_H1        => __(':count H1 headings on the page', ['count' => $details['h1_count'] ?? '?']),
                CrawlIssueTypeEnum::IMAGES_WITHOUT_ALT => __(':count images without alt text', ['count' => $details['images'] ?? '?']),
                CrawlIssueTypeEnum::SLOW_RESPONSE      => __('The server answered in :seconds s', ['seconds' => round(($details['response_ms'] ?? 0) / 1000, 1)]),
                CrawlIssueTypeEnum::TITLE_TOO_LONG,
                CrawlIssueTypeEnum::TITLE_TOO_SHORT    => __(':title (:length characters)', ['title' => $details['title'] ?? '', 'length' => $details['length'] ?? '?']),
                CrawlIssueTypeEnum::META_DESCRIPTION_TOO_LONG,
                CrawlIssueTypeEnum::META_DESCRIPTION_TOO_SHORT => __(':length characters', ['length' => $details['length'] ?? '?']),
                CrawlIssueTypeEnum::CANONICAL_TO_BROKEN,
                CrawlIssueTypeEnum::CANONICAL_TO_OTHER_PAGE => $details['canonical'] ?? null,
                CrawlIssueTypeEnum::INVALID_STRUCTURED_DATA => implode(', ', $details['errors'] ?? []),
                default                                => null,
            },
        ];
    }
}
