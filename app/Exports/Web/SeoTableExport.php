<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Exports\Web;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;

/**
 * Any SEO table as a spreadsheet: the headings and the rows already as they should read.
 */
class SeoTableExport implements FromArray, WithHeadings, ShouldAutoSize, WithStrictNullComparison
{
    /**
     * @param  array<int, string>  $headings
     * @param  array<int, array<int, mixed>>  $rows
     */
    public function __construct(private readonly array $headings, private readonly array $rows)
    {
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->headings;
    }
}
