<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Nov 2026 16:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufacturePayrollAdjustment;

use App\Actions\Production\ManufactureTaskSession\WithManufacturePayrollLock;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Models\HumanResources\Employee;
use App\Models\Production\ManufacturePayrollAdjustment;
use App\Models\Production\ManufactureTaskSession;
use App\Models\Production\Production;
use App\Models\SysAdmin\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * An artisan is paid for the hours clocked, so adding, deleting or correcting a clocking of a week already locked for
 * payroll goes through the same lock as the changes on the factory floor: the accountant or the production manager,
 * with a reason, recorded and paid in the next pay run. HR keeps changing the weeks still open, and the clockings of
 * employees who never worked on a factory floor.
 */
class ChangeArtisanClocking
{
    use AsObject;
    use WithManufacturePayrollLock;

    /**
     * @param  array<int, string>  $fields  the attributes of $model recorded before and after
     */
    /**
     * A change made inside another one, like the clockings an approved request adds, is measured and recorded by the outer one.
     */
    private static int $depth = 0;

    public function handle(Employee $employee, Carbon $date, Model $model, array $fields, ?User $editor, ?string $lockReason, Closure $apply): mixed
    {
        if (self::$depth > 0) {
            return $apply();
        }

        $session = self::artisanSession($employee, $date);
        if (!$session) {
            if ($editor && !$editor->authTo(["human-resources.$employee->organisation_id.edit", "org-supervisor.$employee->organisation_id.human-resources"])) {
                throw ValidationException::withMessages(['payroll_lock' => __('Only HR can change the clockings of this employee')]);
            }

            return $apply();
        }

        $this->payrollEditor = $editor;
        self::$depth++;
        try {
            return $this->changeInPayWeek($session->production, $model, $session->user_id, 'clocking', [Carbon::parse($date->toDateString())], $fields, $lockReason, $apply);
        } finally {
            self::$depth--;
        }
    }

    /**
     * Whether a clocking of this day can only be changed by the accountant or the production manager now.
     */
    public static function isLocked(Employee $employee, Carbon $date): bool
    {
        $production = self::artisanSession($employee, $date)?->production;

        return $production && Carbon::parse($date->toDateString())->lessThan(ManufacturePayrollAdjustment::lockedBefore($production));
    }

    /**
     * Payroll pays the clockings of the artisans who worked on the tablet in the eight weeks before (HydrateManufactureArtisanDays::rosterEmployeeIds), so only those are locked.
     */
    private static function artisanSession(Employee $employee, Carbon $date): ?ManufactureTaskSession
    {
        return ManufactureTaskSession::where('employee_id', $employee->id)
            ->where('state', ManufactureTaskSessionStateEnum::CLOSED)
            ->whereNotNull('user_id')
            ->where('ended_at', '>=', Carbon::parse($date->toDateString())->subDay()->subWeeks(8))
            ->latest('id')
            ->first();
    }

    protected function canChangeOpenWeek(User $user, Production $production): bool
    {
        return ManufacturePayrollAdjustment::canChangeOpenWeek($user, $production) || $user->authTo([
            "human-resources.$production->organisation_id.edit",
            "org-supervisor.$production->organisation_id.human-resources",
        ]);
    }
}
