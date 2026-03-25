<?php

namespace App\Actions\HumanResources\WorkSchedule;

use App\Actions\GrpAction;
use App\Models\HumanResources\WorkSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Lorisleiva\Actions\ActionRequest;

class IndexShiftSchedules extends GrpAction
{
    public function handle(ActionRequest $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['data' => []]);
        }

        $employee = $user->employees->first();

        if (!$employee || !$employee->organisation_id) {
            return response()->json(['data' => []]);
        }

        $schedules = WorkSchedule::query()
            ->where('schedulable_type', 'Organisation')
            ->where('schedulable_id', $employee->organisation_id)
            ->select(['id', 'name', 'type', 'is_active'])
            ->get();

        return response()->json(['data' => $schedules]);
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        return $this->handle($request);
    }
}
