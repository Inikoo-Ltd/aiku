<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jul 2024 14:54:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\Timesheet\Pdf;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Actions\Traits\WithExportData;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\Timesheet;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Lorisleiva\Actions\ActionRequest;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;
use Symfony\Component\HttpFoundation\Response;

class PdfTimesheet extends OrgAction
{
    use WithHumanResourcesAuthorisation;
    use WithExportData;

    /**
     * @throws \Mpdf\MpdfException
     */
    public function handle(Employee $employee): Response
    {
        $filename = __('Timesheets - ') . $employee->contact_name . '.pdf';
        $config = [
            'title' => $filename,
            'margin_left' => 8,
            'margin_right' => 8,
            'margin_top' => 2,
            'margin_bottom' => 2,
            'auto_page_break' => true,
            'auto_page_break_margin' => 10
        ];

        $query = QueryBuilder::for(Timesheet::class);
        $query->where('subject_type', class_basename($employee));
        $query->where('subject_id', $employee->id);
        $query->withFilterPeriod('date');

        return PDF::chunkLoadView('<html-separator/>', 'hr.timesheet', [
            'filename' => $filename,
            'organisation' => $employee->organisation,
            'employee' => $employee,
            'timesheets' => $query->get()
        ], [], $config)->stream($filename);
    }

    /**
     * @throws \Mpdf\MpdfException
     */
    public function inEmployee(Organisation $organisation, Employee $employee, ActionRequest $request): Response
    {
        $this->initialisation($organisation, $request);
        return $this->handle($employee);
    }
}
