<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Crawl;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Web\Crawl\UI\ShowSiteAudit;
use App\Exports\Web\SiteAuditExport;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use Lorisleiva\Actions\ActionRequest;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The latest finished audit of a website as an Excel file: its numbers, the issue types compared
 * with the previous audit, and every page with every issue found on it.
 */
class ExportSiteAudit extends OrgAction
{
    use WithWebAuthorisation;

    public function handle(Website $website): BinaryFileResponse
    {
        $audit  = ShowSiteAudit::make()->handle($website);
        $latest = $audit['latest'] ?? abort(404);

        $overview = [
            ['Website', $website->domain],
            ['Audit finished', $latest['end_at']?->toDateTimeString()],
            ['Health score', $latest['health_score']],
            ['Pages crawled', $latest['pages']],
            ['Pages found', $latest['urls_found']],
            ['Pages with errors', $latest['pages_with_errors']],
            ['Errors', $latest['errors']],
            ['Warnings', $latest['warnings']],
            ['Notices', $latest['notices']],
        ];

        $issueTypes = array_map(fn (array $issue) => [
            $issue['severity'],
            $issue['label'],
            $issue['description'],
            $issue['pages'],
            $issue['previous_pages'],
        ], $audit['issues']);

        return Excel::download(
            new SiteAuditExport($latest['id'], $overview, $issueTypes),
            now()->format('Y-m-d').'-site-audit-'.$website->slug.'.xlsx'
        );
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): BinaryFileResponse
    {
        abort_unless($shop->website, 404);

        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop->website);
    }
}
