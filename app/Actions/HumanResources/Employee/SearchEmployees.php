<?php

namespace App\Actions\HumanResources\Employee;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Http\Resources\HumanResources\EmployeeResource;
use App\Models\HumanResources\Employee;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;

class SearchEmployees extends OrgAction
{
    use WithHumanResourcesAuthorisation;

    /**
     * @param Organisation $organisation
     * @param ActionRequest $request
     *
     * @return AnonymousResourceCollection
     */
    public function handle(Organisation $organisation, ActionRequest $request): AnonymousResourceCollection
    {
        $search = $request->input('search');
        $department = $request->input('department');
        $limit = min($request->input('limit', 10), 50); // Max 50 results

        $query = Employee::where('organisation_id', $organisation->id)
            ->where('state', 'working');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereWith('contact_name', $search)
                    ->orWhereWith('work_email', $search)
                    ->orWhereHas('jobPositions', function ($subQuery) use ($search) {
                        $subQuery->whereWith('department', $search)
                            ->orWhereWith('name', $search);
                    });
            });
        }

        if ($department) {
            $query->whereHas('jobPositions', function ($q) use ($department) {
                $q->where('department', $department);
            });
        }

        $employees = $query->orderBy('contact_name')
            ->limit($limit)
            ->get();

        return EmployeeResource::collection($employees);
    }

    public function asController(Organisation $organisation, ActionRequest $request): AnonymousResourceCollection
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, $request);
    }
}
