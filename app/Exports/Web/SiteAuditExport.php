<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Exports\Web;

use App\Exports\Web\Sheets\SiteAuditPagesSheet;
use App\Exports\Web\Sheets\SiteAuditTableSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * The latest site audit of a website as a workbook: the audit in numbers, the issue types with the
 * pages each affects, and one row per page and issue.
 */
class SiteAuditExport implements WithMultipleSheets
{
    /**
     * @param  array<int, array<int, mixed>>  $overview
     * @param  array<int, array<int, mixed>>  $issueTypes
     */
    public function __construct(
        private readonly int $crawlId,
        private readonly array $overview,
        private readonly array $issueTypes,
    ) {
    }

    public function sheets(): array
    {
        return [
            new SiteAuditTableSheet(__('Overview'), ['Item', 'Value'], $this->overview),
            new SiteAuditTableSheet(__('Issue types'), ['Severity', 'Issue', 'What it means', 'Pages affected', 'Pages affected in the previous audit'], $this->issueTypes),
            new SiteAuditPagesSheet($this->crawlId),
        ];
    }
}
