<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Fri, 26 May 2023 13:25:37 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Enums\Helpers\Export\ExportTypeEnum;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

trait WithExportData
{
    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function export($callback, $prefix, string $type): ?BinaryFileResponse
    {
        $result = null;

        $query = $callback->query();

        if ($query->count() >= 20000) {
            $type = ExportTypeEnum::CSV->value;
        }

        if ($type == ExportTypeEnum::XLSX->value) {
            $result = $this->xlsx($callback, $prefix);
        }

        if ($type == ExportTypeEnum::CSV->value) {
            $result = $this->csv($callback, $prefix);
        }

        if ($type == ExportTypeEnum::PDF->value) {
            $result = $this->pdf($callback, $prefix);
        }

        return $result;
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function xlsx($callback, $prefix): BinaryFileResponse
    {
        $filename = now()->format('Y-m-d') . '-' . $prefix . '-' . rand(111, 999) . '.xlsx';

        return Excel::download($callback, $filename, \Maatwebsite\Excel\Excel::XLSX);
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function csv($callback, $prefix): BinaryFileResponse
    {
        $filename = now()->format('Y-m-d') . '-' . $prefix . '-' . rand(111, 999) . '.csv';

        return Excel::download($callback, $filename, \Maatwebsite\Excel\Excel::CSV);
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function pdf($callback, $prefix): BinaryFileResponse
    {
        $filename = now()->format('Y-m-d') . '-' . $prefix . '-' . rand(111, 999) . '.pdf';

        return Excel::download($callback, $filename, \Maatwebsite\Excel\Excel::DOMPDF);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in([ExportTypeEnum::XLSX->value, ExportTypeEnum::CSV->value, ExportTypeEnum::PDF->value])]
        ];
    }
}
