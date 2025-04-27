<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 24 Apr 2023 20:22:54 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Profile;

use App\Actions\HumanResources\Timesheet\UI\IndexTimesheets;
use App\Actions\OrgAction;
use App\Actions\Traits\Actions\WithActionButtons;
use App\Actions\UI\WithInertia;
use App\Enums\HumanResources\Employee\EmployeeTypeEnum;
use App\Enums\UI\SysAdmin\ProfileTabsEnum;
use App\Http\Resources\HumanResources\TimesheetsResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowProfileIndexTimesheets extends OrgAction
{
    use AsAction;
    use WithInertia;
    use WithActionButtons;

    public function asController(ActionRequest $request): ?LengthAwarePaginator
    {
        $this->initialisationFromGroup(group(), $request)->withTab(ProfileTabsEnum::values());

        $employees = $request->user()->employees;
        if (count($employees) > 0) {
            $employee = $employees->first();
            if ($employee->type->value == EmployeeTypeEnum::EMPLOYEE->value) {
                return IndexTimesheets::run($employee, null, ProfileTabsEnum::TIMESHEETS->value);
            }
        }
        return null;
    }

    public function jsonResponse(?LengthAwarePaginator $timesheets): AnonymousResourceCollection
    {
        if ($timesheets == null) {
            return TimesheetsResource::collection(collect());
        }
        return TimesheetsResource::collection($timesheets);
    }
}
