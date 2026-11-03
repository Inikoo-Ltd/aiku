<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Nov 2026 09:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact;

use App\Actions\Production\ManufactureTaskSession\CalculateManufactureTaskSessionPay;
use App\Actions\Production\ManufactureTaskSession\CloseManufactureTaskSession;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionActivityTypeEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Models\Production\Artefact;
use App\Models\Production\ArtefactManufactureTask;
use App\Models\Production\ArtefactStepTarget;
use App\Models\Production\ManufacturePayrollAdjustment;
use App\Models\Production\ManufactureTaskSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Every change of a step's target is kept with the moment it applies from (HELP-4461). A date applies from the
 * start of that day in the organisation's time zone; without one a changed target applies from the start of today and
 * a target given to a step without one from the start of this pay week, so the week's sessions made without one are
 * paid against it (HELP-4091). Closed sessions on the step from then on are worked out again: target, under target,
 * band and pay. Weeks locked for payroll (HELP-4464) are settled: a target never starts in one and never repays its
 * sessions; an underpaid earlier week is corrected by the accountant.
 */
class SetArtefactStepTarget
{
    use AsAction;

    public function handle(Artefact $artefact, int $manufactureTaskId, float|int|string|null $standardRate, ?string $effectiveFromDate = null): ?ArtefactStepTarget
    {
        $step = ArtefactManufactureTask::where('artefact_id', $artefact->id)
            ->where('manufacture_task_id', $manufactureTaskId)
            ->firstOrFail();

        $standardRate = $standardRate === null || $standardRate === '' ? null : (float) $standardRate;
        $current      = $step->standard_rate === null ? null : (float) $step->standard_rate;

        if ($effectiveFromDate === null && $current === $standardRate) {
            return null;
        }

        $effectiveFrom = match (true) {
            $effectiveFromDate !== null => self::startOfDay($artefact, $effectiveFromDate),
            $current !== null           => self::startOfDay($artefact, now(self::timezone($artefact))->toDateString()),
            default                     => $this->untargetedSince($artefact, $manufactureTaskId),
        };

        return DB::transaction(function () use ($artefact, $step, $manufactureTaskId, $standardRate, $effectiveFrom, $current) {
            $isUnrecorded = !ArtefactStepTarget::where('artefact_id', $artefact->id)->where('manufacture_task_id', $manufactureTaskId)->exists();
            if ($current !== null && $isUnrecorded) {
                ArtefactStepTarget::create([
                    'artefact_id'         => $artefact->id,
                    'manufacture_task_id' => $manufactureTaskId,
                    'standard_rate'       => $current,
                ]);
            }

            $target = ArtefactStepTarget::create([
                'artefact_id'         => $artefact->id,
                'manufacture_task_id' => $manufactureTaskId,
                'standard_rate'       => $standardRate,
                'effective_from'      => $effectiveFrom,
                'user_id'             => auth()->id(),
            ]);

            $step->update(['standard_rate' => ArtefactStepTarget::rateAt($artefact->id, $manufactureTaskId, now())['standard_rate']]);

            $target->update(['data' => $this->recalculate($artefact, $manufactureTaskId, $effectiveFrom)]);

            return $target;
        });
    }

    /**
     * A step taken off the recipe stops carrying its target from now on, so the step added back later starts without
     * one instead of paying against the target it had before.
     */
    public static function recordRemoval(Artefact $artefact, int $manufactureTaskId): void
    {
        $hasHistory = ArtefactStepTarget::where('artefact_id', $artefact->id)->where('manufacture_task_id', $manufactureTaskId)->exists();
        $current    = $hasHistory
            ? ArtefactStepTarget::rateAt($artefact->id, $manufactureTaskId, now())['standard_rate']
            : ArtefactManufactureTask::where('artefact_id', $artefact->id)->where('manufacture_task_id', $manufactureTaskId)->value('standard_rate');

        if ($current === null) {
            return;
        }

        if (!$hasHistory) {
            ArtefactStepTarget::create(['artefact_id' => $artefact->id, 'manufacture_task_id' => $manufactureTaskId, 'standard_rate' => $current]);
        }

        ArtefactStepTarget::create([
            'artefact_id'         => $artefact->id,
            'manufacture_task_id' => $manufactureTaskId,
            'standard_rate'       => null,
            'effective_from'      => now(),
            'user_id'             => auth()->id(),
        ]);
    }

