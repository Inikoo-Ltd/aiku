<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 Oct 2026 20:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureTaskSession;

use App\Actions\OrgAction;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionActivityTypeEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionTimeEditReasonEnum;
use App\Models\Production\ManufactureTaskSession;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class EditManufactureTaskSessionTimes extends OrgAction
{
    use WithManufacturePayrollLock;

    /**
     * A finished session whose operator forgot to press Start or Stop gets the times really worked. The first
     * times recorded are kept beside the new ones, and hours, rate and pay are worked out again for them.
     */
    public function handle(ManufactureTaskSession $session, array $modelData): ManufactureTaskSession
    {
        return DB::transaction(function () use ($session, $modelData) {
            $session   = ManufactureTaskSession::lockForUpdate()->find($session->id);
            $startedAt = Carbon::parse($modelData['started_at'])->setTimezone(config('app.timezone'))->startOfMinute();
            $endedAt   = Carbon::parse($modelData['ended_at'])->setTimezone(config('app.timezone'))->startOfMinute();
            $reason    = ManufactureTaskSessionTimeEditReasonEnum::from($modelData['reason']);

            if ($session->state != ManufactureTaskSessionStateEnum::CLOSED) {
                $this->refuse('started_at', __('Only a finished session can have its times changed'));
            }
            if ($this->payrollEditor && $this->payrollEditor->id == $session->user_id) {
                $this->refuse('started_at', __('You cannot change the times of your own session, ask a supervisor'));
            }
            if ($endedAt->lessThanOrEqualTo($startedAt)) {
                $this->refuse('ended_at', __('The end must be after the start'));
            }
            if ($endedAt->greaterThan(now())) {
                $this->refuse('ended_at', __('The end cannot be in the future'));
            }

            $overlapping = ManufactureTaskSession::overlapping($session->user_id, $startedAt, $endedAt, $session->id);
            if ($overlapping) {
                $this->refuse('started_at', __('This overlaps their :what from :from to :to', [
                    'what' => $overlapping->manufactureTask?->name ?? $overlapping->activityLabel(),
                    'from' => $this->localTime($overlapping->started_at),
                    'to'   => $overlapping->ended_at ? $this->localTime($overlapping->ended_at) : __('now'),
                ]));
            }

            if (empty($modelData['outside_shift_confirmed']) && ($shiftWarning = $this->outsideShiftWarning($session, $startedAt, $endedAt))) {
                $this->refuse('outside_shift', $shiftWarning);
            }

            return $this->changeInPayWeek($session->production, $session, $session->user_id, 'times', [$session->started_at, $session->ended_at, $startedAt, $endedAt], ['started_at', 'ended_at', 'hours', 'band_code', 'pay'], $modelData['lock_reason'] ?? null, fn () => $this->saveTimes($session, $startedAt, $endedAt, $reason, $modelData));
        });
    }

    private function saveTimes(ManufactureTaskSession $session, Carbon $startedAt, Carbon $endedAt, ManufactureTaskSessionTimeEditReasonEnum $reason, array $modelData): ManufactureTaskSession
    {
        $session->fill([
            'original_started_at' => $session->original_started_at ?? $session->started_at,
            'original_ended_at'   => $session->original_ended_at ?? $session->ended_at,
            'started_at'          => $startedAt,
            'ended_at'            => $endedAt,
            'time_edit_reason'    => $reason,
            'time_edit_note'      => $modelData['note'] ?? null,
            'time_edited_by'      => $this->payrollEditor?->id,
            'time_edited_at'      => now(),
        ]);
        if ($session->left_open_after_shift_at) {
            $session->shift_end_resolved_at ??= now();
        }

        CalculateManufactureTaskSessionBreakMinutes::run($session, $endedAt);
        if (empty($modelData['impossible_rate_confirmed']) && CloseManufactureTaskSession::make()->isImpossibleRate($session)) {
            $this->refuse('impossible_rate', __(':made in :minutes min is :rate per hour against a target of :target, save again if these times are right', [
                'made'    => (float) $session->quantity_made,
                'minutes' => round($session->paidHours() * 60, 1),
                'rate'    => number_format((float) $session->quantity_made / max($session->paidHours(), 1 / 3600)),
                'target'  => (float) $session->standard_rate,
            ]));
        }

        return $this->recalculate($session);
    }

    /**
     * Breaks are taken off again within the new window, and the step's target is the one it has now, as
     * when a target given later repays the week's sessions (HELP-4091).
     */
    public function recalculate(ManufactureTaskSession $session): ManufactureTaskSession
    {
        CalculateManufactureTaskSessionBreakMinutes::run($session, $session->ended_at);

        if ($session->activity_type == ManufactureTaskSessionActivityTypeEnum::PRODUCTION && !$session->isNonProductive()) {
            if ($session->standard_rate === null) {
                $session->standard_rate = $session->recipeStandardRate();
            }
            $session->is_under_target = CloseManufactureTaskSession::make()->isUnderTarget($session);
            $session->save();
        }

        return CalculateManufactureTaskSessionPay::run($session);
    }

    private function outsideShiftWarning(ManufactureTaskSession $session, Carbon $startedAt, Carbon $endedAt): ?string
    {
        $window = $session->employee?->shiftWindowOn($startedAt);
        if ($window === null) {
            return null;
        }

        $worker = $session->user?->contact_name ?: $session->user?->username;
        if ($window === []) {
            return __(':worker had no shift on :day, save again to keep these times', ['worker' => $worker, 'day' => $this->localTime($startedAt, 'l j M')]);
        }

        [$shiftStart, $shiftEnd] = $window;
        if ($startedAt->greaterThanOrEqualTo($shiftStart) && $endedAt->lessThanOrEqualTo($shiftEnd)) {
            return null;
        }

        return __('This is outside :worker\'s shift of :from to :to, save again to keep these times', [
            'worker' => $worker,
            'from'   => $shiftStart->format('H:i'),
            'to'     => $shiftEnd->format('H:i'),
        ]);
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
            'started_at'              => ['required', 'date'],
            'ended_at'                => ['required', 'date'],
            'reason'                  => ['required', Rule::in(array_map(fn (ManufactureTaskSessionTimeEditReasonEnum $reason) => $reason->value, ManufactureTaskSessionTimeEditReasonEnum::supervisorReasons()))],
            'note'                    => ['nullable', 'string', 'max:1000', 'required_if:reason,'.ManufactureTaskSessionTimeEditReasonEnum::OTHER->value],
            'outside_shift_confirmed'   => ['sometimes', 'boolean'],
            'impossible_rate_confirmed' => ['sometimes', 'boolean'],
            'lock_reason'               => ['sometimes', 'nullable', 'string', 'max:1000'],
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

    public function action(ManufactureTaskSession $session, array $modelData, ?User $editor = null): ManufactureTaskSession
    {
        $this->asAction      = true;
        $this->payrollEditor = $editor;
        $this->initialisationFromProduction($session->production, $modelData);

        return $this->handle($session, $this->validatedData);
    }

    public function asController(ManufactureTaskSession $manufactureTaskSession, ActionRequest $request): ManufactureTaskSession
    {
        $this->payrollEditor = $request->user();
        $this->initialisationFromProduction($manufactureTaskSession->production, $request);

        return $this->handle($manufactureTaskSession, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
