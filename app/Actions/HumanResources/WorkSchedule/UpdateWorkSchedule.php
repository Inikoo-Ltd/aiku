<?php

namespace App\Actions\HumanResources\WorkSchedule;

use App\Actions\GrpAction;
use App\Models\HumanResources\WorkSchedule;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class UpdateWorkSchedule extends GrpAction
{
    public function handle(WorkSchedule $workSchedule, array $data): WorkSchedule
    {
        return DB::transaction(function () use ($workSchedule, $data) {
            $metadata = $data['metadata'] ?? [];
            $scheduleData = $data['data'] ?? [];

            $workSchedule->update([
                'name'        => $metadata['name'] ?? $workSchedule->name,
                'timezone_id' => $metadata['timezone_id'] ?? $workSchedule->timezone_id,
                'is_active'   => $metadata['is_active'] ?? $workSchedule->is_active,
            ]);


            foreach ($scheduleData as $dayOfWeek => $dayData) {
                $dayModel = $workSchedule->days()->updateOrCreate(
                    ['day_of_week' => $dayOfWeek],
                    [
                        'start_time'     => $dayData['s'] ?? null,
                        'end_time'       => $dayData['e'] ?? null,
                        'is_working_day' => isset($dayData['s']) && isset($dayData['e']),
                    ]
                );

                $dayModel->breaks()->delete();

                if (isset($dayData['b']) && is_array($dayData['b'])) {
                    foreach ($dayData['b'] as $break) {
                        $dayModel->breaks()->create([
                            'start_time' => $break['s'],
                            'end_time'   => $break['e'],
                            'break_name' => $break['n'] ?? null,
                            'is_paid'    => $break['p'] ?? false,
                        ]);
                    }
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

        return $request->user()->authTo("sysadmin.edit") || $request->user()->authTo("hr.edit");
    }

    public function rules(): array
    {
        return [
            'metadata.name'        => ['sometimes', 'string', 'max:100'],
            'metadata.timezone_id' => ['nullable', 'exists:timezones,id'],
            'metadata.is_active'   => ['sometimes', 'boolean'],
            'data'                 => ['sometimes', 'array'],
            'data.*.s'             => ['nullable', 'date_format:H:i'],
            'data.*.e'             => ['nullable', 'date_format:H:i', 'after:data.*.s'],
            'data.*.b'             => ['nullable', 'array'],
        ];
    }

    public function action(WorkSchedule $workSchedule, array $data = []): WorkSchedule
    {
        $this->asAction = true;
        return $this->handle($workSchedule, $data);
    }
}
