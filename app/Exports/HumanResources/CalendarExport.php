<?php

namespace App\Exports\HumanResources;

use App\Enums\HumanResources\Leave\LeaveTypeEnum;
use App\Models\HumanResources\Leave;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
// use Maatwebsite\Excel\Concerns WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class CalendarExport implements FromView, WithColumnWidths
{
    protected Organisation $organisation;
    protected array $filters;
    protected array $calendarData;
    protected array $weeks;
    protected array $holidays;

    public function __construct(Organisation $organisation, array $filters, array $calendarData, array $weeks, array $holidays = [])
    {
        $this->organisation = $organisation;
        $this->filters = $filters;
        $this->calendarData = $calendarData;
        $this->weeks = $weeks;
        $this->holidays = $holidays;
    }

    public function view(): View
    {
        $visibleStart = Carbon::parse($this->filters['start_date'] ?? now()->startOfMonth());
        $visibleEnd = Carbon::parse($this->filters['end_date'] ?? now()->endOfMonth());

        return view('exports.human-resources.calendar', [
            'exportData' => $this->prepareExportData(),
            'organisation' => $this->organisation,
            'filters' => $this->filters,
            'holidays' => $this->holidays,
        ]);
    }

    private function prepareExportData(): array
    {
        $headers = ['Employee', 'Department', 'Job Title'];

        // Add date headers
        foreach ($this->weeks as $week) {
            foreach ($week['days'] as $day) {
                $headers[] = $day['date'];
            }
        }

        $exportData['headers'] = $headers;

        // Add employee rows
        foreach ($this->calendarData as $employee) {
            $row = [
                $employee['name'],
                $employee['department'] ?? '',
                $employee['job_title'] ?? ''
            ];

            // Add leave data for each day
            foreach ($this->weeks as $week) {
                foreach ($week['days'] as $day) {
                    $leaveData = $this->getLeaveForDate($employee, $day['date']);
                    $row[] = $leaveData;
                }
            }

            $exportData['rows'][] = $row;
        }

        return $exportData;
    }

    private function getLeaveForDate(array $employee, string $date): string
    {
        foreach ($employee['leaves'] as $leave) {
            if ($date >= $leave['start_date'] && $date <= $leave['end_date']) {
                $shortCode = LeaveTypeEnum::shortCodes()[$leave['type']] ?? '';
                $status = $leave['status'] === 'pending' ? ' (P)' : '';
                return $shortCode . $status;
            }
        }

        // Check if it's a holiday
        foreach ($this->holidays as $holiday) {
            if ($date >= $holiday['from'] && $date <= $holiday['to']) {
                return 'HOL';
            }
        }

        return '';
    }

    public function columnWidths(): array
    {
        $widths = [
            'A' => 25, // Employee name
            'B' => 20, // Department
            'C' => 20, // Job Title
        ];

        // Set width for each date column
        $col = 'D';
        foreach ($this->weeks as $week) {
            foreach ($week['days'] as $day) {
                $widths[$col] = 8;
                $col++;
            }
        }

        return $widths;
    }

    /*
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();

                // Style headers
                $sheet->getStyle('A1:' . $highestColumn . '1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '4472C4'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                // Style employee column
                $sheet->getStyle('A2:A' . $highestRow)->applyFromArray([
                    'font' => [
                        'bold' => true,
                    ],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F2F2F2'],
                    ],
                ]);

                // Add borders to all cells
                $sheet->getStyle('A1:' . $highestColumn . $highestRow)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                ]);

                // Auto-size filter area
                $sheet->setAutoFilter('A1:' . $highestColumn . $highestRow);
            },
        ];
    }
    */
}
