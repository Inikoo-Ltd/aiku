<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 21 Sep 2023 11:34:12 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\Timesheet;

use App\Actions\HumanResources\Employee\Hydrators\EmployeeHydrateTimesheets;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Guest\Hydrators\GuestHydrateTimesheets;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Models\HumanResources\Timesheet;
use App\Models\SysAdmin\Guest;
use App\Models\HumanResources\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class StoreTimesheet extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(Employee|Guest $parent, array $modelData): Timesheet
    {
        data_set($modelData, 'group_id', $parent->group_id);
        data_set($modelData, 'organisation_id', $parent->organisation_id);
        data_set($modelData, 'subject_name', $parent->contact_name);

        /** @var Timesheet $timesheet */
        $timesheet = $parent->timesheets()->create($modelData);

        if ($parent instanceof Employee) {
            EmployeeHydrateTimesheets::dispatch($parent)->delay($this->hydratorsDelay);
        } else {
            GuestHydrateTimesheets::dispatch($parent)->delay($this->hydratorsDelay);
        }
        $timesheet->refresh();

        return $timesheet;
    }


    public function rules(): array
    {
        $rules = [
            'date' => ['required', 'date'],
        ];
        if (!$this->strict) {
            $rules['fetched_at'] = ['sometimes', 'date'];
            $rules['source_id']  = ['sometimes', 'string', 'max:255'];
        }

        return $rules;
    }


    public function action(Employee|Guest $parent, array $modelData, int $hydratorsDelay = 0, bool $strict = true): Timesheet
    {
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($parent->organisation, $modelData);

        return $this->handle($parent, $this->validatedData);
    }

    public function asController(Employee|Guest $parent, ActionRequest $request): Timesheet
    {
        return $this->handle($parent->organisation, $this->validatedData);
    }

    public function htmlResponse(Timesheet $timesheet): RedirectResponse
    {
        return Redirect::route('grp.org.hr.employees.show', $timesheet->id);
    }
}
