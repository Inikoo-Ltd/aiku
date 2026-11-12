<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Nov 2026 18:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureTaskSession;

use App\Actions\OrgAction;
use App\Actions\Production\ManufactureArtisanDay\HydrateManufactureArtisanDays;
use App\Enums\Production\Artisan\ArtisanPayTypeEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionActivityTypeEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionTimeEditReasonEnum;
use App\Models\HumanResources\Employee;
use App\Models\Production\ManufactureTaskSession;
use App\Models\Production\Production;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class AddManufactureDevelopmentTime extends OrgAction
{
    use WithManufacturePayrollLock;

    /**
     * Development work done away from the tablet and kept on paper (HELP-4648) is added by a supervisor as a finished
     * session on no job order, so it is paid at the Development rate instead of as clocked time not on the tablet.
     *
     * @param  array{employee_id: int, started_at: string, ended_at: string, note: string, not_paid_confirmed?: bool, lock_reason?: string|null}  $modelData
     */
    public function handle(Production $production, array $modelData): ManufactureTaskSession
    {
        return DB::transaction(function () use ($production, $modelData) {
            $employee  = Employee::find($modelData['employee_id']);
            $worker    = $employee->getUser();
            $startedAt = Carbon::parse($modelData['started_at'])->setTimezone(config('app.timezone'))->startOfMinute();
            $endedAt   = Carbon::parse($modelData['ended_at'])->setTimezone(config('app.timezone'))->startOfMinute();

            if (!$production->paysDevelopment()) {
                $this->refuse('employee_id', __('This production has no Development rate'));
            }
            if (!HydrateManufactureArtisanDays::isArtisan($production, $employee)) {
                $this->refuse('employee_id', __('This person is not an artisan of this production'));
            }
            if (!$worker) {
                $this->refuse('employee_id', __('This artisan has no user for the tablet'));
            }
            if ($this->payrollEditor && $this->payrollEditor->id == $worker->id) {
                $this->refuse('employee_id', __('You cannot add time for yourself, ask a supervisor'));
            }
            if ($endedAt->lessThanOrEqualTo($startedAt)) {
                $this->refuse('ended_at', __('The end must be after the start'));
            }
            if ($endedAt->greaterThan(now())) {
                $this->refuse('ended_at', __('The end cannot be in the future'));
            }

            User::whereKey($worker->id)->lockForUpdate()->first();
            $overlapping = ManufactureTaskSession::overlapping($worker->id, $startedAt, $endedAt);
            if ($overlapping) {
                $this->refuse('started_at', __('This overlaps their :what from :from to :to', [
                    'what' => $overlapping->manufactureTask?->name ?? $overlapping->activityLabel(),
                    'from' => $this->localTime($overlapping->started_at),
                    'to'   => $overlapping->ended_at ? $this->localTime($overlapping->ended_at) : __('now'),
                ]));
            }

            if (empty($modelData['not_paid_confirmed']) && ($warning = $this->notPaidWarning($production, $employee, $worker, $startedAt, $endedAt))) {
                $this->refuse('not_paid', $warning);
            }

            $session = new ManufactureTaskSession([
                'group_id'         => $production->group_id,
                'organisation_id'  => $production->organisation_id,
                'production_id'    => $production->id,
                'activity_type'    => ManufactureTaskSessionActivityTypeEnum::DEVELOPMENT,
                'user_id'          => $worker->id,
                'employee_id'      => $employee->id,
                'artisan_pay_type' => $employee->artisan_pay_type ?? ArtisanPayTypeEnum::PIECE_RATE,
                'state'            => ManufactureTaskSessionStateEnum::CLOSED,
                'started_at'       => $startedAt,
                'ended_at'         => $endedAt,
                'time_edit_reason' => ManufactureTaskSessionTimeEditReasonEnum::OTHER,
                'time_edit_note'   => $modelData['note'],
                'non_productive_reason' => $modelData['note'],
                'time_edited_by'   => $this->payrollEditor?->id,
                'time_edited_at'   => now(),
            ]);

            return $this->changeInPayWeek($production, $session, $worker->id, 'development_time', [$startedAt, $endedAt], ['started_at', 'ended_at', 'hours', 'band_code', 'pay'], $modelData['lock_reason'] ?? null, function () use ($session) {
                $session->save();

                return EditManufactureTaskSessionTimes::make()->recalculate($session);
            });
        });
    }

    /**
     * Payroll pays the day's clocked time, or under a paid time rule the clocked time inside the shift, and never
     * tablet time beyond it: say so before the entry is saved. A day still clocked in is not checked, its time is not known yet.
     */
    private function notPaidWarning(Production $production, Employee $employee, User $worker, Carbon $startedAt, Carbon $endedAt): ?string
    {
        $name      = $worker->contact_name ?: $worker->username;
        $date      = $endedAt->toDateString();
        $clockings = HydrateManufactureArtisanDays::clockings([$employee->id], $date, $date);
        $timesheet = $clockings->first();
        $paidTime  = HydrateManufactureArtisanDays::make()->paidTime($production, $clockings)[$employee->id.'|'.$date] ?? null;

        if ($paidTime) {
            $shift = $paidTime['shift'];
            if (!$shift || $startedAt->lessThan($shift['start']) || $endedAt->greaterThan($shift['end'])) {
                return $shift
                    ? __('This is outside :worker\'s shift of :from to :to, and time outside the shift is only paid as approved overtime. Save again to keep these times', ['worker' => $name, 'from' => $this->localTime($shift['start'], 'H:i'), 'to' => $this->localTime($shift['end'], 'H:i')])
                    : __(':worker had no shift on :day, and time on a day off is only paid as approved overtime. Save again to keep these times', ['worker' => $name, 'day' => $this->localTime($endedAt, 'l j M')]);
            }
        }
        if ($timesheet?->number_open_time_trackers) {
            return null;
        }

        $paid   = (int) ($paidTime['paid'] ?? $timesheet?->working_duration ?? 0);
        $tablet = HydrateManufactureArtisanDays::artisanSessions($production)
            ->where('user_id', $worker->id)
            ->where('state', ManufactureTaskSessionStateEnum::CLOSED)
            ->whereDate('ended_at', $date)
            ->get()
            ->sum(fn (ManufactureTaskSession $session) => $session->paidHours() * 3600);
        $adding = $startedAt->diffInSeconds($endedAt);

        if ($tablet + $adding <= $paid + 60) {
            return null;
        }

        return __(':worker is paid for :paid clocked on :day and has :tablet on the tablet already. Adding :adding goes over it, and the time over it is not paid. Correct the clocking first, or save again to keep these times', [
            'worker' => $name,
            'paid'   => $this->hoursAndMinutes($paid),
            'day'    => $this->localTime($endedAt, 'l j M'),
            'tablet' => $this->hoursAndMinutes((int) round($tablet)),
            'adding' => $this->hoursAndMinutes((int) $adding),
        ]);
    }

    private function hoursAndMinutes(int $seconds): string
    {
        return sprintf('%d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60));
    }

    private function localTime(Carbon $moment, string $format = 'D j M H:i'): string
    {
        return $moment->copy()->setTimezone($this->organisation->timezone?->name ?? config('app.timezone'))->format($format);
    }

    private function refuse(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    public function rules(): array
    {
        return [
            'employee_id'           => ['required', 'integer', Rule::exists('employees', 'id')->where('organisation_id', $this->organisation->id)],
            'started_at'            => ['required', 'date'],
            'ended_at'              => ['required', 'date'],
            'note'                  => ['required', 'string', 'max:255'],
            'not_paid_confirmed' => ['sometimes', 'boolean'],
            'lock_reason'           => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.orchestrate",
            "accounting.{$this->organisation->id}.edit",
        ]);
    }

    public function action(Production $production, array $modelData, ?User $editor = null): ManufactureTaskSession
    {
        $this->asAction      = true;
        $this->payrollEditor = $editor;
        $this->initialisationFromProduction($production, $modelData);

        return $this->handle($production, $this->validatedData);
    }

    public function asController(Production $production, ActionRequest $request): ManufactureTaskSession
    {
        $this->payrollEditor = $request->user();
        $this->initialisationFromProduction($production, $request);

        return $this->handle($production, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
