<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Fri, 26 Aug 2022 00:49:45 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */

namespace App\Actions\HumanResources\Employee;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Models\HumanResources\Employee;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;

class GeneratePinEmployee extends OrgAction
{
    use WithActionUpdate;
    use WithHumanResourcesAuthorisation;

    public function handle(Employee $employee): string
    {
        return SetEmployeePin::make()->action($employee, false, true);
    }

    public function jsonResponse(string $pin): JsonResponse
    {
        return response()->json([
            'pin' => $pin
        ]);
    }

    public function asController(Organisation $organisation, Employee $employee, ActionRequest $request): string
    {
        $this->initialisation($employee->organisation, $request);

        return $this->handle($employee);
    }
}
