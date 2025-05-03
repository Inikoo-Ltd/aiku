<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Fri, 26 Aug 2022 00:49:45 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */

namespace App\Actions\HumanResources\Employee;

use App\Actions\OrgAction;
use App\Actions\Traits\WithPreparePositionsForValidation;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Http\Resources\HumanResources\EmployeeHanResource;
use App\Models\HumanResources\ClockingMachine;
use App\Models\HumanResources\Employee;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class ValidatePinEmployee extends OrgAction
{
    use WithActionUpdate;
    use WithPreparePositionsForValidation;

    protected bool $asAction = false;

    private ClockingMachine $clockingMachine;

    public function handle(Organisation $organisation, array $modalData)
    {
        return $organisation->employees()
        ->where('state', '!=', EmployeeStateEnum::LEFT->value)
        ->where('pin', Arr::get($modalData, 'pin'))->firstOrFail();
    }


    public function action(ClockingMachine $clockingMachine, array $modelData, bool $audit = true)
    {
        $this->clockingMachine = $clockingMachine;

        if (!$audit) {
            Employee::disableAuditing();
        }
        $this->asAction = true;

        $this->initialisation($clockingMachine->organisation, $modelData);

        return $this->handle($clockingMachine->organisation, $modelData);
    }

    public function rules(): array
    {
        return [
            'pin' => ['required', 'exists:employees,pin']
        ];
    }

    public function asController(ActionRequest $request)
    {
        /** @var ClockingMachine $clockingMachine */
        $clockingMachine = $request->user();
        $this->clockingMachine = $clockingMachine;

        $this->initialisation($clockingMachine->organisation, $request);

        return $this->handle($clockingMachine->organisation, $this->validatedData);
    }

    public function jsonResponse(Employee $employee): EmployeeHanResource
    {
        return new EmployeeHanResource($employee);
    }
}
