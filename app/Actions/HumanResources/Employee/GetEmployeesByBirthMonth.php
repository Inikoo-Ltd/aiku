<?php

namespace App\Actions\HumanResources\Employee;

use App\Actions\OrgAction;
use App\Http\Resources\HumanResources\EmployeeResource;
use App\Models\HumanResources\Employee;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;

class GetEmployeesByBirthMonth extends OrgAction
{
    public function handle(Organisation $organisation, ?int $month = null): AnonymousResourceCollection
    {
        $query = Employee::where('organisation_id', $organisation->id)
            ->whereNotNull('date_of_birth');

        if ($month !== null && $month >= 1 && $month <= 12) {
            $query->whereRaw("EXTRACT(MONTH FROM date_of_birth) = ?", [$month]);
        }

        $employees = $query->orderByRaw("EXTRACT(DAY FROM date_of_birth)")->get();

        return EmployeeResource::collection($employees);
    }

    public function asController(Organisation $organisation, ActionRequest $request): AnonymousResourceCollection
    {
        $this->initialisation($organisation, $request);

        $month = $request->input('month');

        return $this->handle($organisation, $month);
    }
}
