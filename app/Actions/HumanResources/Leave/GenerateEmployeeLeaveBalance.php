<?php

/*
 * @Author: andiferdiawan (https://github.com/andiferdiawan)
 * @Created: 2026-06-12
 * * @Copyright: Copyright (c) 2026, andiferdiawan
 */

namespace App\Actions\HumanResources\Leave;

use App\Models\HumanResources\EmployeeContract;
use App\Models\HumanResources\EmployeeLeaveBalance;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\Concerns\AsAction;

class GenerateEmployeeLeaveBalance
{
    use AsAction;

    public function handle(EmployeeContract $contract, ?Carbon $periodStart = null): EmployeeLeaveBalance
    {
        $start = $periodStart ?? $contract->start_date;
        $end   = $this->resolvePeriodEnd($contract, $start);

        return EmployeeLeaveBalance::firstOrCreate(
            [
                'employee_contract_id' => $contract->id,
                'period_start'         => $start->toDateString(),
            ],
            [
                'employee_id'  => $contract->employee_id,
                'period_end'   => $end?->toDateString(),
                'annual_used'  => 0,
                'medical_used' => 0,
                'unpaid_used'  => 0,
            ]
        );
    }

    private function resolvePeriodEnd(EmployeeContract $contract, Carbon $start): ?Carbon
    {
        if ($contract->end_date === null) {
            return $start->copy()->addYear()->subDay();
        }

        $oneYearOut = $start->copy()->addYear()->subDay();

        return $contract->end_date->lt($oneYearOut) ? $contract->end_date : $oneYearOut;
    }

    public function asController(EmployeeContract $contract): EmployeeLeaveBalance
    {
        return $this->handle($contract);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back()->with('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Leave balance generated successfully.'),
        ]);
    }

    public function jsonResponse(EmployeeLeaveBalance $balance): JsonResponse
    {
        $contract = $balance->contract;

        return new JsonResponse([
            'created' => $balance->wasRecentlyCreated,
            'balance' => [
                'id'           => $balance->id,
                'annual_days'  => $contract?->annual_leave_days,
                'annual_used'  => $balance->annual_used,
                'medical_used' => $balance->medical_used,
                'unpaid_used'  => $balance->unpaid_used,
            ],
        ]);
    }

    public string $commandSignature = 'leave:generate-balances';

    public function asCommand(Command $command): int
    {
        $today     = now();
        $generated = 0;

        EmployeeContract::query()
            ->where('start_date', '<=', $today->toDateString())
            ->whereNull('end_date')
            ->with('leaveBalances')
            ->each(function (EmployeeContract $contract) use ($today, &$generated) {
                $periodStart = $this->currentPeriodStart($contract, $today);

                $exists = $contract->leaveBalances
                    ->where('period_start', $periodStart->toDateString())
                    ->isNotEmpty();

                if (!$exists) {
                    $this->handle($contract, $periodStart);
                    $generated++;
                }
            });

        $command->info("Generated {$generated} leave balance(s).");

        return 0;
    }

    private function currentPeriodStart(EmployeeContract $contract, Carbon $today): \Carbon\Carbon
    {
        $start = $contract->start_date->copy();

        while ($start->copy()->addYear()->subDay()->lt($today)) {
            $start->addYear();
        }

        return $start;
    }
}
