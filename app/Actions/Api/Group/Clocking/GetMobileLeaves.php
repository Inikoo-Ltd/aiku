<?php

/*
 * Author Louis Perez
 * Created on 11-11-2026-11h-14m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Clocking;

use App\Actions\HumanResources\Employee\UI\IndexClockingEmployees;
use App\Enums\HumanResources\Clocking\ClockingEmployeesTabsEnum;
use App\Http\Resources\HumanResources\LeaveBalanceResource;
use App\Http\Resources\HumanResources\LeaveResource;
use App\Models\HumanResources\Leave;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class GetMobileLeaves
{
    use AsController;

    public function handle(ActionRequest $request): array
    {
        $request->merge(['tab' => ClockingEmployeesTabsEnum::LEAVES->value]);
        $leavesTab = IndexClockingEmployees::make()->handle($request);
        $leaves    = $leavesTab['leaves'];

        if (!$leaves instanceof LengthAwarePaginator) {
            return [
                'leaves'                            => [],
                'pagination'                        => null,
                'balance'                           => null,
                'annual_submitted_days'             => null,
                'annual_remaining_after_submission' => null,
                'medical_request_count'             => null,
                'unpaid_request_count'              => null,
                'type_options'                      => [],
                'holidays'                          => [],
            ];
        }

        return [
            'leaves'                            => collect($leaves->items())->map(fn (Leave $leave) => $this->leaveData($leave))->values()->all(),
            'pagination'                        => $this->pagination($leaves),
            'balance'                           => $leavesTab['balance'] ? LeaveBalanceResource::make($leavesTab['balance'])->resolve() : null,
            'annual_submitted_days'             => $leavesTab['annual_submitted_days'],
            'annual_remaining_after_submission' => $leavesTab['annual_remaining_after_submission'],
            'medical_request_count'             => $leavesTab['medical_request_count'],
            'unpaid_request_count'              => $leavesTab['unpaid_request_count'],
            'type_options'                      => collect($leavesTab['leave_type_options'])
                ->map(fn (array $option, string $code) => array_merge(['code' => $code], $option))
                ->values()
                ->all(),
            'holidays'                          => $leavesTab['holiday_dates']->values()->all(),
        ];
    }

    public function leaveData(Leave $leave): array
    {
        return array_merge(
            LeaveResource::make($leave)->resolve(),
            ['attachments' => (new DownloadMobileClockingAttachment())->attachments($leave)]
        );
    }

    /**
     * @return array{current_page: int, last_page: int, per_page: int, total: int}
     */
    public function pagination(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'per_page'     => $paginator->perPage(),
            'total'        => $paginator->total(),
        ];
    }

    public function rules(): array
    {
        return [
            'page'     => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->handle($request)]);
    }
}
