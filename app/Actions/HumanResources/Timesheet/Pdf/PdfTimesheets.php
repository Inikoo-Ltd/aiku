<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jul 2024 14:54:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\Timesheet\Pdf;

use App\Actions\OrgAction;
use App\Actions\Traits\WithExportData;
use App\Models\HumanResources\Employee;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;

class PdfTimesheets extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithExportData;

    /**
     * @throws \Mpdf\MpdfException
     */
    public function handle(Organisation $parent): string
    {
        ini_set("pcre.backtrack_limit", "5000000");
        ini_set("pcre.recursion_limit", "5000000");

        $filename = __('Timesheets - ').$parent->name.'.pdf';
        $config   = [
            'title'                  => $filename,
            'margin_left'            => 8,
            'margin_right'           => 8,
            'margin_top'             => 2,
            'margin_bottom'          => 2,
            'auto_page_break'        => true,
            'auto_page_break_margin' => 10
        ];

        $query = QueryBuilder::for(Employee::class);
        $query->where('organisation_id', $parent->id);

        $query->whereHas('timesheets', function ($q) {
            QueryBuilder::for($q)->withFilterPeriod('date');
        });

        $chunkSize      = 10;
        $employeeChunks = $query->get()->chunk($chunkSize);

        $pdf = PDF::loadHTML('', $config);

        foreach ($employeeChunks as $key => $chunk) {
            $html = view('hr.timesheets', [
                'filename'     => $filename,
                'organisation' => $parent,
                'employees'    => $chunk,
            ])->render();

            if ($key > 0) {
                $pdf->getMpdf()->WriteHTML('<pagebreak />');
            }

            $pdf->getMpdf()->WriteHTML($html);
        }

        $filePath = "pdfs/$filename.pdf";
        Storage::put($filePath, $pdf->output());

        return $filePath;
    }


    public function asController(Organisation $organisation, ActionRequest $request): void
    {
        $this->initialisation($organisation, $request);
        self::dispatch($organisation);
    }
}
