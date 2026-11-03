<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Oct 2026 22:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureTaskSession;

use App\Actions\Production\ManufactureArtisanDay\HydrateManufactureArtisanDays;
use App\Enums\Production\Artisan\ArtisanPayTypeEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionActivityTypeEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\Timesheet;
use App\Models\Production\ManufacturePayBand;
use App\Models\Production\ManufacturePayrollAdjustment;
use App\Models\Production\ManufactureTaskSession;
use App\Models\Production\Production;
use App\Models\SysAdmin\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Payroll per artisan: the hours clocked (clocked breaks left out) at the National Living Wage (band 0 rate), plus the
 * hours of each band at what the band pays above it, plus the Guru bonus on every clocked hour of a Guru day.
 *
 * Each day the tablet time is split into bands by the band CalculateManufactureTaskSessionPay stored on each session, and
 * the clocked time not on the tablet is band 0. Tablet time beyond the clocked time is not paid: it is taken off the
 * jobs that ended last, and the day is flagged. Hourly artisans are paid their clocked hours at band 0, apart from machine
 * maintenance and development, which keep their own rate; their other tablet time is shown for information only. Hours are rounded once, on the breakdown lines, so the bands add up exactly to the
 * clocked hours.
 */
class GetManufacturePayroll
{
    use AsObject;

    public const array BANDS = [
        '0'  => 'band_0',
        '1'  => 'band_1',
        '2'  => 'band_2',
        '3'  => 'band_3',
        'D'  => 'band_d',
        'DG' => 'band_dg',
    ];

    private Collection $payBands;

