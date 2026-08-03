<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Fri, 26 May 2023 13:25:37 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Enums\Helpers\Export\ExportTypeEnum;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilderContract;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait WithExportData
{
    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function export($callback, $prefix, string $type): ?BinaryFileResponse
    {
        $result = null;

        if ($callback instanceof FromQuery && $callback->query()->count() >= 20000) {
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
     * Stream a CSV straight from a lazy query cursor: flat memory, no model
     * hydration and no pre-count, suitable for very large datasets.
     *
     * @param  array<int, string>  $headings
     */
    public function streamCsv(QueryBuilderContract $query, array $headings, string $prefix): StreamedResponse
    {
        $filename = now()->format('Y-m-d') . '-' . $prefix . '-' . rand(111, 999) . '.csv';

        return response()->streamDownload(function () use ($query, $headings) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headings, ',', '"', '');

            foreach ($query->cursor() as $row) {
                fputcsv($handle, array_values((array) $row), ',', '"', '');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Stream a CSV from a FromQuery export while still applying its own map()
     * and headings(). Uses a lazy cursor so memory stays flat and there is no
     * PhpSpreadsheet buffering, suitable for very large datasets whose row
     * formatting is too complex to push into SQL. Relations touched by map()
     * must be eager-loaded in the export's query() to avoid N+1.
     */
    public function streamMappedCsv(FromQuery $export, string $prefix): StreamedResponse
    {
        $filename = now()->format('Y-m-d') . '-' . $prefix . '-' . rand(111, 999) . '.csv';

        return response()->streamDownload(function () use ($export) {
            $handle = fopen('php://output', 'w');

            if ($export instanceof WithHeadings) {
                fputcsv($handle, $export->headings(), ',', '"', '');
            }

            foreach ($export->query()->lazy() as $row) {
                $line = $export instanceof WithMapping ? $export->map($row) : array_values((array) $row);
                fputcsv($handle, $line, ',', '"', '');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
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
