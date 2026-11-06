<?php

/*
 * Created: Fri, 06 Nov 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Crawl;

use App\Models\Web\Crawl;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The report of an audit closed before the end (a failed part, an abandoned audit): the issues
 * of every page it got to. Queued, so whoever closes the audit is not held while they are found.
 */
class ReportSiteAudit
{
    use AsAction;

    public string $jobQueue = 'long-low-priority';

    public int $jobTimeout = 1800;

    public function handle(Crawl $crawl, string $finishReason): Crawl
    {
        return AuditWebsite::make()->report($crawl, $finishReason);
    }
}
