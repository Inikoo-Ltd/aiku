<?php

/*
 * Author Louis Perez
 * Created on 11-11-2026-11h-14m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Clocking;

use App\Actions\HumanResources\Employee\UI\IndexClockingEmployees;
use App\Actions\SysAdmin\User\GetUserCurrentEmployee;
use App\Enums\HumanResources\Clocking\ClockingEmployeesTabsEnum;
use App\Enums\HumanResources\Overtime\OvertimeRequestStatusEnum;
use App\Models\HumanResources\OvertimeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class GetMobileOvertimeRequests
{
    use AsController;

    public function handle(ActionRequest $request): array
    {
        $employee = GetUserCurrentEmployee::fromRequest($request);

        if (!$employee) {
            return ['overtime_requests' => [], 'pagination' => null, 'type_options' => []];
        }

        $clockingPage = IndexClockingEmployees::make();
        $request->merge(['tab' => ClockingEmployeesTabsEnum::OVERTIME->value]);
        /** @var LengthAwarePaginator $overtimeRequests */
        $overtimeRequests = $clockingPage->handle($request)['overtime'];
        $timezone         = $employee->organisation->timezone?->name ?? config('app.timezone');

        return [
            'overtime_requests' => collect($overtimeRequests->items())
                ->map(fn (OvertimeRequest $overtimeRequest) => $this->overtimeRequestData($overtimeRequest, $timezone))
                ->values()
                ->all(),
            'pagination'        => (new GetMobileLeaves())->pagination($overtimeRequests),
            'type_options'      => $clockingPage->getOvertimeTypeOptions($employee->organisation_id)->all(),
        ];
    }

    public function overtimeRequestData(OvertimeRequest $overtimeRequest, string $timezone): array
    {
        $status = $overtimeRequest->status?->value;

        return [
            'id'                         => $overtimeRequest->id,
            'overtime_type_id'           => $overtimeRequest->overtime_type_id,
            'overtime_type_name'         => $overtimeRequest->overtime_type_name ?? $overtimeRequest->overtimeType?->name,
            'requested_date'             => $overtimeRequest->requested_date?->toDateString(),
            'requested_start_at'         => $overtimeRequest->requested_start_at?->timezone($timezone)->toIso8601String(),
            'requested_end_at'           => $overtimeRequest->requested_end_at?->timezone($timezone)->toIso8601String(),
            'requested_duration_minutes' => $overtimeRequest->requested_duration_minutes,
            'recorded_start_at'          => $overtimeRequest->recorded_start_at?->timezone($timezone)->toIso8601String(),
            'recorded_end_at'            => $overtimeRequest->recorded_end_at?->timezone($timezone)->toIso8601String(),
            'recorded_duration_minutes'  => $overtimeRequest->recorded_duration_minutes,
            'lieu_requested_minutes'     => $overtimeRequest->lieu_requested_minutes,
            'reason'                     => $overtimeRequest->reason,
            'status'                     => $status,
            'status_label'               => OvertimeRequestStatusEnum::labels()[$status] ?? $status,
            'approved_at'                => $overtimeRequest->approved_at?->timezone($timezone)->toIso8601String(),
            'rejected_at'                => $overtimeRequest->rejected_at?->timezone($timezone)->toIso8601String(),
        ];
    }

    public function rules(): array
    {
        return [
            'page'     => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'year'     => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month'    => ['nullable', 'integer', 'min:1', 'max:12'],
            'requested_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->handle($request)]);
    }
}
