<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Nov 2026 09:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureOvertime;

use App\Actions\OrgAction;
use App\Actions\Production\ManufactureArtisanDay\HydrateManufactureArtisanDays;
use App\Actions\Production\ManufactureTaskSession\WithManufacturePayrollLock;
use App\Enums\Production\ManufactureOvertime\ManufactureOvertimeCompensationEnum;
use App\Enums\Production\ManufactureOvertime\ManufactureOvertimeDecisionEnum;
use App\Models\HumanResources\Employee;
use App\Models\Production\ManufactureArtisanDay;
use App\Models\Production\ManufactureOvertime;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * A manager's decision on overtime, for one artisan or several on the same day, each recorded on its own: planned times
 * (optional, entered ahead or after), approved as paid or as time off in lieu, or not authorised with a reason. Changes
 * pay, so it goes through the payroll lock.
 */
class SetManufactureOvertime extends OrgAction
{
    use WithManufacturePayrollLock;

    public function handle(Production $production, Employee $employee, string $date, User $user, array $modelData): ManufactureOvertime
    {
        $overtime = ManufactureOvertime::firstOrNew([
            'production_id' => $production->id,
            'employee_id'   => $employee->id,
            'date'          => $date,
        ]);
        $day = ManufactureArtisanDay::where('production_id', $production->id)->where('employee_id', $employee->id)->where('date', $date)->first();

        $decision     = ManufactureOvertimeDecisionEnum::from($modelData['decision']);
        $compensation = $decision == ManufactureOvertimeDecisionEnum::APPROVED ? ManufactureOvertimeCompensationEnum::from($modelData['compensation']) : null;
        [$plannedStart, $plannedEnd] = $this->plannedWindow($production, $date, $modelData);

        $expectedMinutes = $day?->expected_minutes ?? HydrateManufactureArtisanDays::expectedMinutes($employee->scheduledShiftOn($date));
        if ($expectedMinutes === null) {
            throw ValidationException::withMessages([
                'days' => __(':name has no working hours set: set them on the employee before deciding overtime', ['name' => $employee->contact_name]),
            ]);
        }
        $approvedMinutes = match (true) {
            $decision != ManufactureOvertimeDecisionEnum::APPROVED => null,
            $day?->clocked_minutes !== null                        => max(0, $day->clocked_minutes - $expectedMinutes),
            $plannedStart !== null                                 => (int) $plannedStart->diffInMinutes($plannedEnd),
            default                                                => 0,
        };

        $this->payrollEditor ??= $user;
        $workerId = $day?->user_id ?? $employee->user_id;
        $fields   = ['planned_start', 'planned_end', 'decision', 'compensation', 'toil_ratio', 'reason', 'approved_minutes'];

        $this->changeInPayWeek($production, $overtime, $workerId, 'overtime', [Carbon::parse($date)], $fields, $modelData['lock_reason'] ?? null, function () use ($overtime, $production, $user, $day, $decision, $compensation, $plannedStart, $plannedEnd, $modelData, $workerId, $expectedMinutes, $approvedMinutes) {
            $keepsRatio = $overtime->exists && $overtime->compensation == ManufactureOvertimeCompensationEnum::TOIL && $overtime->toil_ratio !== null;

            $overtime->fill([
                'group_id'                => $production->group_id,
                'organisation_id'         => $production->organisation_id,
                'user_id'                 => $workerId,
                'planned_start'           => $plannedStart,
                'planned_end'             => $plannedEnd,
                'decision'                => $decision,
                'compensation'            => $compensation,
                'toil_ratio'              => $compensation == ManufactureOvertimeCompensationEnum::TOIL ? ($keepsRatio ? $overtime->toil_ratio : ManufactureOvertime::toilRatio($production)) : null,
                'reason'                  => trim((string) Arr::get($modelData, 'reason')) ?: null,
                'decided_clocked_minutes' => $day?->clocked_minutes,
                'expected_minutes'        => $expectedMinutes,
                'approved_minutes'        => $approvedMinutes,
                'decided_by_user_id'      => $user->id,
                'decided_at'              => now(),
            ]);
            $overtime->save();
        });

        return $overtime;
    }

    /**
     * The planned times are the organisation's local times on the day; an end before the start runs past midnight.
     *
     * @return array{0: Carbon|null, 1: Carbon|null}
     */
    private function plannedWindow(Production $production, string $date, array $modelData): array
    {
        if (!Arr::get($modelData, 'planned_start') || !Arr::get($modelData, 'planned_end')) {
            return [null, null];
        }

        $timezone = $production->organisation->timezone?->name ?? config('app.timezone');
        $start    = Carbon::parse($date.' '.$modelData['planned_start'], $timezone);
        $end      = Carbon::parse($date.' '.$modelData['planned_end'], $timezone);
        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return [$start->utc(), $end->utc()];
    }

    public function rules(): array
    {
        return [
            'date'          => ['required_without:days', 'date_format:Y-m-d'],
            'days'          => [$this->asAction ? 'sometimes' : 'required', 'array', 'min:1', 'max:200'],
            'days.*.employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->where('organisation_id', $this->organisation->id)],
            'days.*.date'   => ['required', 'date_format:Y-m-d'],
            'decision'      => ['required', Rule::enum(ManufactureOvertimeDecisionEnum::class)],
            'compensation'  => ['required_if:decision,'.ManufactureOvertimeDecisionEnum::APPROVED->value, 'nullable', Rule::enum(ManufactureOvertimeCompensationEnum::class)],
            'reason'        => ['required_if:decision,'.ManufactureOvertimeDecisionEnum::NOT_APPROVED->value, 'nullable', 'string', 'max:1000'],
            'planned_start' => ['nullable', 'required_with:planned_end', 'date_format:H:i'],
            'planned_end'   => ['nullable', 'required_with:planned_start', 'date_format:H:i'],
            'lock_reason'   => ['sometimes', 'nullable', 'string', 'max:1000'],
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

    public function action(Production $production, Employee $employee, string $date, User $user, array $modelData): ManufactureOvertime
    {
        $this->asAction = true;
        $this->initialisationFromProduction($production, $modelData + ['date' => $date]);
        HydrateManufactureArtisanDays::run($production, Carbon::parse($date), Carbon::parse($date));

        $overtime = $this->handle($production, $employee, $date, $user, $this->validatedData);
        HydrateManufactureArtisanDays::run($production, Carbon::parse($date), Carbon::parse($date));

        return $overtime;
    }

    /**
     * Several artisans and days at once: the same times and decision, each recorded for its artisan and day.
     */
    public function asController(Organisation $organisation, Production $production, ActionRequest $request): void
    {
        $this->initialisationFromProduction($production, $request);
        $days      = collect($this->validatedData['days'] ?? []);
        $employees = Employee::whereIn('id', $days->pluck('employee_id'))->get()->keyBy('id');
        $from      = Carbon::parse($days->min('date'));
        $to        = Carbon::parse($days->max('date'));
        HydrateManufactureArtisanDays::run($production, $from, $to);

        DB::transaction(function () use ($production, $days, $employees, $request) {
            foreach ($days->unique(fn (array $day) => $day['employee_id'].'|'.$day['date']) as $day) {
                $this->handle($production, $employees[$day['employee_id']], $day['date'], $request->user(), $this->validatedData);
            }
        });

        HydrateManufactureArtisanDays::run($production, $from, $to);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
