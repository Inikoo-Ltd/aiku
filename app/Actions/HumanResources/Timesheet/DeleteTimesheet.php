<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 31 Jul 2026 07:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\Timesheet;

use App\Actions\HumanResources\Employee\Hydrators\EmployeeHydrateClockings;
use App\Actions\HumanResources\Employee\Hydrators\EmployeeHydrateTimeTracker;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Guest\Hydrators\GuestHydrateClockings;
use App\Actions\SysAdmin\Guest\Hydrators\GuestHydrateTimeTracker;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Models\HumanResources\Clocking;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\Timesheet;
use App\Models\HumanResources\TimeTracker;
use App\Models\SysAdmin\Guest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class DeleteTimesheet extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    private string $organisationSlug;

    private ?string $employeeSlug = null;

    public function handle(Timesheet $timesheet): void
    {
        $subject                = $timesheet->subject;
        $this->organisationSlug = $timesheet->organisation->slug;
        $this->employeeSlug     = $subject instanceof Employee ? $subject->slug : null;

        DB::transaction(function () use ($timesheet, $subject) {
            Clocking::withTrashed()
                ->where('timesheet_id', $timesheet->id)
                ->update(['time_tracker_id' => null]);

            TimeTracker::withTrashed()
                ->where('timesheet_id', $timesheet->id)
                ->get()
                ->each(fn (TimeTracker $timeTracker) => $timeTracker->forceDelete());

            Clocking::withTrashed()
                ->where('timesheet_id', $timesheet->id)
                ->get()
                ->each(fn (Clocking $clocking) => $clocking->forceDelete());

            $timesheet->delete();

            if ($subject instanceof Employee) {
                EmployeeHydrateClockings::dispatch($subject);
                EmployeeHydrateTimeTracker::dispatch($subject);
            } elseif ($subject instanceof Guest) {
                GuestHydrateClockings::dispatch($subject);
                GuestHydrateTimeTracker::dispatch($subject);
            }
        });
    }

    public function asController(Timesheet $timesheet, ActionRequest $request): void
    {
        $this->initialisation($timesheet->organisation, $request);

        $this->handle($timesheet);
    }

    public function jsonResponse(): JsonResponse
    {
        return response()->json(['success' => true]);
    }

    public function htmlResponse(): RedirectResponse
    {
        if ($this->employeeSlug) {
            return Redirect::route('grp.org.hr.employees.show.timesheets.index', [$this->organisationSlug, $this->employeeSlug]);
        }

        return Redirect::route('grp.org.hr.timesheets.index', $this->organisationSlug);
    }
}
