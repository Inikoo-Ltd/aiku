<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 06 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Exports\Web\Sheets;

use App\Actions\Web\Crawl\UI\GetCrawlTabRows;
use App\Enums\Web\Crawl\CrawlTabEnum;
use App\Models\Web\Crawl;
use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Every row of one tab and filter of a crawl with the columns the site crawler shows for it, read in
 * chunks so a large crawl does not have to fit in memory.
 */
class CrawlTabSheet implements FromQuery, WithHeadings, WithMapping, WithTitle, ShouldAutoSize, WithStrictNullComparison
{
    /** @var array<int, array{key: string, label: string, numeric: bool}> */
    private array $columns;

    public function __construct(
        private readonly Crawl $crawl,
        private readonly CrawlTabEnum $tab,
        private readonly ?string $filter,
        private readonly string $title,
        private readonly ?string $segment = null,
    ) {
        $this->columns = GetCrawlTabRows::make()->columns($this->tab, $this->filter, $this->crawl);
    }

    public function query(): Builder
    {
        return GetCrawlTabRows::make()->query($this->crawl, $this->tab, $this->filter, null, $this->segment)->orderBy('url');
    }

    public function headings(): array
    {
        return [GetCrawlTabRows::columnLabels()['url'], ...array_column($this->columns, 'label')];
    }

    public function map($row): array
    {
        $values = GetCrawlTabRows::make()->row($row, array_column($this->columns, 'key'));

        return [
            $values['url'],
            ...array_map(fn (string $key) => match (true) {
                is_array($values[$key])  => json_encode($values[$key], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                is_bool($values[$key])   => $values[$key] ? 'Yes' : 'No',
                default                  => $values[$key],
            }, array_column($this->columns, 'key')),
        ];
    }

    public function title(): string
    {
        return mb_substr($this->title, 0, 31);
    }
}
