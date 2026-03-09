<?php

namespace App\Exports\HumanResources;

use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
// use Maatwebsite\Excel\Concerns WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class CalendarExport implements FromArray, WithHeadings, WithColumnWidths
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

    public function headings(): array
    {
        $headers = ['Employee', 'Department', 'Job Title'];

        foreach ($this->weeks as $week) {
            foreach ($week['days'] as $day) {
                $headers[] = Carbon::parse($day['date'])->format('M d');
            }
        }

        return $headers;
    }

    public function array(): array
    {
        $exportData = [];

        // Add employee data rows
        foreach ($this->calendarData as $employee) {
            $row = [
                $employee['name'],
                $employee['department'] ?? '',
                $employee['job_title'] ?? ''
            ];

            foreach ($this->weeks as $week) {
                foreach ($week['days'] as $day) {
                    $leaveData = $this->getLeaveForDate($employee, $day['date']);
                    $row[] = $leaveData;
                }
            }

            $exportData[] = $row;
        }

        return $exportData;
    }



    private function getLeaveForDate(array $employee, string $date): string
    {
        foreach ($employee['leaves'] as $leave) {
            if ($date >= $leave['start_date'] && $date <= $leave['end_date']) {
                $shortCode = $leave['type_code'] ?? '';
                $status = $leave['status'] === 'pending' ? ' (P)' : '';
                return $shortCode . $status;
            }
        }

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
            'A' => 25,
            'B' => 20,
            'C' => 20,
        ];

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
