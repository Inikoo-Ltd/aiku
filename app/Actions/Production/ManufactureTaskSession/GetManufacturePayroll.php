<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Oct 2026 22:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureTaskSession;

use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Models\Production\ManufacturePayBand;
use App\Models\Production\ManufactureTaskSession;
use App\Models\Production\Production;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Payroll per artisan: working hours at the National Living Wage (band 0 rate), plus the hours of each band at what the band pays above it,
 * plus the Guru bonus. Hours are rounded once, on the breakdown lines, so the bands add up exactly to the working hours.
 * The band of each session is the one CalculateManufactureTaskSessionPay stored on it.
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
     * @return array<int, array<string, mixed>> one row per artisan, each with its breakdown lines
     */
    public function handle(Production $production, Carbon $from, Carbon $to, ?int $userId = null): array
    {
        $this->payBands = ManufacturePayBand::where('production_id', $production->id)->orderByDesc('effective_from')->get();

        return ManufactureTaskSession::where('production_id', $production->id)
            ->where('state', ManufactureTaskSessionStateEnum::CLOSED)
            ->whereBetween('ended_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->with(['user', 'employee', 'manufactureTask', 'jobOrder', 'jobOrderItemTask.jobOrder', 'jobOrderItemTask.jobOrderItem.artefact', 'shares.jobOrderItemTask.jobOrderItem.artefact'])
            ->orderBy('ended_at')
            ->get()
            ->groupBy('user_id')
            ->map(fn (Collection $sessions) => $this->artisan($sessions))
            ->sortBy('worker', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
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
        $keys = ['working_hours', 'working_amount'];
        foreach (self::BANDS as $key) {
            $keys[] = $key.'_hours';
            $keys[] = $key.'_amount';
        }

        return [...$keys, 'guru_hours', 'guru_amount', 'total'];
    }

    private function artisan(Collection $sessions): array
    {
        /** @var ManufactureTaskSession $first */
        $first    = $sessions->first();
        $employee = $sessions->first(fn (ManufactureTaskSession $session) => $session->employee)?->employee;

        $lines = $sessions
            ->groupBy(fn (ManufactureTaskSession $session) => implode('|', [
                $session->ended_at->toDateString(),
                $session->jobOrderItemTask?->job_order_item_id ?? 'job-order-'.$session->job_order_id,
                $session->manufacture_task_id ?? $session->activity_type->value,
                $session->band_code ?? '0',
                $this->rate('0', $session->ended_at),
                $this->rate($session->band_code ?? '0', $session->ended_at),
                $this->guruRate($session),
                $this->isWaitingForTimesCheck($session) ? 'excluded' : 'counted',
            ]))
            ->map(fn (Collection $group) => $this->line($group))
            ->values();

        $counted = $this->allocateHours($lines->where('excluded', false));

        $row = [
            'user_id'           => $first->user_id,
            'employee_id'       => $employee?->id,
            'worker'            => $first->user->contact_name ?: $first->user->username,
            'artisan_pay_type'  => $employee?->artisan_pay_type,
            'is_guru'           => $counted->contains(fn (array $line) => $line['guru_rate'] > 0),
            'excluded_sessions' => $lines->where('excluded', true)->sum('sessions'),
            'working_hours'     => round($counted->sum('hours'), 2),
            'working_amount'    => round($counted->sum(fn (array $line) => $line['hours'] * $line['nlw']), 2),
        ];

        foreach (self::BANDS as $code => $key) {
            $bandLines            = $counted->where('band', (string) $code);
            $row[$key.'_hours']   = round($bandLines->sum('hours'), 2);
            $row[$key.'_amount']  = round($bandLines->sum(fn (array $line) => $line['hours'] * $line['above_nlw']), 2);
        }

        $guruLines           = $counted->where('guru_rate', '>', 0);
        $row['guru_hours']   = round($guruLines->sum('hours'), 2);
        $row['guru_amount']  = round($guruLines->sum(fn (array $line) => $line['hours'] * $line['guru_rate']), 2);
        $row['total']        = round($row['working_amount'] + array_sum(array_map(fn (string $key) => $row[$key.'_amount'], self::BANDS)) + $row['guru_amount'], 2);

        $row['lines'] = $counted->merge($lines->where('excluded', true))
            ->sortBy([['date', 'asc'], ['job_order_reference', 'asc'], ['step', 'asc']])
            ->map(fn (array $line) => array_merge($line, [
                'hours'  => round($line['hours'], 2),
                'amount' => round($line['hours'] * $line['band_rate'] + $line['hours'] * $line['guru_rate'], 2),
            ]))
            ->values()
            ->all();

        return $row;
    }

    private function line(Collection $sessions): array
    {
        /** @var ManufactureTaskSession $session */
        $session = $sessions->first();
        $band    = $session->band_code ?? '0';
        $nlw     = $this->rate('0', $session->ended_at);

        return [
            'date'                => $session->ended_at->toDateString(),
            'job_order_reference' => $session->jobOrder?->reference ?? $session->jobOrderItemTask?->jobOrder?->reference,
            'artefact_code'       => $session->artefactCode(),
            'step'                => $session->manufactureTask?->name ?? $session->activityLabel(),
            'band'                => $band,
            'sessions'            => $sessions->count(),
            'hours'               => $sessions->sum(fn (ManufactureTaskSession $session) => (float) ($session->hours ?? $session->paidHours())),
            'units'               => round((float) $sessions->sum('quantity_made'), 2),
            'nlw'                 => $nlw,
            'band_rate'           => $this->rate($band, $session->ended_at),
            'above_nlw'           => max(0, $this->rate($band, $session->ended_at) - $nlw),
            'guru_rate'           => $this->guruRate($session),
            'excluded'            => $this->isWaitingForTimesCheck($session),
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

    private function guruRate(ManufactureTaskSession $session): float
    {
        $employee = $session->employee;
        $date     = $session->ended_at->copy()->utc()->startOfDay();

        if (!$employee?->guru_since || $date->lessThan($employee->guru_since) || ($employee->guru_until && $date->greaterThan($employee->guru_until))) {
            return 0;
        }

        return $this->rate('GURU', $session->ended_at);
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
