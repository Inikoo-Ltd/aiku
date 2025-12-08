<?php

namespace App\Exports\HumanResources;

use App\Models\HumanResources\Employee;
use App\Models\HumanResources\Timesheet;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TimesheetsExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    private ?Employee $employee;

    public function __construct(Employee $employee)
    {
        $this->employee = $employee;
    }

    public function query(): Relation|\Illuminate\Database\Eloquent\Builder|Employee|Builder
    {
        $timesheets = Timesheet::query();

        if ($this->employee) {
            $timesheets->where([['subject_type', class_basename($this->employee)], ['subject_id', $this->employee->id]]);
        }

        return $timesheets;
    }

    /** @var Timesheet */
    public function map($row): array
    {
        return [
            $row->id,
            $row->slug,
            $row->date,
            $row->start_at,
            $row->end_at,
            $row->working_duration,
            $row->breaks_duration,
            $row->number_time_trackers,
            $row->number_open_time_trackers,
        ];
    }

    public function headings(): array
    {
        return [
            '#',
            'Slug',
            'Date',
            'Start At',
            'End At',
            'Working Duration',
            'Breaks Duration',
            'Number Time Trackers',
            'Number Open Time Trackers',
        ];
    }
}
