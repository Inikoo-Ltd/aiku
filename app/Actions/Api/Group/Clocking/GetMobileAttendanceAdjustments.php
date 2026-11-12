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
use App\Http\Resources\HumanResources\AttendanceAdjustmentResource;
use App\Models\HumanResources\AttendanceAdjustment;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class GetMobileAttendanceAdjustments
{
    use AsController;

    public function handle(ActionRequest $request): array
    {
        $request->merge(['tab' => ClockingEmployeesTabsEnum::ADJUSTMENTS->value]);
        $adjustments = IndexClockingEmployees::make()->handle($request)['adjustments'];

        if (!$adjustments instanceof LengthAwarePaginator) {
            return ['adjustments' => [], 'pagination' => null];
        }

        $adjustments->getCollection()->load('organisation.timezone');

        return [
            'adjustments' => collect($adjustments->items())
                ->map(fn (AttendanceAdjustment $adjustment) => $this->adjustmentData($adjustment))
                ->values()
                ->all(),
            'pagination'  => (new GetMobileLeaves())->pagination($adjustments),
        ];
    }

    public function adjustmentData(AttendanceAdjustment $adjustment): array
    {
        $adjustment->loadMissing('organisation.timezone');

        return array_merge(
            AttendanceAdjustmentResource::make($adjustment)->resolve(),
            ['attachments' => (new DownloadMobileClockingAttachment())->attachments($adjustment)]
        );
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
