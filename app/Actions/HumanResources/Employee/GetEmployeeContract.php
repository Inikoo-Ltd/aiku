<?php

namespace App\Actions\HumanResources\Employee;

use App\Actions\OrgAction;
use App\Models\HumanResources\Employee;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;

class GetEmployeeContract extends OrgAction
{
    public function handle(Employee $employee): JsonResponse
    {
        $contract = $employee->getMedia('contracts')->sortByDesc('id')->first();

        return new JsonResponse([
            'employee_id' => $employee->id,
            'employee_name' => $employee->contact_name,
            'contract_start_date' => $employee->contract_start_date?->format('Y-m-d'),
            'contract_end_date' => $employee->contract_end_date?->format('Y-m-d'),
            'contract_document' => $contract ? [
                'id' => $contract->id,
                'file_name' => $contract->file_name,
                'mime_type' => $contract->mime_type,
                'url' => route('grp.media.show', ['media' => $contract->ulid]),
                'size' => $contract->size,
            ] : null,
        ]);
    }

    public function asController(Organisation $organisation, Employee $employee, ActionRequest $request): JsonResponse
    {
        $this->initialisation($organisation, $request);

        return $this->handle($employee);
    }
}
