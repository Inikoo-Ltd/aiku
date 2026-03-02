<?php

namespace App\Actions\HumanResources\Holiday;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Actions\Traits\WithExportData;
use App\Exports\HumanResources\HolidaysExport;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportHolidays extends OrgAction
{
    use WithHumanResourcesAuthorisation;
    use WithExportData;

    public function rules(): array
    {
        return [
            'year' => ['required', 'integer'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'format' => ['required', 'string', 'in:csv,xlsx'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function handle(array $modelData): BinaryFileResponse
    {
        $year = (int) $modelData['year'];
        $month = isset($modelData['month']) ? (int) $modelData['month'] : null;
        $format = $modelData['format'];

        $export = new HolidaysExport(
            organisationId: $this->organisation->id,
            year: $year,
            month: $month
        );

        return $this->export($export, 'holidays', $format);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Organisation $organisation, ActionRequest $request): BinaryFileResponse
    {
        $this->initialisation($organisation, $request);

        return $this->handle($this->validatedData);
    }
}