    /**
     * @return array<int, array<string, mixed>> one row per artisan, each with its days and breakdown lines
     */
    public function handle(Production $production, Carbon $from, Carbon $to, ?int $userId = null): array
    {
        $this->payBands = ManufacturePayBand::where('production_id', $production->id)->orderByDesc('effective_from')->get();

        $sessions = ManufactureTaskSession::where('production_id', $production->id)
            ->where('state', ManufactureTaskSessionStateEnum::CLOSED)
            ->whereBetween('ended_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->with(['user', 'employee', 'manufactureTask', 'jobOrder', 'jobOrderItemTask.jobOrder', 'jobOrderItemTask.jobOrderItem.artefact', 'shares.jobOrderItemTask.jobOrderItem.artefact'])
            ->orderBy('ended_at')
            ->get();

        $sessionEmployeeIds = $sessions->pluck('employee_id')->filter()->unique()->values()->all();
        $clockings          = HydrateManufactureArtisanDays::clockings(array_values(array_unique([...$sessionEmployeeIds, ...HydrateManufactureArtisanDays::rosterEmployeeIds($production, $from, $to)])), $from->toDateString(), $to->toDateString());
        $clockedOnly        = Employee::whereIn('id', $clockings->pluck('subject_id')->unique()->diff($sessionEmployeeIds)->values())
            ->whereNotNull('user_id')
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->with('user')
            ->get();
        $sessionsByUser     = $sessions->groupBy('user_id');

        $artisans = [];
        foreach ($sessionsByUser->keys()->merge($clockedOnly->pluck('user_id'))->unique() as $workerId) {
            $userSessions  = $sessionsByUser->get($workerId, collect());
            $userEmployees = $clockedOnly->where('user_id', $workerId);

            $artisans[$workerId] = $this->artisan(
                $userSessions->first()?->user ?? $userEmployees->first()->user,
                $userSessions->first(fn (ManufactureTaskSession $session) => $session->employee)?->employee ?? $userEmployees->first(),
                $userSessions->pluck('employee_id')->filter()->merge($userEmployees->pluck('id'))->unique()->all(),
                $userSessions,
                $clockings
            );
        }

        return collect($this->withPayrollAdjustments($production, $from, $to, $userId, $artisans))
            ->sortBy('worker', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * A change made in the period to a week locked before it is paid or deducted in this pay run. Changes to the period's
     * own weeks that the accountant has not approved yet are counted so the artisan is highlighted.
     *
     * @param  array<int, array<string, mixed>>  $artisans  keyed by user id
     * @return array<int, array<string, mixed>>
     */
    private function withPayrollAdjustments(Production $production, Carbon $from, Carbon $to, ?int $userId, array $artisans): array
    {
        [$settleStart, $settleEnd, $earlierThan] = ManufacturePayrollAdjustment::settledIn($from, $to);

        $adjustments = ManufacturePayrollAdjustment::where('production_id', $production->id)
            ->whereNotNull('user_id')
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->where(fn ($query) => $query
                ->where(fn ($query) => $query->whereBetween('created_at', [$settleStart, $settleEnd])->where('pay_week', '<', $earlierThan))
                ->orWhere(fn ($query) => $query->whereBetween('pay_week', [$from->copy()->startOfWeek(Carbon::MONDAY)->toDateString(), $to->toDateString()])->where('needs_approval', true)->whereNull('approved_at')))
            ->with('user')
            ->get();

        foreach ($artisans as $workerId => $row) {
            $artisans[$workerId] += ['adjustment_hours' => 0.0, 'adjustment_amount' => 0.0, 'unapproved_changes' => 0];
        }

        foreach ($adjustments->groupBy('user_id') as $workerId => $workerAdjustments) {
            $settled = $workerAdjustments->filter(fn (ManufacturePayrollAdjustment $adjustment) => $adjustment->created_at->between($settleStart, $settleEnd) && $adjustment->pay_week->toDateString() < $earlierThan);
            $row     = $artisans[$workerId] ?? $this->artisanWithoutSessions($production, $workerAdjustments->first()->user);

            $row['adjustment_hours']   = round($settled->sum(fn (ManufacturePayrollAdjustment $adjustment) => (float) $adjustment->hours_delta), 2);
            $row['adjustment_amount']  = round($settled->sum(fn (ManufacturePayrollAdjustment $adjustment) => (float) $adjustment->amount_delta), 2);
            $row['unapproved_changes'] = $workerAdjustments->filter(fn (ManufacturePayrollAdjustment $adjustment) => $adjustment->isAwaitingApproval())->count();
            $row['total']              = round($row['total'] + $row['adjustment_amount'], 2);

            $artisans[$workerId] = $row;
        }

        return $artisans;
    }

    /**
     * An artisan paid only an adjustment to an earlier week in this pay run.
     */
    private function artisanWithoutSessions(Production $production, User $user): array
    {
        $employee = ManufactureTaskSession::where('production_id', $production->id)->where('user_id', $user->id)->whereNotNull('employee_id')->latest('id')->first()?->employee;

        return array_fill_keys($this->amountKeys(), 0.0) + [
            'user_id'           => $user->id,
            'employee_id'       => $employee?->id,
            'worker'            => $user->contact_name ?: $user->username,
            'artisan_pay_type'  => $employee?->artisan_pay_type,
            'is_agency_worker'  => (bool) $employee?->is_agency_worker,
            'is_guru'           => false,
            'excluded_sessions' => 0,
            'days'              => [],
            'lines'             => [],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $artisans
     */
    public function grandTotal(array $artisans): array
    {
        $total = ['worker' => __('Total')];
        foreach ($this->amountKeys() as $key) {
            $total[$key] = round(array_sum(array_column($artisans, $key)), 2);
        }
        $total['excluded_sessions'] = array_sum(array_column($artisans, 'excluded_sessions'));

        return $total;
    }

    public function amountKeys(): array
    {
        $keys = ['working_hours', 'working_amount', 'tablet_hours', 'not_on_tablet_hours', 'tablet_over_clocked_hours'];
        foreach (self::BANDS as $key) {
            $keys[] = $key.'_hours';
            $keys[] = $key.'_amount';
        }

        return [...$keys, 'guru_hours', 'guru_amount', 'adjustment_hours', 'adjustment_amount', 'total'];
    }

    /**
     * @param  array<int, int>  $employeeIds  the worker's employee records, whose clockings are paid
     * @param  Collection<string, Timesheet>  $clockings
     */
    private function artisan(User $user, ?Employee $employee, array $employeeIds, Collection $sessions, Collection $clockings): array
    {
        $clockedSeconds = [];
        $openClockings  = [];
        foreach ($clockings as $timesheet) {
            if (in_array($timesheet->subject_id, $employeeIds)) {
                $date                  = $timesheet->date->toDateString();
                $clockedSeconds[$date] = ($clockedSeconds[$date] ?? 0) + $timesheet->working_duration;
                $openClockings[$date]  = ($openClockings[$date] ?? 0) + $timesheet->number_open_time_trackers;
            }
        }

        $sessionsByDate = $sessions->groupBy(fn (ManufactureTaskSession $session) => $session->ended_at->toDateString());
        $dates          = collect(array_keys($clockedSeconds))->merge($sessionsByDate->keys())->unique()->sort()->values();

        $lines    = collect();
        $dayFacts = [];
        foreach ($dates as $date) {
            $daySessions = $sessionsByDate->get($date, collect());
            $tabletHours = $daySessions->reject(fn (ManufactureTaskSession $session) => $this->isWaitingForTimesCheck($session))
                ->mapWithKeys(fn (ManufactureTaskSession $session) => [$session->id => (float) ($session->hours ?? $session->paidHours())]);
            $clocked     = round(($clockedSeconds[$date] ?? 0) / 3600, 6);
            $tablet      = round($tabletHours->sum(), 6);
            $isHourly    = ($daySessions->first()?->artisan_pay_type ?? $employee?->artisan_pay_type) == ArtisanPayTypeEnum::HOURLY;
            $paidTablet  = $isHourly ? $tabletHours->only($daySessions->filter(fn (ManufactureTaskSession $session) => $this->isFlatRate($session))->pluck('id')->all()) : $tabletHours;
            $paidHours   = $this->capToClocked($daySessions, $paidTablet, $clocked);

            $lines = $lines->merge(
                $daySessions
                    ->groupBy(fn (ManufactureTaskSession $session) => implode('|', [
                        $session->jobOrderItemTask?->job_order_item_id ?? 'job-order-'.$session->job_order_id,
                        $session->manufacture_task_id ?? $session->activity_type->value,
                        $session->band_code ?? '0',
                        $this->rate('0', $session->ended_at),
                        $this->rate($session->band_code ?? '0', $session->ended_at),
                        $this->guruRate($session->employee, $session->ended_at),
                        $this->isWaitingForTimesCheck($session) ? 'excluded' : 'counted',
                        $this->isFlatRate($session) ? 'flat' : 'performance',
                    ]))
                    ->map(fn (Collection $group) => $this->line($group, $paidHours, $isHourly && !$this->isFlatRate($group->first())))
                    ->values()
            );

            $clockedNotPaidOnTablet = max(0, $clocked - $paidTablet->sum());
            if ($clockedNotPaidOnTablet > 0.000001) {
                $lines->push($this->clockedLine($employee, $date, $clockedNotPaidOnTablet, $isHourly));
            }

            $dayFacts[$date] = [
                'date'                      => $date,
                'tablet_hours'              => round($tablet, 2),
                'not_on_tablet_hours'       => round(max(0, $clocked - $tablet), 2),
                'tablet_over_clocked_hours' => round(max(0, $tablet - $clocked), 2),
                'open_clockings'            => $openClockings[$date] ?? 0,
                'is_clocked'                => isset($clockedSeconds[$date]),
            ];
        }

        $counted = $this->allocateHours($lines->where('counted', true));

        $row = array_merge([
            'user_id'           => $user->id,
            'employee_id'       => $employee?->id,
            'worker'            => $user->contact_name ?: $user->username,
            'artisan_pay_type'  => $employee?->artisan_pay_type,
            'is_agency_worker'  => (bool) $employee?->is_agency_worker,
            'is_guru'           => $counted->contains(fn (array $line) => $line['guru_rate'] > 0),
            'excluded_sessions' => $lines->where('excluded', true)->sum('sessions'),
        ], $this->summary($counted, $dayFacts));

        $row['days'] = $counted->groupBy('date')->union(array_fill_keys(array_keys($dayFacts), collect()))
            ->map(fn (Collection $dayLines, string $date) => array_merge(['date' => $date], $this->summary($dayLines, [$date => $dayFacts[$date]]), [
                'open_clockings' => $dayFacts[$date]['open_clockings'],
                'is_clocked'     => $dayFacts[$date]['is_clocked'],
            ]))
            ->sortKeys()
            ->values()
            ->all();

        $row['lines'] = $counted->merge($lines->where('counted', false))
            ->sortBy([['date', 'asc'], ['is_clocked_line', 'asc'], ['job_order_reference', 'asc'], ['step', 'asc']])
            ->map(fn (array $line) => array_merge($line, [
                'hours'  => round($line['hours'], 2),
                'amount' => $line['counted'] ? round($line['hours'] * $line['band_rate'] + $line['hours'] * $line['guru_rate'], 2) : 0.0,
            ]))
            ->values()
            ->all();

        return $row;
    }

    /**
     * @param  array<string, array<string, mixed>>  $dayFacts
     */
    private function summary(Collection $countedLines, array $dayFacts): array
    {
        $summary = [
            'working_hours'             => round($countedLines->sum('hours'), 2),
            'working_amount'            => round($countedLines->sum(fn (array $line) => $line['hours'] * $line['nlw']), 2),
            'tablet_hours'              => round(array_sum(array_column($dayFacts, 'tablet_hours')), 2),
            'not_on_tablet_hours'       => round(array_sum(array_column($dayFacts, 'not_on_tablet_hours')), 2),
            'tablet_over_clocked_hours' => round(array_sum(array_column($dayFacts, 'tablet_over_clocked_hours')), 2),
        ];

        foreach (self::BANDS as $code => $key) {
            $bandLines              = $countedLines->where('band', (string) $code);
            $summary[$key.'_hours']  = round($bandLines->sum('hours'), 2);
            $summary[$key.'_amount'] = round($bandLines->sum(fn (array $line) => $line['hours'] * $line['above_nlw']), 2);
        }

        $guruLines              = $countedLines->where('guru_rate', '>', 0);
        $summary['guru_hours']  = round($guruLines->sum('hours'), 2);
        $summary['guru_amount'] = round($guruLines->sum(fn (array $line) => $line['hours'] * $line['guru_rate']), 2);
        $summary['total']       = round($summary['working_amount'] + array_sum(array_map(fn (string $key) => $summary[$key.'_amount'], self::BANDS)) + $summary['guru_amount'], 2);

        return $summary;
    }

    /**
     * Tablet time beyond the clocked time is taken off the jobs that ended last, which is where a job left running after
     * clocking out shows.
     *
     * @param  Collection<int, float>  $tabletHours  keyed by session id
     * @return Collection<int, float> the hours paid, keyed by session id
     */
    private function capToClocked(Collection $sessions, Collection $tabletHours, float $clocked): Collection
    {
        $paidHours = $tabletHours->collect();
        $excess    = $tabletHours->sum() - $clocked;

        foreach ($sessions->sortByDesc('ended_at') as $session) {
            if ($excess <= 0.000001) {
                break;
            }
            if (!$paidHours->has($session->id)) {
                continue;
            }

            $cut = min($paidHours[$session->id], $excess);
            $paidHours->put($session->id, $paidHours[$session->id] - $cut);
            $excess -= $cut;
        }

        return $paidHours;
    }

    /**
     * @param  Collection<int, float>  $paidHours  keyed by session id
     */
    private function line(Collection $sessions, Collection $paidHours, bool $isHourly): array
    {
        /** @var ManufactureTaskSession $session */
        $session  = $sessions->first();
        $band     = $session->band_code ?? '0';
        $nlw      = $this->rate('0', $session->ended_at);
        $excluded = $this->isWaitingForTimesCheck($session);
        $tablet   = $sessions->sum(fn (ManufactureTaskSession $session) => (float) ($session->hours ?? $session->paidHours()));
        $paid     = $sessions->sum(fn (ManufactureTaskSession $session) => $paidHours->get($session->id, 0.0));

        return [
            'date'                => $session->ended_at->toDateString(),
            'job_order_reference' => $session->jobOrder?->reference ?? $session->jobOrderItemTask?->jobOrder?->reference,
            'artefact_code'       => $session->artefactCode(),
            'step'                => $session->manufactureTask?->name ?? $session->activityLabel(),
            'band'                => $band,
            'sessions'            => $sessions->count(),
            'hours'               => $excluded || $isHourly ? $tablet : $paid,
            'not_paid_hours'      => $excluded || $isHourly ? 0.0 : round($tablet - $paid, 2),
            'units'               => round((float) $sessions->sum('quantity_made'), 2),
            'nlw'                 => $nlw,
            'band_rate'           => $this->rate($band, $session->ended_at),
            'above_nlw'           => max(0, $this->rate($band, $session->ended_at) - $nlw),
            'guru_rate'           => $this->guruRate($session->employee, $session->ended_at),
            'excluded'            => $excluded,
            'is_tablet_info'      => $isHourly && !$excluded,
            'is_clocked_line'     => false,
            'counted'             => !$excluded && !$isHourly,
        ];
    }

    /**
     * The clocked time paid at band 0: what was not on the tablet, or the whole day for an hourly artisan.
     */
    private function clockedLine(?Employee $employee, string $date, float $hours, bool $isHourly): array
    {
        $at  = Carbon::parse($date)->endOfDay();
        $nlw = $this->rate('0', $at);

        return [
            'date'                => $date,
            'job_order_reference' => null,
            'artefact_code'       => null,
            'step'                => $isHourly ? __('Clocked hours') : __('Not on tablet'),
            'band'                => '0',
            'sessions'            => 0,
            'hours'               => $hours,
            'not_paid_hours'      => 0.0,
            'units'               => 0.0,
            'nlw'                 => $nlw,
            'band_rate'           => $nlw,
            'above_nlw'           => 0.0,
            'guru_rate'           => $this->guruRate($employee, $at),
            'excluded'            => false,
            'is_tablet_info'      => false,
            'is_clocked_line'     => true,
            'counted'             => true,
        ];
    }

    /**
     * Rounds each line to hundredths of an hour, handing the leftover hundredths to the lines that lost most, so the lines add up to the rounded total.
     */
    private function allocateHours(Collection $lines): Collection
    {
        $lines      = $lines->values();
        $hundredths = $lines->map(fn (array $line) => $line['hours'] * 100)->all();
        $rounded    = array_map(fn (float $value) => (int) floor($value + 1e-6), $hundredths);
        $leftover   = (int) round(array_sum($hundredths)) - array_sum($rounded);

        $remainders = [];
        foreach ($hundredths as $index => $value) {
            $remainders[$index] = $value - $rounded[$index];
        }
        arsort($remainders);
        foreach (array_slice(array_keys($remainders), 0, max(0, $leftover)) as $index) {
            $rounded[$index]++;
        }

        return $lines->map(fn (array $line, int $index) => array_merge($line, ['hours' => $rounded[$index] / 100]));
    }

    private function rate(string $code, Carbon $at): float
    {
        return (float) ($this->payBands->first(
            fn (ManufacturePayBand $band) => $band->code === $code && $band->effective_from->lessThanOrEqualTo($at) && (!$band->effective_to || $band->effective_to->greaterThan($at))
        )?->hourly_rate ?? 0);
    }

    private function guruRate(?Employee $employee, Carbon $at): float
    {
        $date = $at->copy()->utc()->startOfDay();

        if (!$employee?->guru_since || $date->lessThan($employee->guru_since) || ($employee->guru_until && $date->greaterThan($employee->guru_until))) {
            return 0;
        }

        return $this->rate('GURU', $at);
    }

    /**
     * Machine maintenance (band 2, HELP-4441) and development (D or DG, HELP-4160) are paid at their own rate whatever the
     * artisan's pay type, so an hourly artisan keeps them; the rest of an hourly artisan's clocked time is band 0.
     */
    private function isFlatRate(ManufactureTaskSession $session): bool
    {
        return in_array($session->activity_type, [ManufactureTaskSessionActivityTypeEnum::MAINTENANCE, ManufactureTaskSessionActivityTypeEnum::DEVELOPMENT]);
    }

    /**
     * Left running past the end of the shift and nobody has checked its times yet: not paid until it is.
     */
    private function isWaitingForTimesCheck(ManufactureTaskSession $session): bool
    {
        return $session->job_order_item_task_id !== null
            && $session->left_open_after_shift_at !== null
            && $session->shift_end_resolved_at === null;
    }
}
