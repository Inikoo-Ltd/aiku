<?php

namespace App\Actions\HumanResources\Leave;

use App\Enums\HumanResources\Leave\LeaveStatusEnum;
use App\Models\HumanResources\EmployeeLeaveBalance;
use App\Models\HumanResources\Leave;
use App\Services\HumanResources\LeaveTypeResolver;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class RecalculateEmployeeLeaveBalance
{
    use AsAction;

    public const array BUCKET_COLUMNS = [
        'annual'  => 'annual_used',
        'medical' => 'medical_used',
        'unpaid'  => 'unpaid_used',
    ];

    public const array ADJUSTMENT_COLUMNS = [
        'annual_used'  => 'annual_adjustment',
        'medical_used' => 'medical_adjustment',
        'unpaid_used'  => 'unpaid_adjustment',
    ];

    public string $commandSignature = 'leave:recalculate-balances {--execute : Write the recalculated counters. Without this flag, only a report is printed.}';

    public string $commandDescription = 'Find (and optionally fix) leave balances whose used counters differ from the approved leaves they should add up to.';

    public function handle(EmployeeLeaveBalance $balance): EmployeeLeaveBalance
    {
        $used = $this->usedDays($balance);

        if ($used !== null) {
            $balance->update($used);
        }

        return $balance;
    }

    public function forLeave(Leave $leave, bool $createFallback = true): ?EmployeeLeaveBalance
    {
        $balance = $this->balanceForLeave($leave, $createFallback);

        return $balance ? $this->handle($balance) : null;
    }

    public function balanceForLeave(Leave $leave, bool $createFallback = true): ?EmployeeLeaveBalance
    {
        $periodBalance = $this->owningPeriodBalance($this->periodBalances($leave->employee_id), $leave);

        if ($periodBalance) {
            return $periodBalance;
        }

        $fallback = $this->fallbackBalance($leave->employee_id);

        if ($fallback || !$createFallback) {
            return $fallback;
        }

        return EmployeeLeaveBalance::create([
            'employee_id'          => $leave->employee_id,
            'employee_contract_id' => null,
            'annual_used'          => 0,
            'medical_used'         => 0,
            'unpaid_used'          => 0,
        ]);
    }

    public static function deductionFor(Leave $leave): float
    {
        $value = $leave->leaveType?->deductionValue() ?? 1.0;

        if ($leave->is_half_day && $value === 1.0) {
            return 0.5;
        }

        return (float) $leave->duration_days * $value;
    }

    /**
     * @return array{annual_used: float, medical_used: float, unpaid_used: float}|null
     */
    public function usedDays(EmployeeLeaveBalance $balance): ?array
    {
        $recorded = $this->recordedDays($balance);

        if ($recorded === null) {
            return null;
        }

        return self::withAdjustments($recorded, $balance);
    }

    /**
     * @return array{annual_used: float, medical_used: float, unpaid_used: float}|null
     */
    public function recordedDays(EmployeeLeaveBalance $balance): ?array
    {
        $rows = EmployeeLeaveBalance::query()
            ->where('employee_id', $balance->employee_id)
            ->orderBy('id')
            ->get()
            ->map(fn (EmployeeLeaveBalance $row): array => self::layoutRow($row));

        return self::recordedByOwner(self::approvedLeaves($balance->employee_id), $rows)[(string) $balance->id] ?? null;
    }

    /**
     * @param  array{annual_used: float, medical_used: float, unpaid_used: float}  $recorded
     * @return array{annual_used: float, medical_used: float, unpaid_used: float}
     */
    public static function withAdjustments(array $recorded, EmployeeLeaveBalance $balance): array
    {
        $used = [];

        foreach (self::ADJUSTMENT_COLUMNS as $usedColumn => $adjustmentColumn) {
            $used[$usedColumn] = round($recorded[$usedColumn] + (float) $balance->getAttribute($adjustmentColumn), 2);
        }

        return $used;
    }

    /**
     * @return array{key: string, employee_contract_id: int|string|null, period_start: string|null, period_end: string|null}
     */
    public static function layoutRow(EmployeeLeaveBalance $balance): array
    {
        return [
            'key'                  => (string) $balance->id,
            'employee_contract_id' => $balance->employee_contract_id,
            'period_start'         => $balance->period_start?->toDateString(),
            'period_end'           => $balance->period_end?->toDateString(),
        ];
    }

    /**
     * @return Collection<int, Leave>
     */
    public static function approvedLeaves(int $employeeId): Collection
    {
        return Leave::query()
            ->where('employee_id', $employeeId)
            ->where('status', LeaveStatusEnum::APPROVED)
            ->with('leaveType')
            ->get();
    }

    /**
     * @param  Collection<int, Leave>  $leaves
     * @param  Collection<int, array{key: string, employee_contract_id: int|string|null, period_start: string|null, period_end: string|null}>  $rows
     * @return array<string, array{annual_used: float, medical_used: float, unpaid_used: float}>
     */
    public static function recordedByOwner(Collection $leaves, Collection $rows): array
    {
        $periodRows  = $rows->filter(fn (array $row): bool => $row['employee_contract_id'] !== null && $row['period_start'] !== null)->values();
        $fallbackKey = $rows->first(fn (array $row): bool => $row['employee_contract_id'] === null)['key'] ?? null;

        $recorded = [];
        foreach ($periodRows->pluck('key')->push($fallbackKey)->filter() as $key) {
            $recorded[$key] = array_fill_keys(array_values(self::BUCKET_COLUMNS), 0.0);
        }

        foreach ($leaves as $leave) {
            $day   = ($leave->start_date ?? now())->toDateString();
            $owner = $periodRows->first(
                fn (array $row): bool => $row['period_start'] <= $day && ($row['period_end'] === null || $row['period_end'] >= $day)
            )['key'] ?? $fallbackKey;

            $column = self::BUCKET_COLUMNS[LeaveTypeResolver::bucketFromLeaveType($leave->leaveType, $leave->type)] ?? null;

            if ($owner !== null && $column) {
                $recorded[$owner][$column] += self::deductionFor($leave);
            }
        }

        return array_map(fn (array $days): array => array_map(fn (float $value): float => round($value, 2), $days), $recorded);
    }

    /**
     * @return Collection<int, EmployeeLeaveBalance>
     */
    private function periodBalances(int $employeeId): Collection
    {
        return EmployeeLeaveBalance::query()
            ->where('employee_id', $employeeId)
            ->whereNotNull('employee_contract_id')
            ->whereNotNull('period_start')
            ->orderBy('id')
            ->get();
    }

    private function fallbackBalance(int $employeeId): ?EmployeeLeaveBalance
    {
        return EmployeeLeaveBalance::query()
            ->where('employee_id', $employeeId)
            ->whereNull('employee_contract_id')
            ->orderBy('id')
            ->first();
    }

    /**
     * @param  Collection<int, EmployeeLeaveBalance>  $periodBalances
     */
    private function owningPeriodBalance(Collection $periodBalances, Leave $leave): ?EmployeeLeaveBalance
    {
        /** @var CarbonInterface $startDate */
        $startDate = $leave->start_date ?? now();
        $day       = $startDate->toDateString();

        return $periodBalances->first(
            fn (EmployeeLeaveBalance $balance): bool => $balance->period_start->toDateString() <= $day
                && ($balance->period_end === null || $balance->period_end->toDateString() >= $day)
        );
    }

    public function asCommand(Command $command): int
    {
        $execute = (bool) $command->option('execute');
        $rows    = [];

        EmployeeLeaveBalance::query()->with('employee:id,slug,contact_name')->chunkById(500, function ($balances) use ($execute, &$rows) {
            foreach ($balances as $balance) {
                $used = $this->usedDays($balance);

                if ($used === null) {
                    continue;
                }

                $stored = [
                    'annual_used'  => round((float) $balance->annual_used, 2),
                    'medical_used' => round((float) $balance->medical_used, 2),
                    'unpaid_used'  => round((float) $balance->unpaid_used, 2),
                ];

                if ($stored === $used) {
                    continue;
                }

                $rows[] = [
                    $balance->id,
                    $balance->employee?->contact_name ?? $balance->employee_id,
                    $balance->period_start?->toDateString() ?? '-',
                    $stored['annual_used'].' → '.$used['annual_used'],
                    $stored['medical_used'].' → '.$used['medical_used'],
                    $stored['unpaid_used'].' → '.$used['unpaid_used'],
                ];

                if ($execute) {
                    $balance->update($used);
                }
            }
        });

        if ($rows === []) {
            $command->info('Every leave balance matches its approved leaves.');

            return 0;
        }

        $command->table(['Balance', 'Employee', 'Period start', 'Annual', 'Medical', 'Unpaid'], $rows);
        $command->info(count($rows).($execute ? ' leave balance(s) recalculated.' : ' leave balance(s) differ. Run again with --execute to write them.'));

        return 0;
    }
}
