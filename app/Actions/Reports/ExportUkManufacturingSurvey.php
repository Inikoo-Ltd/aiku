<?php

namespace App\Actions\Reports;

use App\Actions\OrgAction;
use App\Exports\Reports\UkManufacturingSurveyExport;
use App\Models\SysAdmin\Organisation;
use Carbon\Carbon;
use Lorisleiva\Actions\ActionRequest;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportUkManufacturingSurvey extends OrgAction
{
    public function handle(Organisation $organisation, string $startDate, string $endDate): BinaryFileResponse
    {
        $filename = sprintf(
            'uk-manufacturing-survey-%s-%s-to-%s.xlsx',
            $organisation->slug,
            $startDate,
            $endDate
        );

        return Excel::download(
            new UkManufacturingSurveyExport($organisation->id, $startDate, $endDate),
            $filename,
            \Maatwebsite\Excel\Excel::XLSX
        );
    }

    public function asController(Organisation $organisation, ActionRequest $request): BinaryFileResponse
    {
        ini_set('memory_limit', '512M');
        $this->initialisation($organisation, $request);

        $startDate = $request->get('start_date')
            ? Carbon::parse($request->get('start_date'))->format('Y-m-d')
            : now()->subYear()->startOfYear()->format('Y-m-d');

        $endDate = $request->get('end_date')
            ? Carbon::parse($request->get('end_date'))->format('Y-m-d')
            : now()->subYear()->endOfYear()->format('Y-m-d');

        return $this->handle($organisation, $startDate, $endDate);
    }
}
