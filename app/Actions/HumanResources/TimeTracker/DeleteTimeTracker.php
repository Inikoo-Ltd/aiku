<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 31 Jul 2026 09:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\TimeTracker;

use App\Actions\HumanResources\Employee\Hydrators\EmployeeHydrateClockings;
use App\Actions\HumanResources\Employee\Hydrators\EmployeeHydrateTimeTracker;
use App\Actions\HumanResources\Timesheet\Hydrators\TimesheetHydrateTimeTrackers;
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

class DeleteTimeTracker extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(TimeTracker $timeTracker): TimeTracker
    {
        DB::transaction(function () use ($timeTracker) {
            $timesheet = $timeTracker->timesheet;
            $subject   = $timeTracker->subject;

            $anchorIds = array_filter([$timeTracker->start_clocking_id, $timeTracker->end_clocking_id]);

            $clockingIds = Clocking::withTrashed()
                ->where(function ($query) use ($timeTracker, $anchorIds) {
                    $query->where('time_tracker_id', $timeTracker->id);
                    if ($anchorIds) {
                        $query->orWhereIn('id', $anchorIds);
                    }
                })
                ->pluck('id');

            Clocking::withTrashed()->whereIn('id', $clockingIds)->update(['time_tracker_id' => null]);

            $timeTracker->forceDelete();

            Clocking::withTrashed()
                ->whereIn('id', $clockingIds)
                ->get()
                ->each(fn (Clocking $clocking) => $clocking->forceDelete());

            if ($timesheet) {
                $this->rehydrateTimesheet($timesheet->fresh());
            }

            if ($subject instanceof Employee) {
                EmployeeHydrateTimeTracker::dispatch($subject);
                EmployeeHydrateClockings::dispatch($subject);
            } elseif ($subject instanceof Guest) {
                GuestHydrateTimeTracker::dispatch($subject);
                GuestHydrateClockings::dispatch($subject);
            }
        });

        return $timeTracker;
    }

    private function rehydrateTimesheet(Timesheet $timesheet): void
    {
        $remaining = $timesheet->timeTrackers()->orderBy('starts_at')->get();

        $timesheet->update([
            'start_at' => $remaining->first()?->starts_at,
            'end_at'   => $remaining->filter(fn (TimeTracker $timeTracker) => $timeTracker->ends_at)->last()?->ends_at,
        ]);

        TimesheetHydrateTimeTrackers::run($timesheet);
    }

    public function asController(TimeTracker $timeTracker, ActionRequest $request): TimeTracker
    {
        $timeTracker->loadMissing('timesheet');
        $this->initialisation($timeTracker->timesheet->organisation, $request);

        return $this->handle($timeTracker);
    }

    public function jsonResponse(): JsonResponse
    {
        return response()->json(['success' => true]);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
