<?php

namespace App\Actions\HumanResources\Clocking;

use App\Models\HumanResources\Clocking;
use App\Models\HumanResources\TimeTracker;
use App\Models\HumanResources\Timesheet;
use App\Actions\HumanResources\Employee\Hydrators\EmployeeHydrateClockings;
use App\Actions\HumanResources\Timesheet\Hydrators\TimesheetHydrateTimeTrackers;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Guest\Hydrators\GuestHydrateClockings;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Models\HumanResources\Employee;
use App\Models\SysAdmin\Guest;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Support\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;

class UpdateClockingNotes extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(Clocking $clocking, ?string $notes, ?string $clockedAt): Clocking
    {
        $data = [
            'notes' => $notes,
        ];

        if ($clockedAt) {
            $data['clocked_at'] = $clocking->timesheet
                ? $clocking->timesheet->clockedAtOnTimesheetDate($clockedAt)
                : Carbon::parse($clockedAt, config('app.timezone'))->utc();
        }

        $clocking->update($data);
        $clocking->refresh();

        $this->updateTimeTrackerAndTimesheet($clocking);
        $this->hydrateSubjectClockings($clocking);

        return $clocking;
    }

    public function asController(Clocking $clocking, ActionRequest $request): Clocking
    {
        $this->initialisation($clocking->organisation, $request);
        $validated = $this->validatedData;

        return $this->handle(
            $clocking,
            $request->has('notes') ? ($validated['notes'] ?? null) : $clocking->notes,
            $validated['clocked_at'] ?? null
        );
    }

    public function jsonResponse(Clocking $clocking): JsonResponse
    {
        return response()->json([
            'success'  => true,
            'message'  => __('Notes updated successfully.'),
            'clocking' => $clocking
        ]);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }

    public function rules(): array
    {
        return [
            'notes'      => ['sometimes', 'nullable', 'string', 'max:500'],
            'clocked_at' => ['nullable', 'date'],
        ];
    }

    protected function updateTimeTrackerAndTimesheet(Clocking $clocking): void
    {
        if (!$clocking->timesheet_id) {
            return;
        }

        /** @var Timesheet $timesheet */
        $timesheet = $clocking->timesheet;

        if (!$timesheet) {
            return;
        }

        /** @var TimeTracker|null $timeTracker */
        $timeTracker = TimeTracker::query()
            ->where('timesheet_id', $timesheet->id)
            ->where(function ($q) use ($clocking) {
                $q->where('start_clocking_id', $clocking->id)
                    ->orWhere('end_clocking_id', $clocking->id);
            })
            ->first();

        if ($timeTracker) {
            if ($timeTracker->start_clocking_id === $clocking->id) {
                $timeTracker->starts_at = $clocking->clocked_at;
            }

            if ($timeTracker->end_clocking_id === $clocking->id) {
                $timeTracker->ends_at = $clocking->clocked_at;
            }

            $timeTracker->normaliseInterval();
        }

        $startAt = $timesheet->timeTrackers()->min('starts_at');
        $endAt   = $timesheet->timeTrackers()->max('ends_at');

        $timesheet->update([
            'start_at' => $startAt,
            'end_at'   => $endAt,
        ]);

        TimesheetHydrateTimeTrackers::run($timesheet->id);
    }

    protected function hydrateSubjectClockings(Clocking $clocking): void
    {
        $subject = $clocking->subject;

        if ($subject instanceof Employee) {
            EmployeeHydrateClockings::dispatch($subject);

            return;
        }

        if ($subject instanceof Guest) {
            GuestHydrateClockings::dispatch($subject);
        }
    }
}
