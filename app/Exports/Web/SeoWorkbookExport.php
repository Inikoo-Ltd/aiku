<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Tue, 03 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Exports\Web;

use App\Exports\Web\Sheets\SiteAuditTableSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * An SEO table that needs more than one sheet: each sheet its title, headings and rows already as
 * they should read.
 */
class SeoWorkbookExport implements WithMultipleSheets
{
    /**
     * @param  array<int, array{title: string, headings: array<int, string>, rows: array<int, array<int, mixed>>}>  $sheets
     */
    public function __construct(private readonly array $sheets)
    {
    }

    public function sheets(): array
    {
        return array_map(
            fn (array $sheet) => new SiteAuditTableSheet($sheet['title'], $sheet['headings'], $sheet['rows']),
            $this->sheets
        );
    }
}
