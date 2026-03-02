<?php

namespace App\Exports\HumanResources;

use App\Models\HumanResources\Holiday;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class HolidaysExport implements FromQuery, WithMapping, ShouldAutoSize, WithHeadings
{
    public function __construct(
        public int $organisationId,
        public int $year,
        public ?int $month = null,
    ) {
    }

    public function query(): Relation|Builder|Holiday
    {
        $query = Holiday::query()
            ->where('organisation_id', $this->organisationId)
            ->where('year', $this->year)
            ->orderBy('from')
            ->orderBy('to');

        if ($this->month) {
            $query->whereMonth('from', $this->month);
        }

        return $query;
    }

    public function map($row): array
    {
        return [
            $row->from->format('Y-m-d'),
            $row->to->format('Y-m-d'),
            $row->from->format('l'),
            $row->to->format('l'),
            $row->label,
        ];
    }

    public function headings(): array
    {
        return [
            __('From Date'),
            __('To Date'),
            __('From Day'),
            __('To Day'),
            __('Holiday'),
        ];
    }
}
