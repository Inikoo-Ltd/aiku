<?php

namespace App\Exports\HumanResources\Sheets;

use App\Exports\HumanResources\Sheets\Concerns\MergesGroupedColumns;
use App\Models\HumanResources\Clocking;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ClockingsByEmployeeSheet implements FromQuery, ShouldAutoSize, WithEvents, WithHeadings, WithMapping, WithStyles, WithTitle
{
    use MergesGroupedColumns;

    /**
     * @param  array<int, int>  $employeeIds
     */
    public function __construct(
        private readonly int $organisationId,
        private readonly array $employeeIds,
        private readonly string $from,
        private readonly string $to,
        private readonly string $timezone,
    ) {
    }

    public function query(): Builder
    {
        $query = Clocking::query()
            ->join('timesheets', 'clockings.timesheet_id', '=', 'timesheets.id')
            ->leftJoin('employees', function ($join) {
                $join->on('employees.id', '=', 'timesheets.subject_id')
                    ->where('timesheets.subject_type', '=', 'Employee');
            })
            ->where('timesheets.organisation_id', $this->organisationId)
            ->where('timesheets.subject_type', 'Employee')
            ->whereBetween('timesheets.date', [$this->from, $this->to])
            ->select([
                'timesheets.date as timesheet_date',
                'timesheets.subject_name',
                'employees.job_title',
                'clockings.clocked_at',
                'clockings.notes',
            ])
            ->selectRaw('row_number() over (partition by clockings.timesheet_id order by clockings.clocked_at) as session_no')
            ->selectRaw("case
                when exists (select 1 from time_trackers tt where tt.start_clocking_id = clockings.id) then 'In'
                when exists (select 1 from time_trackers tt where tt.end_clocking_id = clockings.id) then 'Out'
                else '-'
            end as clock_action");

        if (!empty($this->employeeIds)) {
            $query->whereIn('timesheets.subject_id', $this->employeeIds);
        }

        return $query
            ->orderBy('timesheets.subject_name')
            ->orderBy('timesheets.date')
            ->orderBy('clockings.clocked_at');
    }

    public function headings(): array
    {
        return ['Name', 'Job Position', 'Date', 'Session #', 'Time', 'Action', 'Notes'];
    }

    public function title(): string
    {
        return 'Clockings';
    }

    public function map($row): array
    {
        return [
            $row->subject_name,
            $row->job_title ?: '-',
            $row->timesheet_date,
            $row->session_no,
            $row->clocked_at ? \Illuminate\Support\Carbon::parse($row->clocked_at, 'UTC')->setTimezone($this->timezone)->format('H:i:s') : '-',
            $row->clock_action,
            $row->notes ?: '-',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $this->mergeColumns($event->sheet->getDelegate(), ['A', 'B', 'C']);
            },
        ];
    }
}
