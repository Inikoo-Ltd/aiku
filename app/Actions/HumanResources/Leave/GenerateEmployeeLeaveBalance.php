<?php

/*
 * @Author: andiferdiawan (https://github.com/andiferdiawan)
 * @Created: YYYY-MM-DD HH:mm:ss
 * @Copyright: Copyright (c) 2026, andiferdiawan
 */

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\Leave;

use App\Models\HumanResources\Employee;
use App\Models\HumanResources\EmployeeContract;
use App\Models\HumanResources\EmployeeLeaveBalance;
use Illuminate\Console\Command;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GenerateEmployeeLeaveBalance
{
    use AsAction;

    public function handle(EmployeeContract $contract): EmployeeLeaveBalance
    {
        return EmployeeLeaveBalance::firstOrCreate(
            ['employee_contract_id' => $contract->id],
            [
                'employee_id'  => $contract->employee_id,
                'period_start' => $contract->start_date->toDateString(),
                'period_end'   => $contract->end_date?->toDateString(),
                'annual_used'  => 0,
                'medical_used' => 0,
                'unpaid_used'  => 0,
            ]
        );
    }

    public function asController(EmployeeContract $contract, ActionRequest $request): EmployeeLeaveBalance
    {
        return $this->handle($contract);
    }

    public function htmlResponse(EmployeeLeaveBalance $balance, ActionRequest $request): RedirectResponse
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
        $today = now()->toDateString();
        $generated = 0;

        Employee::query()
            ->with(['contracts.leaveBalance'])
            ->whereHas('contracts', function ($q) use ($today) {
                $q->where('start_date', '<=', $today)
                  ->where(function ($q2) use ($today) {
                      $q2->whereNull('end_date')->orWhere('end_date', '>=', $today);
                  })
                  ->whereDoesntHave('leaveBalance');
            })
            ->each(function (Employee $employee) use (&$generated) {
                $employee->contracts
                    ->filter(fn (EmployeeContract $c) => $c->leaveBalance === null)
                    ->each(function (EmployeeContract $contract) use (&$generated) {
                        $this->handle($contract);
                        $generated++;
                    });
            });

        $command->info("Generated {$generated} leave balance(s).");

        return 0;
    }
}
