<?php

namespace App\Actions\HumanResources\WorkSchedule;

use App\Actions\GrpAction;
use App\Models\HumanResources\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Support\Arr;

class UpdateWorkSchedule extends GrpAction
{
    /**
     * @param Model $parent (Organisation, Group, or Shop)
     * @param array $data
     */
    public function handle(Model $parent, array $data): WorkSchedule
    {

        return DB::transaction(function () use ($parent, $data) {
            $workingHoursPayload = Arr::get($data, 'working_hours', []) ?? [];
            $scheduleName = 'Office Schedule';

            /** @var WorkSchedule $workSchedule */
            $workSchedule = $parent->workSchedules()->firstOrCreate(
                [],
                [
                    'name' => $scheduleName,
                    'is_active' => true
                ]
            );

            $receivedScheduleData = $workingHoursPayload['data'] ?? [];

            for ($dayOfWeek = 1; $dayOfWeek <= 7; $dayOfWeek++) {

                if (array_key_exists($dayOfWeek, $receivedScheduleData)) {

                    $dayData = $receivedScheduleData[$dayOfWeek];

                    $startTime = isset($dayData['s']) ? Carbon::parse($dayData['s'])->format('H:i:s') : null;
                    $endTime = isset($dayData['e']) ? Carbon::parse($dayData['e'])->format('H:i:s') : null;

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
                            $breakStart = isset($break['s']) ? Carbon::parse($break['s'])->format('H:i:s') : null;
                            $breakEnd   = isset($break['e']) ? Carbon::parse($break['e'])->format('H:i:s') : null;

                            if ($breakStart && $breakEnd) {
                                $dayModel->breaks()->create([
                                    'start_time' => $breakStart,
                                    'end_time'   => $breakEnd,
                                    'break_name' => $break['n'] ?? null,
                                    'is_paid'    => $break['p'] ?? false,
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
                'org-admin.' . $this->organisation->id
            ]
        );
    }

    public function rules(): array
    {
        return [
            'working_hours' => ['sometimes', 'array'],
        ];
    }

    public function action(Model $parent, array $data = []): WorkSchedule
    {
        $this->asAction = true;
        return $this->handle($parent, $data);
    }
}