    /**
     * Back to the start of this pay week, but not over a change made since, such as the target being taken off.
     */
    private function untargetedSince(Artefact $artefact, int $manufactureTaskId): Carbon
    {
        $weekStart  = now(self::timezone($artefact))->startOfWeek(Carbon::MONDAY)->utc();
        $lastChange = ArtefactStepTarget::where('artefact_id', $artefact->id)->where('manufacture_task_id', $manufactureTaskId)->max('effective_from');

        return $lastChange ? $weekStart->max(Carbon::parse($lastChange)) : $weekStart;
    }

    /**
     * The first day a target can start from: the first day of the oldest pay week not yet locked for payroll.
     */
    public static function firstOpenDay(Artefact $artefact): string
    {
        return ManufacturePayrollAdjustment::lockedBefore($artefact->production)->toDateString();
    }

    /**
     * What saving would do to sessions already worked, without saving it.
     *
     * @return array{sessions: int, artisans: array<int, string>}
     */
    public function preview(Artefact $artefact, int $manufactureTaskId, float|int|string|null $standardRate, ?string $effectiveFromDate): array
    {
        DB::beginTransaction();
        try {
            return $this->handle($artefact, $manufactureTaskId, $standardRate, $effectiveFromDate)?->data ?? ['sessions' => 0, 'artisans' => []];
        } finally {
            DB::rollBack();
        }
    }

    /**
     * The warning shown before a back-dated target is saved, or null when no session worked is touched.
     */
    public function confirmationMessage(Artefact $artefact, int $manufactureTaskId, float|int|string|null $standardRate, string $effectiveFromDate): ?string
    {
        $preview = $this->preview($artefact, $manufactureTaskId, $standardRate, $effectiveFromDate);
        if (!$preview['sessions']) {
            return null;
        }

        $message = __('Sessions already worked on this step from :date are worked out again against this target (per hour, under target, pay band and pay): :count.', [
            'date'  => Carbon::parse($effectiveFromDate)->format('j M Y'),
            'count' => $preview['sessions'],
        ]);
        if ($preview['artisans']) {
            $message .= ' '.__('Artisans: :artisans.', ['artisans' => implode(', ', $preview['artisans'])]);
        }

        return $message;
    }

    public static function timezone(Artefact $artefact): string
    {
        return $artefact->organisation->timezone?->name ?? config('app.timezone');
    }

    public static function startOfDay(Artefact $artefact, string $date): Carbon
    {
        $timezone = self::timezone($artefact);
        $start    = Carbon::parse($date, $timezone)->startOfDay();

        if ($start->isAfter(now($timezone))) {
            throw ValidationException::withMessages(['effective_from' => __('A target can apply from today or an earlier day, not a later one')]);
        }

        $firstOpenDay = self::firstOpenDay($artefact);
        if ($start->toDateString() < $firstOpenDay) {
            throw ValidationException::withMessages(['effective_from' => __('Pay weeks before :date are closed and stay as they were paid, so a target can start from :date at the earliest. If someone was underpaid before then, tell the accountant.', [
                'date' => Carbon::parse($firstOpenDay)->format('j M Y'),
            ])]);
        }

        return $start->utc();
    }

    /**
     * @return array{sessions: int, artisans: array<int, string>}
     */
    private function recalculate(Artefact $artefact, int $manufactureTaskId, Carbon $from): array
    {
        $onArtefact = fn ($query) => $query->where('artefact_id', $artefact->id);

        $sessions = ManufactureTaskSession::query()
            ->where('state', ManufactureTaskSessionStateEnum::CLOSED)
            ->where('activity_type', ManufactureTaskSessionActivityTypeEnum::PRODUCTION)
            ->where('manufacture_task_id', $manufactureTaskId)
            ->where('started_at', '>=', $from)
            ->where('ended_at', '>=', ManufacturePayrollAdjustment::lockedBefore($artefact->production))
            ->where(fn ($query) => $query
                ->whereHas('jobOrderItemTask.jobOrderItem', $onArtefact)
                ->orWhereHas('shares.jobOrderItemTask.jobOrderItem', $onArtefact))
            ->with('employee')
            ->orderBy('started_at')
            ->get();

        foreach ($sessions as $session) {
            $session->standard_rate   = $session->recipeStandardRate();
            $session->is_under_target = CloseManufactureTaskSession::make()->isUnderTarget($session);
            $session->save();
            CalculateManufactureTaskSessionPay::run($session);
        }

        return [
            'sessions' => $sessions->count(),
            'artisans' => $sessions->map(fn (ManufactureTaskSession $session) => $session->employee?->contact_name)->filter()->unique()->values()->all(),
        ];
    }
}
