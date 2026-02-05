<?php

namespace App\Actions\HumanResources\WorkSchedule;

use App\Actions\GrpAction;
use App\Models\HumanResources\WorkSchedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreWorkSchedule extends GrpAction
{
    private Model $parent;

    public function handle(Model $parent, array $data): WorkSchedule
    {
        return DB::transaction(function () use ($parent, $data) {
            $metadata = $data['metadata'] ?? [];
            $scheduleData = $data['data'] ?? [];

            /** @var WorkSchedule $workSchedule */
            $workSchedule = $parent->workSchedules()->create([
                'name' => $metadata['name'] ?? 'Office Schedule',
                'timezone_id' => $metadata['timezone_id'] ?? null,
                'is_active' => $metadata['is_active'] ?? true,
            ]);

            foreach ($scheduleData as $dayOfWeek => $dayData) {
                $workScheduleDay = $workSchedule->days()->create([
                    'day_of_week' => $dayOfWeek,
                    'start_time' => $dayData['s'] ?? null,
                    'end_time' => $dayData['e'] ?? null,
                    'is_working_day' => isset($dayData['s']) && isset($dayData['e']),
                ]);

                if (isset($dayData['b']) && is_array($dayData['b'])) {
                    foreach ($dayData['b'] as $break) {
                        $workScheduleDay->breaks()->create([
                            'start_time' => $break['s'],
                            'end_time' => $break['e'],
                            'break_name' => $break['n'] ?? null,
                            'is_paid' => $break['p'] ?? false,
                        ]);
                    }
                }
            }

            return $workSchedule;
        });
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }
        return $request->user()->authTo("sysadmin.edit") || $request->user()->authTo("hr.edit");
    }

    public function rules(): array
    {
        return [
            'metadata.name' => ['sometimes', 'string', 'max:100'],
            'metadata.timezone_id' => ['nullable', 'exists:timezones,id'],
            'data' => ['required', 'array'],
            'data.*.s' => ['nullable', 'date_format:H:i'],
            'data.*.e' => ['nullable', 'date_format:H:i', 'after:data.*.s'],
            'data.*.b' => ['nullable', 'array'],
        ];
    }

    public function action(Model $parent, array $data = []): WorkSchedule
    {
        $this->asAction = true;
        $this->parent = $parent;

        return $this->handle($parent, $data);
    }


    public function asController(ActionRequest $request, ...$params)
    {
        $parent = $this->resolveParentModel($request);

        $workSchedule = $this->handle($parent, $request->validated());

        return response()->json([
            'status' => 'success',
            'data' => $workSchedule->load('days.breaks')
        ]);
    }

    private function resolveParentModel($request)
    {

        if ($request->route('shop')) {
            return $request->route('shop');
        }
        if ($request->route('organisation')) {
            return $request->route('organisation');
        }

        return $request->user()->group;
    }
}
