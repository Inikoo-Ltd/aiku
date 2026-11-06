<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 06 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Crawl\UI;

use App\Actions\OrgAction;
use App\Enums\Web\Crawl\CrawlIssueTypeEnum;
use App\Enums\Web\Crawl\CrawlTabEnum;
use App\Enums\Web\Crawl\CrawlTypeEnum;
use App\Exports\Web\Sheets\SiteAuditTableSheet;
use App\Models\Web\Crawl;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The reports of a crawl that read across pages rather than one row per URL: every redirect chain
 * hop by hop, every canonical that leads to another canonical, and the overview of every issue.
 */
class ExportCrawlReport extends OrgAction
{
    use WithSiteCrawlerAuthorisation;

    public const array REPORTS = ['redirect_chains', 'canonical_chains', 'issues_overview'];

    private const int MAX_HOPS = 20;

    private Crawl $crawl;

    public function authorize(ActionRequest $request): bool
    {
        return $this->canViewCrawlsOf($request->user(), $this->crawl->website);
    }

    public function handle(Crawl $crawl, string $report): BinaryFileResponse
    {
        [$title, $headings, $rows] = match ($report) {
            'redirect_chains'  => [__('Redirect chains'), ['Address', 'Hops', 'Chain', 'Final URL', 'Final status code', 'Loop', 'Inlinks'], $this->redirectChains($crawl)],
            'canonical_chains' => [__('Canonical chains'), ['Address', 'Hops', 'Chain', 'Final canonical', 'Final status code', 'Loop'], $this->canonicalChains($crawl)],
            default            => [__('Issues overview'), ['Issue', 'Severity', 'Tab', 'URLs', '% of URLs', 'What it means', 'How to fix'], $this->issuesOverview($crawl)],
        };

        return Excel::download(
            new SiteAuditTableSheet($title, $headings, $rows),
            now()->format('Y-m-d').'-crawl-'.$crawl->website->slug.'-'.str_replace('_', '-', $report).'.xlsx'
        );
    }

    /**
     * @return Collection<string, object>
     */
    private function pages(Crawl $crawl): Collection
    {
        return DB::table('crawl_pages')
            ->where('crawl_id', $crawl->id)
            ->get(['url', 'url_hash', 'status_code', 'redirect_to', 'canonical', 'inlinks'])
            ->keyBy('url_hash');
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function redirectChains(Crawl $crawl): array
    {
        $pages = $this->pages($crawl);
        $rows  = [];

        foreach ($pages as $page) {
            if ($page->status_code < 300 || $page->status_code >= 400) {
                continue;
            }

            [$chain, $final, $isLoop] = $this->follow($pages, $page, fn (object $current) => $current->status_code >= 300 && $current->status_code < 400 ? $current->redirect_to : null);

            $rows[] = [$page->url, count($chain) - 1, implode(' > ', $chain), $final->url ?? end($chain), $final->status_code ?? null, $isLoop ? 'Yes' : 'No', (int) $page->inlinks];
        }

        usort($rows, fn (array $a, array $b) => [$b[1], $b[6]] <=> [$a[1], $a[6]]);

        return $rows;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function canonicalChains(Crawl $crawl): array
    {
        $pages        = $this->pages($crawl);
        $rows         = [];
        $canonicalOf  = fn (object $current) => $current->canonical !== null && $current->canonical !== $current->url ? $current->canonical : null;

        foreach ($pages as $page) {
            if ($canonicalOf($page) === null) {
                continue;
            }

            [$chain, $final, $isLoop] = $this->follow($pages, $page, $canonicalOf);

            if (count($chain) > 2 || $isLoop) {
                $rows[] = [$page->url, count($chain) - 1, implode(' > ', $chain), $final->url ?? end($chain), $final->status_code ?? null, $isLoop ? 'Yes' : 'No'];
            }
        }

        usort($rows, fn (array $a, array $b) => $b[1] <=> $a[1]);

        return $rows;
    }

    /**
     * Follows a page to the next URL the step gives, until a page without one, a URL that was not
     * crawled or a URL already in the chain.
     *
     * @return array{0: array<int, string>, 1: object|null, 2: bool}
     */
    private function follow(Collection $pages, object $page, callable $next): array
    {
        $chain   = [$page->url];
        $visited = [$page->url_hash => true];
        $current = $page;

        while (count($chain) <= self::MAX_HOPS && ($nextUrl = $next($current)) !== null) {
            $chain[] = $nextUrl;
            $hash    = md5($nextUrl);

            if (isset($visited[$hash])) {
                return [$chain, $pages->get($hash), true];
            }

            $visited[$hash] = true;
            $current        = $pages->get($hash);

            if (!$current) {
                return [$chain, null, false];
            }
        }

        return [$chain, $current, false];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function issuesOverview(Crawl $crawl): array
    {
        $urls   = max(1, DB::table('crawl_pages')->where('crawl_id', $crawl->id)->count());
        $counts = DB::table('crawl_issues')
            ->where('crawl_id', $crawl->id)
            ->groupBy('type')
            ->selectRaw('type, count(*) as pages')
            ->pluck('pages', 'type');

        $labels       = CrawlIssueTypeEnum::labels();
        $descriptions = CrawlIssueTypeEnum::descriptions();
        $howToFix     = CrawlIssueTypeEnum::howToFix();
        $tabs         = CrawlTabEnum::labels();

        return collect(CrawlIssueTypeEnum::cases())
            ->filter(fn (CrawlIssueTypeEnum $type) => $counts->has($type->value))
            ->sortBy([fn (CrawlIssueTypeEnum $a, CrawlIssueTypeEnum $b) => $a->severity()->rank() <=> $b->severity()->rank(), fn (CrawlIssueTypeEnum $a, CrawlIssueTypeEnum $b) => $counts->get($b->value) <=> $counts->get($a->value)])
            ->map(fn (CrawlIssueTypeEnum $type) => [
                $labels[$type->value],
                $type->severity()->value,
                $tabs[$type->tab()->value],
                (int) $counts->get($type->value),
                round($counts->get($type->value) * 100 / $urls, 1),
                $descriptions[$type->value],
                $howToFix[$type->value],
            ])
            ->values()
            ->all();
    }

    public function asController(Crawl $crawl, ActionRequest $request): BinaryFileResponse
    {
        abort_unless($crawl->type === CrawlTypeEnum::AUDIT, 404);

        $this->crawl = $crawl;
        $this->initialisationFromGroup(group(), $request);

        $report = (string) $request->query('report');

        return $this->handle($crawl, in_array($report, self::REPORTS, true) ? $report : 'issues_overview');
    }
}
