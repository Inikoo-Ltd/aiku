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
use App\Exports\Web\Sheets\CrawlTabSheet;
use App\Models\Web\Crawl;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The rows of one tab and filter of a crawl as an Excel file, as the site crawler table shows them.
 */
class ExportCrawlTab extends OrgAction
{
    use WithSiteCrawlerAuthorisation;

    private Crawl $crawl;

    public function authorize(ActionRequest $request): bool
    {
        return $this->canViewCrawlsOf($request->user(), $this->crawl->website);
    }

    public function handle(Crawl $crawl, CrawlTabEnum $tab, ?string $filter): BinaryFileResponse
    {
        $filterLabel = $filter ? (CrawlIssueTypeEnum::labels()[$filter] ?? $tab->stateFilters()[$filter] ?? $filter) : __('All');
        $title       = CrawlTabEnum::labels()[$tab->value].' - '.$filterLabel;

        return Excel::download(
            new CrawlTabSheet($crawl, $tab, $filter, $title),
            now()->format('Y-m-d').'-crawl-'.$crawl->website->slug.'-'.Str::slug($title).'.xlsx'
        );
    }

    public function asController(Crawl $crawl, ActionRequest $request): BinaryFileResponse
    {
        abort_unless($crawl->type === CrawlTypeEnum::AUDIT, 404);

        $this->crawl = $crawl;
        $this->initialisationFromGroup(group(), $request);

        $tab    = CrawlTabEnum::tryFrom((string) $request->query('tab')) ?? CrawlTabEnum::INTERNAL;
        $filter = $request->query('filter');

        return $this->handle($crawl, $tab, is_string($filter) && $filter !== '' ? $filter : null);
    }
}
