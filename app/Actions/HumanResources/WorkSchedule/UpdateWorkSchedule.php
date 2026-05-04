<?php

namespace App\Actions\HumanResources\WorkSchedule;

use App\Actions\OrgAction;
use App\Models\HumanResources\WorkSchedule;
use App\Models\SysAdmin\Organisation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Support\Arr;

class UpdateWorkSchedule extends OrgAction
{
    public function handle(WorkSchedule $workSchedule, array $data): WorkSchedule
    {
        $workingHoursPayload = Arr::get($data, 'working_hours', []) ?? [];
        $scheduleName = Arr::get($data, 'name', $workSchedule->name);
        $scheduleType = Arr::get($data, 'type', $workSchedule->type);

        return DB::transaction(function () use ($workSchedule, $data, $workingHoursPayload, $scheduleName, $scheduleType) {
            if (Arr::has($data, 'name')) {
                $workSchedule->name = $scheduleName;
            }

            if (Arr::has($data, 'type')) {
                $workSchedule->type = $scheduleType;
            }

            $workSchedule->save();

            $receivedScheduleData = $workingHoursPayload['data'] ?? [];

            for ($dayOfWeek = 1; $dayOfWeek <= 7; $dayOfWeek++) {
                if (array_key_exists($dayOfWeek, $receivedScheduleData)) {
                    $dayData = $receivedScheduleData[$dayOfWeek];

                    $startTime = isset($dayData['s']) && $dayData['s'] ? Carbon::parse($dayData['s'])->format('H:i:s') : null;
                    $endTime = isset($dayData['e']) && $dayData['e'] ? Carbon::parse($dayData['e'])->format('H:i:s') : null;

                    $dayModel = $workSchedule->days()->updateOrCreate(
                        ['day_of_week' => $dayOfWeek],
                        [
                            'start_time'     => $startTime,
                            'end_time'       => $endTime,
                            'is_working_day' => ($startTime && $endTime),
                        ]
                    );

                    $dayModel->breaks()->delete();

                    if (isset($dayData['b']) && is_array($dayData['b'])) {
                        foreach ($dayData['b'] as $break) {
                            $breakStart = isset($break['s']) && $break['s'] ? Carbon::parse($break['s'])->format('H:i:s') : null;
                            $breakEnd = isset($break['e']) && $break['e'] ? Carbon::parse($break['e'])->format('H:i:s') : null;

                            if ($breakStart && $breakEnd) {
                                $dayModel->breaks()->create([
                                    'start_time' => $breakStart,
                                    'end_time' => $breakEnd,
                                    'break_name' => $break['n'] ?? null,
                                    'is_paid' => $break['p'] ?? false,
                                ]);
                            }
                        }
                    }
                } else {
                    $workSchedule->days()->where('day_of_week', $dayOfWeek)->delete();
                }
            }

            return $workSchedule->refresh();
        });
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo(
            [
                'org-admin.' . $this->organisation->id,
            ]
        );
    }

    public function rules(): array
    {
        return [
            'working_hours' => ['sometimes', 'array'],
        ];
    }

    public function asController(Organisation $organisation, WorkSchedule $workSchedule, ActionRequest $request): WorkSchedule
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request);

        return $this->handle($workSchedule, $this->validatedData);
    }

    public function action(Organisation $organisation, WorkSchedule $workSchedule, array $data = []): WorkSchedule
    {
        $this->asAction = true;
        $this->parent = $organisation;

        return $this->handle($workSchedule, $data);
    }
}
