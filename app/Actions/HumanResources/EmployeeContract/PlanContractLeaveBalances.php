<?php

namespace App\Actions\HumanResources\EmployeeContract;

use App\Actions\HumanResources\Leave\GenerateEmployeeLeaveBalance;
use App\Actions\HumanResources\Leave\RecalculateEmployeeLeaveBalance;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\EmployeeContract;
use App\Models\HumanResources\EmployeeLeaveBalance;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsObject;

class PlanContractLeaveBalances
{
    use AsObject;

    public const string CARRY = 'carry';

    public const string RECORDED = 'recorded';

    public const string NEW_CONTRACT = 'new';

    public const string NEW_BALANCE_KEY = 'new-balance';

    public const string NEW_UNLINKED_KEY = 'new-unlinked';

    private const array COUNTERS = ['annual_used', 'medical_used', 'unpaid_used'];

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>|null  $choices
     * @return array{requires_confirmation: bool, missing_choices: array<int, string>, entries: array<int, array<string, mixed>>, writes: array<int, array<string, mixed>>}
     */
    public function forStore(Employee $employee, array $data, ?array $choices = null): array
    {
        $contract = new EmployeeContract([
            'employee_id'       => $employee->id,
            'start_date'        => $data['start_date'],
            'end_date'          => $data['end_date'] ?? null,
            'annual_leave_days' => $data['annual_leave_days'] ?? 10.0,
        ]);

        $before     = $this->currentRows($employee->id);
        $after      = $before->map(fn (array $row): array => $row);
        $choiceKeys = [];
        $linked     = $this->linkableBalance($employee->id, $data['link_balance_id'] ?? null);

        if ($linked) {
            $after      = $this->linkRow($after, (string) $linked->id, self::NEW_CONTRACT, $contract);
            $choiceKeys = [(string) $linked->id];
        } else {
            $periodStart = Carbon::parse($contract->start_date->toDateString());
            $after->push([
                'key'                  => self::NEW_BALANCE_KEY,
                'employee_contract_id' => self::NEW_CONTRACT,
                'period_start'         => $periodStart->toDateString(),
                'period_end'           => GenerateEmployeeLeaveBalance::make()->periodEnd($contract, $periodStart)?->toDateString(),
                'allowance'            => (float) $contract->annual_leave_days,
            ]);
        }

        return $this->plan($employee->id, $before, $after, $choiceKeys, [], $choices);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>|null  $choices
     * @return array{requires_confirmation: bool, missing_choices: array<int, string>, entries: array<int, array<string, mixed>>, writes: array<int, array<string, mixed>>}
     */
    public function forUpdate(EmployeeContract $contract, array $data, ?array $choices = null): array
    {
        $updated = $contract->replicate();
        $updated->forceFill($this->contractAttributes($data));

        $before     = $this->currentRows($contract->employee_id);
        $after      = $before->map(fn (array $row): array => $row);
        $ownKeys    = $before->where('employee_contract_id', $contract->id)->pluck('key')->all();
        $choiceKeys = $ownKeys;

        $datesChanged = $updated->start_date->toDateString() !== $contract->start_date->toDateString()
            || $updated->end_date?->toDateString() !== $contract->end_date?->toDateString();

        if ($datesChanged) {
            $periods = $this->yearlyPeriods($updated, $before->whereIn('key', $ownKeys)->values(), Carbon::parse($contract->start_date->toDateString()));
            $after   = $after->map(fn (array $row): array => isset($periods[$row['key']]) ? [...$row, ...$periods[$row['key']]] : $row);
        }

        $after = $after->map(
            fn (array $row): array => in_array($row['key'], $ownKeys, true) ? [...$row, 'allowance' => (float) $updated->annual_leave_days] : $row
        );

        $linked = $this->linkableBalance($contract->employee_id, $data['link_balance_id'] ?? null);

        if ($linked) {
            $after        = $this->linkRow($after, (string) $linked->id, $contract->id, $updated);
            $choiceKeys[] = (string) $linked->id;
        }

        return $this->plan($contract->employee_id, $before, $after, $choiceKeys, [], $choices);
    }

    /**
     * @param  array<string, string>|null  $choices
     * @return array{requires_confirmation: bool, missing_choices: array<int, string>, entries: array<int, array<string, mixed>>, writes: array<int, array<string, mixed>>}
     */
    public function forDelete(EmployeeContract $contract, ?array $choices = null): array
    {
        $before      = $this->currentRows($contract->employee_id);
        $deletedKeys = $before->where('employee_contract_id', $contract->id)->pluck('key')->all();
        $after       = $before->reject(fn (array $row): bool => in_array($row['key'], $deletedKeys, true))->values();

        if ($deletedKeys !== [] && !$after->contains(fn (array $row): bool => $row['employee_contract_id'] === null)) {
            $after->push([
                'key'                  => self::NEW_UNLINKED_KEY,
                'employee_contract_id' => null,
                'period_start'         => null,
                'period_end'           => null,
                'allowance'            => null,
            ]);
        }

        return $this->plan($contract->employee_id, $before, $after, [], $deletedKeys, $choices);
    }

    /**
     * @param  array{requires_confirmation: bool, missing_choices: array<int, string>}  $plan
     */
    public function ensureConfirmed(array $plan, bool $isConfirmed): void
    {
        if ($plan['requires_confirmation'] && !$isConfirmed) {
            throw ValidationException::withMessages([
                'balance_changes' => __('This change affects leave balances. Review and confirm them before saving.'),
            ]);
        }

        if ($plan['missing_choices'] !== []) {
            throw ValidationException::withMessages([
                'balance_choices' => __('Choose what happens to every affected leave balance before saving.'),
            ]);
        }
    }

    /**
     * @param  array{writes: array<int, array<string, mixed>>}  $plan
     */
    public function persist(array $plan, int $employeeId, ?int $contractId): void
    {
        foreach ($plan['writes'] as $write) {
            $attributes = $write['attributes'] ?? [];

            if (array_key_exists('employee_contract_id', $attributes) && $attributes['employee_contract_id'] === self::NEW_CONTRACT) {
                $attributes['employee_contract_id'] = $contractId;
            }

            match ($write['operation']) {
                'create' => EmployeeLeaveBalance::create([...$attributes, 'employee_id' => $employeeId]),
                'update' => EmployeeLeaveBalance::whereKey($write['id'])->update($attributes),
                'delete' => EmployeeLeaveBalance::whereKey($write['id'])->delete(),
            };
        }
    }

    /**
     * @param  array{requires_confirmation: bool, entries: array<int, array<string, mixed>>}  $plan
     * @return array{requires_confirmation: bool, entries: array<int, array<string, mixed>>}
     */
    public function preview(array $plan): array
    {
        return [
            'requires_confirmation' => $plan['requires_confirmation'],
            'entries'               => $plan['entries'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function contractAttributes(array $data): array
    {
        $attributes = [];

        if (isset($data['start_date'])) {
            $attributes['start_date'] = $data['start_date'];
        }

        if (array_key_exists('end_date', $data)) {
            $attributes['end_date'] = $data['end_date'];
        }

        if (isset($data['annual_leave_days'])) {
            $attributes['annual_leave_days'] = $data['annual_leave_days'];
        }

        if (array_key_exists('notes', $data)) {
            $attributes['notes'] = $data['notes'];
        }

        return $attributes;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $before
     * @param  Collection<int, array<string, mixed>>  $after
     * @param  array<int, string>  $choiceKeys
     * @param  array<int, string>  $deletedKeys
     * @param  array<string, string>|null  $choices
     * @return array{requires_confirmation: bool, missing_choices: array<int, string>, entries: array<int, array<string, mixed>>, writes: array<int, array<string, mixed>>}
     */
    private function plan(int $employeeId, Collection $before, Collection $after, array $choiceKeys, array $deletedKeys, ?array $choices): array
    {
        $leaves         = RecalculateEmployeeLeaveBalance::approvedLeaves($employeeId);
        $recordedBefore = RecalculateEmployeeLeaveBalance::recordedByOwner($leaves, $before);
        $recordedAfter  = RecalculateEmployeeLeaveBalance::recordedByOwner($leaves, $after);
        $beforeByKey    = $before->keyBy('key');
        $zero           = array_fill_keys(self::COUNTERS, 0.0);

        $entries        = [];
        $writes         = [];
        $missingChoices = [];
        $carriedIn      = [];

        foreach ($deletedKeys as $key) {
            $row      = $beforeByKey[$key];
            $recorded = $recordedBefore[$key] ?? $zero;
            $carry    = $this->combine($row['used'], $recorded, fn (float $used, float $leaves): float => max(0.0, $used - $leaves));
            $hasChoice = $carry !== $zero;
            $choice    = $this->choiceFor($key, $hasChoice, $choices, $missingChoices);

            $destination = $after->first(
                fn (array $candidate): bool => $candidate['employee_contract_id'] !== null
                    && $candidate['period_start'] !== null
                    && $row['period_start'] !== null
                    && $candidate['period_start'] <= $row['period_start']
                    && ($candidate['period_end'] === null || $candidate['period_end'] >= $row['period_start'])
            ) ?? $after->first(fn (array $candidate): bool => $candidate['employee_contract_id'] === null);

            if ($choice === self::CARRY && $destination) {
                $carriedIn[$destination['key']] = $this->combine($carriedIn[$destination['key']] ?? $zero, $carry, fn (float $total, float $extra): float => $total + $extra);
            }

            $entries[] = [
                'key'              => $key,
                'kind'             => 'deleted',
                'period_before'    => $this->period($row),
                'period_after'     => null,
                'allowance_before' => $row['allowance'],
                'allowance_after'  => null,
                'before'           => $row['used'],
                'after'            => null,
                'recorded'         => $recorded,
                'has_choice'       => $hasChoice,
                'choice'           => $choice,
                'options'          => $hasChoice ? [self::CARRY => $carry, self::RECORDED => $zero] : null,
                'destination'      => $destination ? $this->period($destination) : null,
                'destination_key'  => $destination['key'] ?? null,
            ];

            $writes[] = ['operation' => 'delete', 'id' => $row['id']];
        }

        foreach ($after as $row) {
            $key       = $row['key'];
            $previous  = $beforeByKey[$key] ?? null;
            $recorded  = $recordedAfter[$key] ?? $zero;
            $usedNow   = $previous['used'] ?? $zero;
            $carried   = $carriedIn[$key] ?? $zero;
            $layoutChanged = $previous === null
                || $previous['employee_contract_id'] !== $row['employee_contract_id']
                || $previous['period_start'] !== $row['period_start']
                || $previous['period_end'] !== $row['period_end'];
            $allowanceChanged = $previous !== null && $previous['allowance'] !== $row['allowance'];

            $isChoice  = $previous !== null && in_array($key, $choiceKeys, true) && ($layoutChanged || $allowanceChanged);
            $options   = null;
            $hasChoice = false;
            $choice    = null;

            if ($previous === null) {
                $result = $this->combine($recorded, $carried, fn (float $leaves, float $extra): float => $leaves + $extra);
            } elseif ($isChoice) {
                $options = [
                    self::CARRY    => $this->combine($usedNow, $recorded, fn (float $used, float $leaves): float => max($used, $leaves)),
                    self::RECORDED => $recorded,
                ];
                $hasChoice = $options[self::CARRY] !== $options[self::RECORDED];
                $choice    = $this->choiceFor($key, $hasChoice, $choices, $missingChoices);
                $result    = $options[$choice ?? self::CARRY];
            } else {
                $recordedPreviously = $recordedBefore[$key] ?? $zero;
                $result             = [];
                foreach (self::COUNTERS as $counter) {
                    $result[$counter] = round(max($recorded[$counter], $usedNow[$counter] + $recorded[$counter] - $recordedPreviously[$counter]) + $carried[$counter], 2);
                }
            }

            if ($previous !== null && !$layoutChanged && !$allowanceChanged && !$hasChoice && $result === $usedNow) {
                continue;
            }

            if ($previous === null && $key === self::NEW_UNLINKED_KEY && $result === $zero) {
                continue;
            }

            $entries[] = [
                'key'              => $key,
                'kind'             => match (true) {
                    $previous === null => 'created',
                    $isChoice          => 'changed',
                    default            => 'adjusted',
                },
                'period_before'    => $previous ? $this->period($previous) : null,
                'period_after'     => $this->period($row),
                'allowance_before' => $previous['allowance'] ?? null,
                'allowance_after'  => $row['allowance'],
                'before'           => $previous ? $usedNow : null,
                'after'            => $result,
                'recorded'         => $recorded,
                'has_choice'       => $hasChoice,
                'choice'           => $choice,
                'options'          => $hasChoice ? $options : null,
                'destination'      => null,
                'destination_key'  => null,
            ];

            $figures = [];
            foreach (RecalculateEmployeeLeaveBalance::ADJUSTMENT_COLUMNS as $usedColumn => $adjustmentColumn) {
                $figures[$usedColumn]       = $result[$usedColumn];
                $figures[$adjustmentColumn] = round($result[$usedColumn] - $recorded[$usedColumn], 2);
            }

            $layout = [
                'employee_contract_id' => $row['employee_contract_id'],
                'period_start'         => $row['period_start'],
                'period_end'           => $row['period_end'],
            ];

            if ($previous === null) {
                $writes[] = ['operation' => 'create', 'attributes' => [...$layout, ...$figures]];

                continue;
            }

            $figuresChanged = $result !== $usedNow || $hasChoice;

            if ($layoutChanged || $figuresChanged) {
                $writes[] = [
                    'operation'  => 'update',
                    'id'         => $previous['id'],
                    'attributes' => [...($layoutChanged ? $layout : []), ...($figuresChanged || $layoutChanged ? $figures : [])],
                ];
            }
        }

        $requiresConfirmation = collect($entries)->contains(
            fn (array $entry): bool => collect([$entry['before'], $entry['after'], $entry['recorded']])
                ->filter()
                ->contains(fn (array $figures): bool => $figures !== $zero)
        );

        return [
            'requires_confirmation' => $requiresConfirmation,
            'missing_choices'       => $missingChoices,
            'entries'               => $entries,
            'writes'                => $writes,
        ];
    }

    /**
     * @param  array<string, string>|null  $choices
     * @param  array<int, string>  $missingChoices
     */
    private function choiceFor(string $key, bool $hasChoice, ?array $choices, array &$missingChoices): ?string
    {
        if (!$hasChoice) {
            return null;
        }

        $choice = $choices[$key] ?? null;

        if (in_array($choice, [self::CARRY, self::RECORDED], true)) {
            return $choice;
        }

        if ($choices !== null) {
            $missingChoices[] = $key;
        }

        return self::CARRY;
    }

    /**
     * @param  array<string, float>  $left
     * @param  array<string, float>  $right
     * @return array<string, float>
     */
    private function combine(array $left, array $right, callable $operation): array
    {
        $result = [];

        foreach (self::COUNTERS as $counter) {
            $result[$counter] = round($operation((float) $left[$counter], (float) $right[$counter]), 2);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{start: string|null, end: string|null, has_contract: bool}
     */
    private function period(array $row): array
    {
        return [
            'start'        => $row['period_start'],
            'end'          => $row['period_end'],
            'has_contract' => $row['employee_contract_id'] !== null,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function currentRows(int $employeeId): Collection
    {
        return EmployeeLeaveBalance::query()
            ->where('employee_id', $employeeId)
            ->with('contract:id,annual_leave_days')
            ->orderBy('id')
            ->get()
            ->map(fn (EmployeeLeaveBalance $balance): array => [
                ...RecalculateEmployeeLeaveBalance::layoutRow($balance),
                'id'        => $balance->id,
                'allowance' => $balance->contract ? (float) $balance->contract->annual_leave_days : null,
                'used'      => [
                    'annual_used'  => round((float) $balance->annual_used, 2),
                    'medical_used' => round((float) $balance->medical_used, 2),
                    'unpaid_used'  => round((float) $balance->unpaid_used, 2),
                ],
            ])
            ->values();
    }

    private function linkableBalance(int $employeeId, mixed $balanceId): ?EmployeeLeaveBalance
    {
        if (empty($balanceId)) {
            return null;
        }

        return EmployeeLeaveBalance::query()
            ->whereKey($balanceId)
            ->where('employee_id', $employeeId)
            ->whereNull('employee_contract_id')
            ->first();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function linkRow(Collection $rows, string $key, int|string $contractId, EmployeeContract $contract): Collection
    {
        return $rows->map(fn (array $row): array => $row['key'] === $key ? [
            ...$row,
            'employee_contract_id' => $contractId,
            'period_start'         => $contract->start_date->toDateString(),
            'period_end'           => $contract->end_date?->toDateString(),
            'allowance'            => (float) $contract->annual_leave_days,
        ] : $row)->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, array{period_start: string, period_end: string|null}>
     */
    private function yearlyPeriods(EmployeeContract $contract, Collection $rows, Carbon $previousStart): array
    {
        $rows = $rows->sortBy([
            fn (array $left, array $right): int => [$left['period_start'] === null, $left['period_start'], $left['id']] <=> [$right['period_start'] === null, $right['period_start'], $right['id']],
        ])->values();

        $yearIndexes = $rows->map(
            fn (array $row): ?int => $row['period_start'] !== null && $row['period_start'] >= $previousStart->toDateString()
                ? (int) round($previousStart->diffInYears(Carbon::parse($row['period_start'])))
                : null
        );

        if ($yearIndexes->contains(null) || $yearIndexes->unique()->count() !== $rows->count()) {
            $yearIndexes = $rows->keys();
        }

        $generator = GenerateEmployeeLeaveBalance::make();
        $periods   = [];

        foreach ($rows as $position => $row) {
            $periodStart = $generator->periodStartOfYear($contract, (int) $yearIndexes[$position]);
            $periodEnd   = $contract->end_date && $periodStart->gt($contract->end_date)
                ? $periodStart->copy()->addYear()->subDay()
                : $generator->periodEnd($contract, $periodStart);

            $periods[$row['key']] = [
                'period_start' => $periodStart->toDateString(),
                'period_end'   => $periodEnd?->toDateString(),
            ];
        }

        return $periods;
    }
}
