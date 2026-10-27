<?php

/*
 * Created: Fri, 16 Oct 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory\UI;

use App\Actions\Catalogue\ProductCategory\CheckFamilyWebpage;
use App\Enums\Web\Crawl\CrawlIssueSeverityEnum;
use App\Enums\Web\Crawl\CrawlIssueTypeEnum;
use App\Enums\Web\Crawl\CrawlStateEnum;
use App\Enums\Web\Crawl\CrawlTypeEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\CrawlIssue;
use App\Models\Web\CrawlPage;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

class GetFamilySeoIssues
{
    use AsObject;

    public const string BROKEN_LINKS = 'broken_links';

    /**
     * What the latest site audit, or a later page check (CheckFamilyWebpage), found on the family webpage, grouped by kind and most severe first.
     * A broken link is recorded on the page it points to, so links from this page to pages that
     * answer with an error are read from those pages; after a later page check, the ones it found
     * asking every link of the page live.
     *
     * @return null|array{last_crawl_at: ?string, total: int, groups: array<int, array{key: string, label: string, description: string, items: array<int, array{title: string, type: string, severity: string, severity_label: string, details: array, url: ?string}>}>}
     */
    public function handle(ProductCategory $family): ?array
    {
        $webpage = $family->webpage;

        if (!$webpage) {
            return null;
        }

        $auditPage = CrawlPage::where('webpage_id', $webpage->id)
            ->whereHas('crawl', fn ($query) => $query->where('type', CrawlTypeEnum::AUDIT)->where('state', CrawlStateEnum::FINISH))
            ->with(['crawl', 'issues'])
            ->orderByDesc('crawl_id')
            ->orderByRaw('status_code = 200 desc nulls last')
            ->first();
        $crawlPage = CheckFamilyWebpage::checkedPage($webpage, $auditPage);

        if (!$crawlPage) {
            return ['last_crawl_at' => null, 'total' => 0, 'groups' => []];
        }

        $groups = $crawlPage->issues
            ->groupBy(fn (CrawlIssue $issue) => $issue->type->value)
            ->map(fn (Collection $issues, string $type) => $this->group(
                $type,
                CrawlIssueTypeEnum::labels()[$type],
                CrawlIssueTypeEnum::descriptions()[$type],
                $issues->map(fn (CrawlIssue $issue) => $this->item($issue, CrawlIssueTypeEnum::labels()[$type]))
            ));

        $brokenLinks = CheckFamilyWebpage::checkedBrokenLinkIssues($webpage, $auditPage) ?? ($auditPage ? CrawlIssue::where('crawl_id', $auditPage->crawl_id)
            ->whereIn('type', [CrawlIssueTypeEnum::HTTP_4XX, CrawlIssueTypeEnum::HTTP_5XX, CrawlIssueTypeEnum::FETCH_FAILED])
            ->whereJsonContains('details->linked_from', $auditPage->url)
            ->with('crawlPage')
            ->get() : collect());

        if ($brokenLinks->isNotEmpty()) {
            $groups->put(self::BROKEN_LINKS, $this->group(
                self::BROKEN_LINKS,
                __('Broken links'),
                __('Pages this page links to that answer with an error or could not be loaded.'),
                $brokenLinks->map(fn (CrawlIssue $issue) => $this->item($issue, __('Broken link'), $issue->crawlPage->url))
            ));
        }

        $groups = $groups->sortBy('rank')->values();

        return [
            'last_crawl_at' => $crawlPage->crawl->end_at?->toIso8601String(),
            'total'         => $groups->sum(fn (array $group) => count($group['items'])),
            'groups'        => $groups->map(fn (array $group) => collect($group)->except('rank')->all())->all(),
        ];
    }

    private function group(string $key, string $label, string $description, Collection $items): array
    {
        $items = $items->sortBy('rank');

        return [
            'key'         => $key,
            'label'       => $label,
            'description' => $description,
            'rank'        => $items->min('rank'),
            'items'       => $items->map(fn (array $item) => collect($item)->except('rank')->all())->values()->all(),
        ];
    }

    private function item(CrawlIssue $issue, string $title, ?string $url = null): array
    {
        return [
            'title'          => $title,
            'type'           => $issue->type->value,
            'severity'       => $issue->severity->value,
            'severity_label' => CrawlIssueSeverityEnum::labels()[$issue->severity->value],
            'details'        => $issue->details ?? [],
            'url'            => $url,
            'rank'           => $issue->severity->rank(),
        ];
    }
}
