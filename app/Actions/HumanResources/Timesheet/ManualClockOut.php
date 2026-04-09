<?php

/*
 * Author: Oggie Sutrisna
 * Desc :
 * Created: Thu, 09 Apr 2026 08:41:39 Singapore Standard Time, Singapore
 *
 */

namespace App\Actions\HumanResources\Timesheet;

use App\Actions\HumanResources\Clocking\StoreClocking;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\TimeTracker;
use App\Models\HumanResources\Timesheet;
use App\Models\HumanResources\Workplace;
use App\Models\SysAdmin\User;
use App\Models\SysAdmin\Guest;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class ManualClockOut extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;
    public function handle(Timesheet $timesheet, User $generator, ?string $time = null): void
    {
        /** @var TimeTracker|null $openTimeTracker */
        $openTimeTracker = $timesheet->timeTrackers()
            ->where('status', 'open')
            ->latest('id')
            ->first();

        if (!$openTimeTracker) {
            // Fallback: check by end_clocking_id just in case status is out of sync
            $openTimeTracker = $timesheet->timeTrackers()
                ->whereNull('end_clocking_id')
                ->latest('id')
                ->first();
        }

        if (!$openTimeTracker) {
            throw ValidationException::withMessages([
                'timesheet' => __('This timesheet does not have an open tracker.'),
            ]);
        }

        /** @var Workplace|null $workplace */
        $workplace = Workplace::find($openTimeTracker->workplace_id);
        if (!$workplace) {
            throw ValidationException::withMessages([
                'timesheet' => __('Unable to determine the workplace for this open tracker.'),
            ]);
        }

        /** @var Employee|Guest $subject */
        $subject = $timesheet->subject;

        $timezone = $this->organisation->timezone->name ?? 'UTC';

        if ($time) {
            $timeString = $time;
            if (strlen($time) > 8) {
                try {
                    $timeString = \Illuminate\Support\Carbon::parse($time)->toTimeString();
                } catch (\Exception) {
                    $timeString = substr($time, 0, 8);
                }
            }

            $clockedAt = $timesheet->date->copy()->shiftTimezone($timezone)->setTimeFromTimeString($timeString);
        } else {
            $clockedAt = now()->setTimezone($timezone);
        }

        StoreClocking::make()->action(
            generator: $generator,
            parent: $workplace,
            subject: $subject,
            modelData: [
                'clocked_at' => $clockedAt->toIso8601String(),
            ]
        );
    }

    public function asController(Organisation $organisation, Timesheet $timesheet, ActionRequest $request): void
    {
        $this->initialisation($organisation, $request);

        $this->handle($timesheet, $request->user(), $request->input('time'));
    }

    public function inEmployee(Organisation $organisation, Employee $employee, Timesheet $timesheet, ActionRequest $request): void
    {
        if ($timesheet->subject_type !== 'Employee' || $timesheet->subject_id !== $employee->id) {
            abort(404);
        }

        $this->initialisation($organisation, $request);

        $this->handle($timesheet, $request->user(), $request->input('time'));
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
