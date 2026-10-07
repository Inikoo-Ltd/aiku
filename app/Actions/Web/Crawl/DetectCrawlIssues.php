<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Crawl;

use App\Enums\Web\Crawl\CrawlIssueSeverityEnum;
use App\Enums\Web\Crawl\CrawlIssueTypeEnum;
use App\Models\Web\Crawl;
use App\Models\Web\CrawlIssue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class DetectCrawlIssues
{
    use AsObject;

    private const int DUPLICATES_SHOWN = 5;

    private array $issues = [];

    /**
     * @param  array<string, array<int, string>>  $linkedFrom
     */
    public function handle(Crawl $crawl, array $linkedFrom = []): Crawl
    {
        $this->issues = [];

        $pages = DB::table('crawl_pages')
            ->where('crawl_id', $crawl->id)
            ->get()
            ->keyBy('url_hash');

        $redirectHops = [];

        foreach ($pages as $page) {
            $this->detectStatusIssues($page, $linkedFrom);

            if ($page->status_code >= 300 && $page->status_code < 400) {
                $redirectHops[$page->id] = $this->detectRedirectIssues($page, $pages, $linkedFrom);
            }

            if ($this->isHtmlPage($page)) {
                $this->detectPageIssues($page, $pages);
            }
        }

        $indexablePages = $pages->filter(fn ($page) => $page->is_indexable && $this->isHtmlPage($page));

        $this->detectDuplicates($indexablePages, 'title', CrawlIssueTypeEnum::DUPLICATE_TITLE);
        $this->detectDuplicates($indexablePages, 'meta_description', CrawlIssueTypeEnum::DUPLICATE_META_DESCRIPTION);

        DB::transaction(function () use ($crawl, $redirectHops) {
            CrawlIssue::where('crawl_id', $crawl->id)->delete();

            $now = now();

            foreach (array_chunk($this->issues, 1000) as $chunk) {
                CrawlIssue::insert(array_map(fn (array $issue) => [
                    'crawl_id'      => $crawl->id,
                    'crawl_page_id' => $issue['crawl_page_id'],
                    'type'          => $issue['type']->value,
                    'severity'      => $issue['type']->severity()->value,
                    'details'       => $issue['details'] ? json_encode($issue['details']) : null,
                    'created_at'    => $now,
                ], $chunk));
            }

            foreach ($redirectHops as $crawlPageId => $hops) {
                DB::table('crawl_pages')->where('id', $crawlPageId)->update(['redirect_hops' => $hops]);
            }
        });

        $issuesBySeverity = collect($this->issues)->countBy(fn (array $issue) => $issue['type']->severity()->value);
        $pagesWithErrors  = collect($this->issues)
            ->filter(fn (array $issue) => $issue['type']->severity() === CrawlIssueSeverityEnum::ERROR)
            ->pluck('crawl_page_id')
            ->unique()
            ->count();

        $crawl->update([
            'number_errors'     => $issuesBySeverity->get(CrawlIssueSeverityEnum::ERROR->value, 0),
            'number_warnings'   => $issuesBySeverity->get(CrawlIssueSeverityEnum::WARNING->value, 0),
            'number_notices'    => $issuesBySeverity->get(CrawlIssueSeverityEnum::NOTICE->value, 0),
            'pages_with_errors' => $pagesWithErrors,
            'health_score'      => $pages->count() > 0 ? round(($pages->count() - $pagesWithErrors) * 100 / $pages->count(), 2) : null,
        ]);

        return $crawl;
    }

    private function detectStatusIssues(object $page, array $linkedFrom): void
    {
        $linkDetails = [
            'inlinks'       => (int) $page->inlinks,
            'linked_from'   => $linkedFrom[$page->url_hash] ?? [],
            'is_in_sitemap' => (bool) $page->is_in_sitemap,
        ];

        if ($page->status_code === null) {
            $this->addIssue($page, CrawlIssueTypeEnum::FETCH_FAILED, [...$linkDetails, 'error' => $page->fetch_error]);
        } elseif ($page->status_code >= 500) {
            $this->addIssue($page, CrawlIssueTypeEnum::HTTP_5XX, [...$linkDetails, 'status_code' => $page->status_code]);
        } elseif ($page->status_code >= 400) {
            $this->addIssue($page, CrawlIssueTypeEnum::HTTP_4XX, [...$linkDetails, 'status_code' => $page->status_code]);
        }

        if ($page->response_ms !== null && $page->response_ms > CrawlIssueTypeEnum::SLOW_RESPONSE_MS) {
            $this->addIssue($page, CrawlIssueTypeEnum::SLOW_RESPONSE, ['response_ms' => (int) $page->response_ms]);
        }
    }

    private function detectRedirectIssues(object $page, Collection $pages, array $linkedFrom): int
    {
        $hops     = 0;
        $visited  = [];
        $current  = $page;
        $isLoop   = false;
        $finalUrl = null;

        while ($current && $current->status_code >= 300 && $current->status_code < 400) {
            if (isset($visited[$current->url_hash])) {
                $isLoop = true;

                break;
            }

            $visited[$current->url_hash] = true;
            $hops++;
            $finalUrl = $current->redirect_to;
            $current  = $current->redirect_to ? $pages->get(md5($current->redirect_to)) : null;
        }

        if ($isLoop) {
            $this->addIssue($page, CrawlIssueTypeEnum::REDIRECT_LOOP, ['chain' => array_values(array_map(fn ($hash) => $pages->get($hash)?->url, array_keys($visited)))]);

            return $hops;
        }

        if ($hops > 1) {
            $this->addIssue($page, CrawlIssueTypeEnum::REDIRECT_CHAIN, ['hops' => $hops, 'final_url' => $finalUrl]);
        }

        if ($page->inlinks > 0) {
            $this->addIssue($page, CrawlIssueTypeEnum::LINKED_REDIRECT, [
                'inlinks'     => (int) $page->inlinks,
                'linked_from' => $linkedFrom[$page->url_hash] ?? [],
                'redirect_to' => $page->redirect_to,
            ]);
        }

        return $hops;
    }

    private function detectPageIssues(object $page, Collection $pages): void
    {
        $isNoindex = $page->robots_meta !== null && str_contains(strtolower($page->robots_meta), 'noindex');

        if ($page->is_in_sitemap && $isNoindex) {
            $this->addIssue($page, CrawlIssueTypeEnum::NOINDEX_IN_SITEMAP, ['robots_meta' => $page->robots_meta]);
        }

        if ($page->images_without_alt > 0) {
            $this->addIssue($page, CrawlIssueTypeEnum::IMAGES_WITHOUT_ALT, ['images' => (int) $page->images_without_alt]);
        }

        if (!$isNoindex) {
            $this->detectCanonicalIssues($page, $pages);
        }

        if (!$page->is_indexable) {
            return;
        }

        $titleLength = mb_strlen((string) $page->title);

        if ($page->title === null) {
            $this->addIssue($page, CrawlIssueTypeEnum::MISSING_TITLE);
        } elseif ($titleLength > CrawlIssueTypeEnum::TITLE_MAX_LENGTH) {
            $this->addIssue($page, CrawlIssueTypeEnum::TITLE_TOO_LONG, ['title' => $page->title, 'length' => $titleLength]);
        } elseif ($titleLength < CrawlIssueTypeEnum::TITLE_MIN_LENGTH) {
            $this->addIssue($page, CrawlIssueTypeEnum::TITLE_TOO_SHORT, ['title' => $page->title, 'length' => $titleLength]);
        }

        $metaDescriptionLength = mb_strlen((string) $page->meta_description);

        if ($page->meta_description === null) {
            $this->addIssue($page, CrawlIssueTypeEnum::MISSING_META_DESCRIPTION);
        } elseif ($metaDescriptionLength > CrawlIssueTypeEnum::META_DESCRIPTION_MAX_LENGTH) {
            $this->addIssue($page, CrawlIssueTypeEnum::META_DESCRIPTION_TOO_LONG, ['length' => $metaDescriptionLength]);
        } elseif ($metaDescriptionLength < CrawlIssueTypeEnum::META_DESCRIPTION_MIN_LENGTH) {
            $this->addIssue($page, CrawlIssueTypeEnum::META_DESCRIPTION_TOO_SHORT, ['length' => $metaDescriptionLength]);
        }

        if ($page->h1_count === 0) {
            $this->addIssue($page, CrawlIssueTypeEnum::MISSING_H1);
        } elseif ($page->h1_count > 1) {
            $this->addIssue($page, CrawlIssueTypeEnum::MULTIPLE_H1, ['h1_count' => (int) $page->h1_count]);
        }

        if (!$page->is_in_sitemap) {
            $this->addIssue($page, CrawlIssueTypeEnum::NOT_IN_SITEMAP);
        }
    }

    private function detectCanonicalIssues(object $page, Collection $pages): void
    {
        if ($page->canonical === null) {
            $this->addIssue($page, CrawlIssueTypeEnum::MISSING_CANONICAL);

            return;
        }

        if ($page->canonical === $page->url) {
            return;
        }

        $canonicalPage = $pages->get(md5($page->canonical));

        if ($canonicalPage && $canonicalPage->status_code !== 200) {
            $this->addIssue($page, CrawlIssueTypeEnum::CANONICAL_TO_BROKEN, ['canonical' => $page->canonical, 'status_code' => $canonicalPage->status_code]);

            return;
        }

        $this->addIssue($page, CrawlIssueTypeEnum::CANONICAL_TO_OTHER_PAGE, ['canonical' => $page->canonical]);
    }

    private function detectDuplicates(Collection $indexablePages, string $field, CrawlIssueTypeEnum $type): void
    {
        $indexablePages
            ->filter(fn ($page) => $page->{$field} !== null)
            ->groupBy(fn ($page) => mb_strtolower($page->{$field}))
            ->filter(fn (Collection $group) => $group->count() > 1)
            ->each(function (Collection $group) use ($type) {
                foreach ($group as $page) {
                    $this->addIssue($page, $type, [
                        'duplicates'      => $group->count() - 1,
                        'duplicate_urls'  => $group->where('id', '!=', $page->id)->take(self::DUPLICATES_SHOWN)->pluck('url')->values()->all(),
                    ]);
                }
            });
    }

    private function isHtmlPage(object $page): bool
    {
        return $page->status_code === 200 && str_contains(strtolower((string) $page->content_type), 'html');
    }

    private function addIssue(object $page, CrawlIssueTypeEnum $type, ?array $details = null): void
    {
        $this->issues[] = [
            'crawl_page_id' => $page->id,
            'type'          => $type,
            'details'       => $details,
        ];
    }
}
