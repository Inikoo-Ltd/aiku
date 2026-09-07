<?php

namespace App\Actions\HumanResources\Timesheet;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Enums\Helpers\Export\ExportTypeEnum;
use App\Enums\UI\HumanResources\TimesheetEmployeeViewEnum;
use App\Exports\HumanResources\TimesheetsByEmployeeExport;
use App\Models\SysAdmin\Organisation;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportTimesheetsByEmployee extends OrgAction
{
    use WithHumanResourcesAuthorisation;

    public function handle(Organisation $organisation, array $modelData): BinaryFileResponse
    {
        [$from, $to] = ResolveTimesheetDateRange::run();

        $view = TimesheetEmployeeViewEnum::tryFrom($modelData['view'] ?? '') ?? TimesheetEmployeeViewEnum::OVERVIEW;

        $rows = GetTimesheetsPerEmployeeRows::run(
            $organisation,
            $modelData['employee_id'] ?? null,
            $from,
            $to,
            $view
        );

        $type = $modelData['type'] ?? ExportTypeEnum::XLSX->value;

        $export = new TimesheetsByEmployeeExport(
            $rows,
            $view,
            $organisation->id,
            $modelData['employee_id'] ?? [],
            $from,
            $to,
            $organisation->timezone?->name ?? config('app.timezone')
        );

        $filename = now()->format('Y-m-d').'-timesheets-by-employee-'.$view->value.'-'.rand(111, 999).'.'.$type;

        return Excel::download(
            $export,
            $filename,
            $type === ExportTypeEnum::CSV->value ? ExcelFormat::CSV : ExcelFormat::XLSX
        );
    }

    public function rules(): array
    {
        return [
            'type'          => ['sometimes', Rule::in([ExportTypeEnum::XLSX->value, ExportTypeEnum::CSV->value])],
            'view'          => ['sometimes', Rule::in(TimesheetEmployeeViewEnum::values())],
            'employee_id'   => ['sometimes', 'array'],
            'employee_id.*' => ['integer'],
        ];
    }

    public function asController(Organisation $organisation, ActionRequest $request): BinaryFileResponse
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, $this->validatedData);
    }
}
