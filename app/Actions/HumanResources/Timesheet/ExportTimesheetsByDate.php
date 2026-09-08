<?php

namespace App\Actions\HumanResources\Timesheet;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Enums\Helpers\Export\ExportTypeEnum;
use App\Exports\HumanResources\TimesheetsByDateExport;
use App\Models\SysAdmin\Organisation;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportTimesheetsByDate extends OrgAction
{
    use WithHumanResourcesAuthorisation;

    public function handle(Organisation $organisation, array $modelData): BinaryFileResponse
    {
        [$from, $to] = ResolveTimesheetDateRange::run();

        $type = $modelData['type'] ?? ExportTypeEnum::XLSX->value;

        $export = new TimesheetsByDateExport(
            $organisation->id,
            $modelData['employee_id'] ?? [],
            $from,
            $to,
            $organisation->timezone?->name ?? config('app.timezone')
        );

        $filename = now()->format('Y-m-d').'-timesheets-by-date-'.rand(111, 999).'.'.$type;

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
