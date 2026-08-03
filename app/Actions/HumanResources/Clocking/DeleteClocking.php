<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 24 Jun 2023 13:12:05 Malaysia Time, Pantai Lembeng, Bali, Id
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\Clocking;

use App\Actions\HumanResources\Employee\Hydrators\EmployeeHydrateClockings;
use App\Actions\HumanResources\Timesheet\Hydrators\TimesheetHydrateTimeTrackers;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Guest\Hydrators\GuestHydrateClockings;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Enums\HumanResources\TimeTracker\TimeTrackerStatusEnum;
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

class DeleteClocking extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(Clocking $clocking): Clocking
    {
        DB::transaction(function () use ($clocking) {
            $timesheet = $clocking->timesheet;
            $subject   = $clocking->subject;

            // A time tracker anchored on this clocking keeps existing, just loses the
            // start/end pointer (and the time that pointer supplied) rather than being
            // deleted itself. Losing its end also reopens it, since a "closed" tracker
            // with no end clocking is no longer meaningful.
            TimeTracker::where('start_clocking_id', $clocking->id)->update([
                'start_clocking_id' => null,
                'starts_at'         => null,
            ]);

            TimeTracker::where('end_clocking_id', $clocking->id)->update([
                'end_clocking_id' => null,
                'ends_at'         => null,
                'duration'        => null,
                'status'          => TimeTrackerStatusEnum::OPEN,
            ]);

            $clocking->forceDelete();

            if ($timesheet) {
                $this->rehydrateTimesheet($timesheet->fresh());
            }

            if ($subject instanceof Employee) {
                EmployeeHydrateClockings::dispatch($subject);
            } elseif ($subject instanceof Guest) {
                GuestHydrateClockings::dispatch($subject);
            }
        });

        return $clocking;
    }

    /**
     * Timesheet start_at/end_at are only ever set when trackers are created/closed, so
     * nulling a tracker's start/end above would otherwise leave them stale.
     */
    private function rehydrateTimesheet(Timesheet $timesheet): void
    {
        $remaining = $timesheet->timeTrackers()->orderBy('starts_at')->get();

        $timesheet->update([
            'start_at' => $remaining->first()?->starts_at,
            'end_at'   => $remaining->filter(fn (TimeTracker $timeTracker) => $timeTracker->ends_at)->last()?->ends_at,
        ]);

        TimesheetHydrateTimeTrackers::run($timesheet);
    }

    public function asController(Clocking $clocking, ActionRequest $request): Clocking
    {
        $this->initialisation($clocking->organisation, $request);

        return $this->handle($clocking);
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
