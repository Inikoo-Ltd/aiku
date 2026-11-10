<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Nov 2026 10:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureTaskSession;

use App\Enums\SysAdmin\User\UserNotificationEnum;
use App\Actions\SysAdmin\User\NotifyUserOnChosenChannels;
use App\Models\Production\ManufacturePayrollAdjustment;
use App\Models\Production\Production;
use App\Models\SysAdmin\User;
use App\Notifications\ManufacturePayrollChangedNotification;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Hours, quantities and pay of a pay week locked for payroll change only by the accountant or the production manager,
 * with a reason. Each such change is recorded with what it did to the artisan's pay, to be settled in the next pay run.
 */
trait WithManufacturePayrollLock
{
    protected ?User $payrollEditor = null;

    /**
     * @param  array<int, Carbon|null>  $moments  every time the change touches, before and after it
     * @param  array<int, string>  $fields  the attributes of $model recorded before and after
     */
    protected function changeInPayWeek(Production $production, Model $model, ?int $workerId, string $change, array $moments, array $fields, ?string $lockReason, Closure $apply): mixed
    {
        $moments      = array_values(array_filter($moments));
        $lockedBefore = ManufacturePayrollAdjustment::lockedBefore($production);
        $earliest     = $moments ? min($moments) : null;

        if (!$earliest || $earliest->greaterThanOrEqualTo($lockedBefore)) {
            if ($this->payrollEditor && !$this->canChangeOpenWeek($this->payrollEditor, $production)) {
                throw ValidationException::withMessages(['payroll_lock' => __('Only a supervisor or the production manager can change a week not yet locked for payroll')]);
            }

            return $apply();
        }

        if (!$this->payrollEditor || !ManufacturePayrollAdjustment::canChangeLockedWeek($this->payrollEditor, $production)) {
            throw ValidationException::withMessages(['payroll_lock' => __('This week is locked for payroll, only the accountant or the production manager can change it')]);
        }
        if (trim((string) $lockReason) === '') {
            throw ValidationException::withMessages(['lock_reason' => __('Give the reason for changing a week locked for payroll')]);
        }

        return DB::transaction(fn () => $this->changeLockedWeek($production, $model, $workerId, $change, $earliest, $lockedBefore, $fields, $lockReason, $apply));
    }

    /**
     * The worker's row is locked first, so two changes to the same artisan at once each measure only their own difference.
     */
    private function changeLockedWeek(Production $production, Model $model, ?int $workerId, string $change, Carbon $earliest, Carbon $lockedBefore, array $fields, string $lockReason, Closure $apply): mixed
    {
        if ($workerId) {
            User::whereKey($workerId)->lockForUpdate()->first();
        }

        $from   = $earliest->copy()->subDay()->startOfDay();
        $to     = $lockedBefore->copy()->subDay()->endOfDay();
        $before = $this->workerPayroll($production, $workerId, $from, $to);
        $was    = Arr::only($model->fresh()?->attributesToArray() ?? $model->attributesToArray(), $fields);

        $result = $apply();

        $after = $this->workerPayroll($production, $workerId, $from, $to);
        $now   = Arr::only($model->fresh()?->attributesToArray() ?? [], $fields);
        $diff  = array_values(array_filter($fields, fn (string $field) => ($was[$field] ?? null) != ($now[$field] ?? null)));

        $adjustment = ManufacturePayrollAdjustment::create([
            'group_id'           => $production->group_id,
            'organisation_id'    => $production->organisation_id,
            'production_id'      => $production->id,
            'user_id'            => $workerId,
            'model_type'         => $model->getMorphClass(),
            'model_id'           => $model->getKey(),
            'change'             => $change,
            'pay_week'           => ManufacturePayrollAdjustment::payWeekOf($production, $earliest),
            'before'             => Arr::only($was, $diff),
            'after'              => Arr::only($now, $diff),
            'reason'             => trim($lockReason),
            'hours_delta'        => round($after['working_hours'] - $before['working_hours'], 2),
            'amount_delta'       => round($after['total'] - $before['total'], 2),
            'changed_by_user_id' => $this->payrollEditor->id,
            'needs_approval'     => !ManufacturePayrollAdjustment::isAccountant($this->payrollEditor, $production),
        ]);

        if ($adjustment->needs_approval) {
            DB::afterCommit(fn () => $this->tellAccountants($production, $adjustment));
        }

        return $result;
    }

    protected function canChangeOpenWeek(User $user, Production $production): bool
    {
        return ManufacturePayrollAdjustment::canChangeOpenWeek($user, $production);
    }

    /**
     * @return array{working_hours: float, total: float}
     */
    private function workerPayroll(Production $production, ?int $workerId, Carbon $from, Carbon $to): array
    {
        if (!$workerId || $to->lessThan($from)) {
            return ['working_hours' => 0.0, 'total' => 0.0];
        }

        $rows = GetManufacturePayroll::make()->handle($production, $from, $to, $workerId);

        return [
            'working_hours' => (float) array_sum(array_map(fn (array $row) => $row['working_hours'] + $row['weekend_overtime_hours'] + $row['toil_hours'], $rows)),
            'total'         => (float) array_sum(array_column($rows, 'total')),
        ];
    }

    private function tellAccountants(Production $production, ManufacturePayrollAdjustment $adjustment): void
    {
        $notification = new ManufacturePayrollChangedNotification($adjustment);
        User::permission(["accounting.$production->organisation_id.view"])
            ->where('status', true)
            ->get()
            ->filter(fn (User $user) => ManufacturePayrollAdjustment::isAccountant($user, $production))
            ->each(fn (User $user) => NotifyUserOnChosenChannels::run(
                $user,
                UserNotificationEnum::MANUFACTURE_PAYROLL_CHANGED,
                $notification,
                $notification->title(),
                $notification->body(),
                $notification->url(),
            ));
    }
}
