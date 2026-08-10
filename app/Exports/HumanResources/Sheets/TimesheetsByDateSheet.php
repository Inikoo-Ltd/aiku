<?php

namespace App\Exports\HumanResources\Sheets;

use App\Models\HumanResources\Clocking;
use App\Models\HumanResources\Timesheet;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TimesheetsByDateSheet implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
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
        $query = Timesheet::query()
            ->where('organisation_id', $this->organisationId)
            ->whereBetween('date', [$this->from, $this->to])
            ->with('subject.jobPositions')
            ->select([
                'timesheets.*',
                'first_clocking_notes' => Clocking::query()
                    ->select('notes')
                    ->whereColumn('timesheet_id', 'timesheets.id')
                    ->orderBy('clocked_at')
                    ->orderBy('id')
                    ->limit(1),
            ]);

        if (!empty($this->employeeIds)) {
            $query->where('subject_type', 'Employee')->whereIn('subject_id', $this->employeeIds);
        }

        return $query->orderBy('date')->orderBy('subject_name');
    }

    public function headings(): array
    {
        return ['Date', 'Name', 'Job Position', 'Start At', 'End At', 'Notes', 'Working', 'Breaks', 'Clock In', 'Clock Out'];
    }

    public function title(): string
    {
        return 'Timesheets';
    }

    /** @var Timesheet $row */
    public function map($row): array
    {
        $jobPosition = '-';

        if ($row->subject_type === 'Employee' && $row->subject) {
            $jobPosition = $row->subject->job_title ?: '-';
        }

        return [
            $row->date->format('Y-m-d'),
            $row->subject_name,
            $jobPosition,
            $row->start_at?->copy()->setTimezone($this->timezone)->format('H:i') ?: '-',
            $row->end_at?->copy()->setTimezone($this->timezone)->format('H:i') ?: '-',
            $row->first_clocking_notes ?: '-',
            $this->formatDuration($row->working_duration),
            $this->formatDuration($row->breaks_duration),
            $row->number_time_trackers,
            max(0, $row->number_time_trackers - $row->number_open_time_trackers),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds <= 0) {
            return '-';
        }

        $hours   = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs    = $seconds % 60;

        return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
    }
}
