<?php

namespace App\Actions\HumanResources\Employee;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;

class GetDepartments extends OrgAction
{
    use WithHumanResourcesAuthorisation;

    /**
     * @param Organisation $organisation
     * @param ActionRequest $request
     *
     * @return JsonResponse
     */
    public function handle(Organisation $organisation, ActionRequest $request): JsonResponse
    {
        $departments = $organisation->jobPositions()
            ->whereNotNull('department')
            ->distinct('department')
            ->orderBy('department')
            ->pluck('department')
            ->map(fn ($dept) => [
                'value' => $dept,
                'label' => $dept,
            ]);

        return response()->json($departments);
    }

    public function asController(Organisation $organisation, ActionRequest $request): JsonResponse
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, $request);
    }
}
