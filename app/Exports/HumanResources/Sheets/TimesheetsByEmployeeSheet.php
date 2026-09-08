<?php

namespace App\Exports\HumanResources\Sheets;

use App\Enums\UI\HumanResources\TimesheetEmployeeViewEnum;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TimesheetsByEmployeeSheet implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(
        private readonly Collection $rows,
        private readonly TimesheetEmployeeViewEnum $view
    ) {
    }

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        if ($this->view === TimesheetEmployeeViewEnum::OVERVIEW) {
            return ['Name', 'Job Position', 'Clockings', 'Clocked', 'Unpaid overtime', 'Breaks', 'Paid time', 'Paid overtime', 'Worked'];
        }

        return ['Name', 'Job Position', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday', 'Work week', 'Weekend'];
    }

    public function title(): string
    {
        return 'Summary';
    }

    public function map($row): array
    {
        if ($this->view === TimesheetEmployeeViewEnum::OVERVIEW) {
            return [
                $row['subject_name'],
                $row['job_position'],
                $row['clockings'],
                $this->formatDuration($row['working_duration']),
                $this->formatDuration($row['unpaid_overtime_duration']),
                $this->formatDuration($row['breaks_duration']),
                $this->formatDuration($row['paid_duration']),
                $this->formatDuration($row['paid_overtime_duration']),
                $this->formatDuration($row['worked']),
            ];
        }

        $byDay = $row['by_day'];

        return [
            $row['subject_name'],
            $row['job_position'],
            $this->formatDuration($byDay['monday']),
            $this->formatDuration($byDay['tuesday']),
            $this->formatDuration($byDay['wednesday']),
            $this->formatDuration($byDay['thursday']),
            $this->formatDuration($byDay['friday']),
            $this->formatDuration($byDay['saturday']),
            $this->formatDuration($byDay['sunday']),
            $this->formatDuration($byDay['work_week']),
            $this->formatDuration($byDay['weekend']),
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
