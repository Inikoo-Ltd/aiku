<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Oct 2026 22:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Exports\Production;

use App\Enums\Production\Artisan\ArtisanPayTypeEnum;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

/**
 * Written as text so every hours and £ figure keeps its two decimals ("4.00", not "4").
 */
class ManufacturePayrollSummaryExport extends StringValueBinder implements FromArray, WithHeadings, WithCustomValueBinder
{
    /**
     * @param  array<int, array<string, mixed>>  $artisans  from GetManufacturePayroll
     */
    public function __construct(protected array $artisans)
    {
    }

    public function headings(): array
    {
        $headings = ['Worker', 'Pay type', 'Agency'];
        foreach (self::columns() as $key => $label) {
            $headings[] = "$label hours";
            $headings[] = "$label £";
            if ($key == 'working') {
                $headings[] = 'Tablet hours';
                $headings[] = 'Not on tablet (Band 0) hours';
            }
        }
        $headings[] = 'Total £';
        $headings[] = 'Changed after lock, not approved';

        return $headings;
    }

    public function array(): array
    {
        return array_map(function (array $artisan) {
            $row = [
                $artisan['worker'],
                $artisan['artisan_pay_type'] == ArtisanPayTypeEnum::HOURLY ? 'Hourly' : 'Piece rate',
                $artisan['is_agency_worker'] ? 'Yes' : 'No',
            ];
            foreach (array_keys(self::columns()) as $key) {
                $row[] = number_format($artisan[$key.'_hours'], 2, '.', '');
                $row[] = number_format($artisan[$key.'_amount'], 2, '.', '');
                if ($key == 'working') {
                    $row[] = number_format($artisan['tablet_hours'], 2, '.', '');
                    $row[] = number_format($artisan['not_on_tablet_hours'], 2, '.', '');
                }
            }
            $row[] = number_format($artisan['total'], 2, '.', '');
            $row[] = ($artisan['unapproved_changes'] ?? 0) ? 'Yes' : '';

            return $row;
        }, $this->artisans);
    }

    /**
     * @return array<string, string>
     */
    public static function columns(): array
    {
        return [
            'working' => 'Clocked hours',
            'band_0'  => 'Band 0',
            'band_1'  => 'Band 1',
            'band_2'  => 'Band 2',
            'band_3'  => 'Band 3',
            'band_d'  => 'Development (D)',
            'band_dg' => 'Guru Dev (DG)',
            'guru'       => 'Guru Bonus',
            'adjustment' => 'Adjustments to earlier weeks',
        ];
    }
}
